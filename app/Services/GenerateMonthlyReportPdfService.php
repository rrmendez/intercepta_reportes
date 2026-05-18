<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HtmlToPdfConverter;
use App\Models\Client;
use App\Models\Report;
use App\Models\Template;
use App\ReportStatus;
use App\Services\Reports\ReportPeriodData;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GenerateMonthlyReportPdfService
{
    public function __construct(
        private readonly ReportPeriodData $periodData,
        private readonly ReportBladeStringRenderer $bladeRenderer,
        private readonly HtmlToPdfConverter $pdfConverter,
    ) {}

    public function generate(int $clientId, int $month, int $year, ?int $templateId = null): Report
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('El mes debe estar entre 1 y 12.');
        }

        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('El anio esta fuera de rango.');
        }

        $startDate = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->endOfMonth()->endOfDay();

        return $this->generateForRange($clientId, $startDate, $endDate, $templateId);
    }

    public function generateForRange(int $clientId, CarbonInterface|string $dateFrom, CarbonInterface|string $dateUntil, ?int $templateId = null): Report
    {
        $client = Client::query()->findOrFail($clientId);
        $template = $this->resolveTemplateForClient($client, $templateId);
        [$from, $until] = $this->periodData->normalizeRange($dateFrom, $dateUntil);
        $period = $this->periodData->load($client, $from, $until);

        return DB::transaction(function () use ($client, $template, $from, $until, $period): Report {
            $report = Report::query()
                ->where('client_id', $client->id)
                ->whereDate('date_from', $from->toDateString())
                ->whereDate('date_until', $until->toDateString())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($report === null) {
                $report = new Report([
                    'client_id' => $client->id,
                    'date_from' => $from->toDateString(),
                    'date_until' => $until->toDateString(),
                ]);
            }

            $report->fill([
                'month' => $from->month,
                'year' => $from->year,
                'template_id' => $template?->id,
                'status' => ReportStatus::Generated,
                'generated_at' => now(),
                'data' => $period['snapshot'],
                'generated_by_user_id' => $report->generated_by_user_id ?? auth()->id(),
            ]);
            $report->save();

            $period = $this->periodData->load($client, $from, $until, $report);
            $pdfBinary = $this->renderPdfBinary(
                client: $client,
                report: $report,
                template: $template,
                period: $period,
                pdfTemplate: $template?->pdf_template,
            );

            $filePath = $this->buildStoragePath($client, $from, $until);

            Storage::disk('local')->put($filePath, $pdfBinary);

            $report->update([
                'generated_file_path' => $filePath,
            ]);

            return $report->fresh();
        });
    }

    /**
     * @param  array{
     *     rich_content_data: array<string, mixed>,
     *     period_label: string,
     *     visits: Collection,
     *     visit_reports: Collection,
     *     aggregations: array{totals_by_bird_type: array<string, int>, totals_by_location: array<string, int>}
     * }  $period
     */
    public function renderPdfBinary(
        Client $client,
        Report $report,
        ?Template $template,
        array $period,
        ?string $pdfTemplate,
    ): string {
        $blade = $pdfTemplate;

        if ($blade === null || trim($blade) === '') {
            $blade = ReportPdfTemplateDefaults::bladeSourceForClient($client);
        }

        $html = $this->bladeRenderer->renderDocument($blade, $client, $report, $period);

        $chromeFooterHtml = view('pdf.partials.report-pdf-chrome-footer-template', [
            'client' => $client,
            'report' => $report,
            'period_label' => $period['period_label'],
        ])->render();

        $documentHtml = ReportPdfDocumentHtml::withDefaultHeader(
            ReportPdfDocumentHtml::withoutEmbeddedFixedFooter($html),
            view('pdf.partials.report-pdf-default-header')->render(),
        );

        return $this->pdfConverter->convert($documentHtml, [
            'chrome_footer_html' => $chromeFooterHtml,
        ]);
    }

    public function resolveTemplateForClient(Client $client, ?int $templateId): ?Template
    {
        if ($templateId !== null) {
            return Template::query()
                ->whereBelongsTo($client)
                ->with('sections')
                ->findOrFail($templateId);
        }

        return Template::query()
            ->whereBelongsTo($client)
            ->where('active', true)
            ->with('sections')
            ->orderByDesc('id')
            ->first();
    }

    public function buildStoragePath(Client $client, CarbonImmutable $dateFrom, CarbonImmutable $dateUntil): string
    {
        $normalizedClientName = Str::of($client->name)
            ->squish()
            ->replaceMatches('/[<>:"\/\\\\|?*\x00-\x1F]/u', '')
            ->trim();

        if ($normalizedClientName->isEmpty()) {
            $normalizedClientName = Str::of("cliente-{$client->id}");
        }

        $period = $dateFrom->isStartOfMonth() && $dateUntil->isSameDay($dateFrom->endOfMonth())
            ? sprintf('%s %d', $this->monthNameInSpanish($dateFrom->month), $dateFrom->year)
            : sprintf('%s al %s', $dateFrom->format('d-m-Y'), $dateUntil->format('d-m-Y'));

        $baseFileName = sprintf(
            '%s %s',
            $normalizedClientName->value(),
            $period,
        );

        $trimmedBaseFileName = Str::of($baseFileName)->trim()->value();
        $limitedBaseFileName = Str::limit($trimmedBaseFileName, 180, '');

        return sprintf('reports/%s.pdf', $limitedBaseFileName);
    }

    private function monthNameInSpanish(int $month): string
    {
        return match ($month) {
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
            default => throw new InvalidArgumentException('El mes debe estar entre 1 y 12.'),
        };
    }
}
