<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Report;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\Reports\ReportChartSeriesBuilder;
use Carbon\CarbonImmutable;
use DOMDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Throwable;

final class ReportBladeStringRenderer
{
    public function __construct(
        private readonly ReportChartSeriesBuilder $chartSeriesBuilder,
    ) {}

    /**
     * @param  array{
     *     client: Client,
     *     date_from: CarbonImmutable,
     *     date_until: CarbonImmutable,
     *     period_label: string,
     *     visits: Collection<int, Visit>,
     *     visit_reports: Collection,
     *     aggregations: array{totals_by_bird_type: array<string, int>, totals_by_location: array<string, int>},
     *     rich_content_data: array<string, mixed>,
     *     snapshot: array<string, mixed>
     * }  $period
     */
    public function renderDocument(string $bladeSource, Client $client, Report $report, array $period): string
    {
        $trimmed = trim($bladeSource);

        if ($trimmed === '') {
            return $this->wrapBody('<p class="muted">Plantilla vacia.</p>');
        }

        $data = $this->bladeData($client, $report, $period);

        try {
            $html = Blade::render($trimmed, $data);
        } catch (Throwable $exception) {
            throw new \InvalidArgumentException(
                'Error al compilar la plantilla Blade: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        if ($this->looksLikeFullHtmlDocument($trimmed)) {
            return $html;
        }

        return $this->wrapBody($html);
    }

    /**
     * @param  array<string, mixed>  $period
     * @return array<string, mixed>
     */
    public function bladeData(Client $client, Report $report, array $period): array
    {
        /** @var Collection<int, VisitReport> $visitReports */
        $visitReports = $period['visit_reports'];

        return array_merge(
            $period['rich_content_data'],
            [
                'report' => $report,
                'dateFrom' => $period['date_from'],
                'dateUntil' => $period['date_until'],
                'aggregations' => $period['aggregations'],
                'report_line_charts' => $this->chartSeriesBuilder->build(
                    $visitReports,
                    $period['date_from'],
                    $period['date_until'],
                ),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $period
     * @return array{ok: bool, html: string, message?: string}
     */
    public function tryRenderDocument(string $bladeSource, Client $client, Report $report, array $period): array
    {
        try {
            return [
                'ok' => true,
                'html' => $this->renderDocument($bladeSource, $client, $report, $period),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'html' => '',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Returns HTML safe to embed inside the Filament panel DOM. Full documents are reduced to the
     * inner HTML of the body element so the page does not contain nested document nodes, which
     * breaks the DOM and Alpine (for example the panel sidebar store).
     */
    public function htmlForAdminPreview(string $html): string
    {
        $trimmed = trim($html);

        if ($trimmed === '') {
            return '';
        }

        $head = strtolower(substr($trimmed, 0, 512));

        if (! str_starts_with($head, '<!doctype html') && ! str_starts_with($head, '<html')) {
            return $html;
        }

        libxml_use_internal_errors(true);

        $document = str_contains($trimmed, '<?xml encoding=')
            ? $trimmed
            : '<?xml encoding="UTF-8">'.$trimmed;

        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML($document, \LIBXML_NOERROR | \LIBXML_NOWARNING | \LIBXML_COMPACT);

        libxml_clear_errors();

        if (! $loaded) {
            return $this->fallbackExtractBodyInnerHtml($trimmed);
        }

        $body = $dom->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return $this->fallbackExtractBodyInnerHtml($trimmed);
        }

        $inner = '';

        foreach ($body->childNodes as $child) {
            $inner .= $dom->saveHTML($child) ?: '';
        }

        $inner = trim($inner);

        return $inner !== '' ? $inner : $this->fallbackExtractBodyInnerHtml($trimmed);
    }

    private function wrapBody(string $bodyHtml): string
    {
        return view('pdf.html-document-wrapper', [
            'bodyHtml' => $bodyHtml,
        ])->render();
    }

    private function looksLikeFullHtmlDocument(string $blade): bool
    {
        $head = strtolower(substr(ltrim($blade), 0, 512));

        return str_starts_with($head, '<!doctype html')
            || str_starts_with($head, '<html');
    }

    private function fallbackExtractBodyInnerHtml(string $html): string
    {
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return $html;
    }
}
