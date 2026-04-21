<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Report;
use App\Models\Template;
use App\Models\Visit;
use App\Models\VisitReport;
use App\ReportStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GenerateMonthlyReportPdfService
{
    public function generate(int $clientId, int $month, int $year, ?int $templateId = null): Report
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Month must be between 1 and 12.');
        }

        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Year is out of range.');
        }

        $client = Client::query()->findOrFail($clientId);
        $template = $this->resolveTemplate($client, $templateId);

        $startDate = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->endOfMonth()->endOfDay();

        $visits = Visit::query()
            ->whereBelongsTo($client)
            ->whereBetween('date_init', [$startDate, $endDate])
            ->with(['employee', 'visitReports.location', 'visitReports.birdType'])
            ->orderBy('date_init')
            ->get();

        $visitReports = $visits->flatMap->visitReports;
        $aggregations = $this->buildAggregations($visitReports);

        return DB::transaction(function () use ($client, $template, $month, $year, $visits, $visitReports, $aggregations): Report {
            $report = Report::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'month' => $month,
                    'year' => $year,
                ],
                [
                    'template_id' => $template?->id,
                    'status' => ReportStatus::Generated,
                    'generated_at' => now(),
                    'data' => [
                        'client' => $client->name,
                        'period' => sprintf('%04d-%02d', $year, $month),
                        'visits_count' => $visits->count(),
                        'totals_by_bird_type' => $aggregations['totals_by_bird_type'],
                        'totals_by_location' => $aggregations['totals_by_location'],
                        'total_observations' => $visitReports->count(),
                        'total_quantity' => $visitReports->sum('quantity'),
                    ],
                ],
            );

            $pdf = Pdf::loadView('pdf.monthly-report', [
                'report' => $report,
                'client' => $client,
                'template' => $template,
                'sections' => $template?->sections?->sortBy('order')->values() ?? collect(),
                'periodLabel' => sprintf('%s %d', Str::ucfirst($this->monthNameInSpanish($month)), $year),
                'visits' => $visits,
                'visitReports' => $visitReports,
                'aggregations' => $aggregations,
            ])->setPaper('a4');

            $filePath = $this->buildStoragePath($client, $month, $year);

            Storage::disk('local')->put($filePath, $pdf->output());

            $report->update([
                'generated_file_path' => $filePath,
            ]);

            return $report->fresh();
        });
    }

    private function resolveTemplate(Client $client, ?int $templateId): ?Template
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

    private function buildStoragePath(Client $client, int $month, int $year): string
    {
        $normalizedClientName = Str::of($client->name)
            ->squish()
            ->replaceMatches('/[<>:"\/\\\\|?*\x00-\x1F]/u', '')
            ->trim();

        if ($normalizedClientName->isEmpty()) {
            $normalizedClientName = Str::of("cliente-{$client->id}");
        }

        $baseFileName = sprintf(
            '%s %s %d',
            $normalizedClientName->value(),
            $this->monthNameInSpanish($month),
            $year,
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
            default => throw new InvalidArgumentException('Month must be between 1 and 12.'),
        };
    }

    /**
     * @param  Collection<int, VisitReport>  $visitReports
     * @return array{
     *   totals_by_bird_type: array<string, int>,
     *   totals_by_location: array<string, int>
     * }
     */
    private function buildAggregations(Collection $visitReports): array
    {
        $totalsByBirdType = $visitReports
            ->groupBy('bird_type_id')
            ->mapWithKeys(fn (Collection $group): array => [
                (string) $group->first()?->birdType?->name => (int) $group->sum('quantity'),
            ])
            ->all();

        $totalsByLocation = $visitReports
            ->groupBy('location_id')
            ->mapWithKeys(fn (Collection $group): array => [
                (string) $group->first()?->location?->name => (int) $group->sum('quantity'),
            ])
            ->all();

        return [
            'totals_by_bird_type' => $totalsByBirdType,
            'totals_by_location' => $totalsByLocation,
        ];
    }
}
