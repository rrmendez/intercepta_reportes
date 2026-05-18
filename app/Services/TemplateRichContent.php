<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Report;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\VisitSpreadsheet\VisitSpreadsheetQuantityColumns;
use App\Services\VisitSpreadsheet\VisitSpreadsheetTableRowArrays;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shared data helpers for report PDF Blade templates and period snapshots.
 */
class TemplateRichContent
{
    /**
     * @return array<string, string>
     */
    public static function mergeTagLabels(): array
    {
        return [
            'client_name' => 'Cliente',
            'period_label' => 'Periodo',
            'generated_at' => 'Fecha de generacion',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mergeTagValues(Client $client, string $periodLabel, ?Report $report = null): array
    {
        return [
            'client_name' => $client->name,
            'period_label' => $periodLabel,
            'generated_at' => fn (): string => ($report?->generated_at ?? now())->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, VisitReport>  $visitReports
     * @param  array{totals_by_bird_type: array<string, int>, totals_by_location: array<string, int>}  $aggregations
     * @return array{
     *     client: Client,
     *     report: Report|null,
     *     period_label: string,
     *     visits: list<array<string, string>>,
     *     visit_columns: list<array{key: string, label: string}>,
     *     visit_reports: Collection<int, VisitReport>,
     *     visits_count: int,
     *     total_observations: int,
     *     total_quantity: int,
     *     totals_by_bird_type: array<string, int>,
     *     totals_by_location: array<string, int>
     * }
     */
    public static function reportData(
        Client $client,
        string $periodLabel,
        Collection $visits,
        Collection $visitReports,
        array $aggregations,
        ?Report $report = null,
    ): array {
        $visitRows = app(VisitSpreadsheetTableRowArrays::class)->forClient($client, $visits);

        return [
            'client' => $client,
            'report' => $report,
            'period_label' => $periodLabel,
            'visits' => $visitRows,
            'visit_columns' => self::visitColumns($client),
            'visit_reports' => $visitReports,
            'visits_count' => $visits->count(),
            'total_observations' => $visitReports->count(),
            'total_quantity' => (int) $visitReports->sum('quantity'),
            'totals_by_bird_type' => $aggregations['totals_by_bird_type'],
            'totals_by_location' => $aggregations['totals_by_location'],
        ];
    }

    /**
     * @param  Collection<int, VisitReport>  $visitReports
     * @return array{totals_by_bird_type: array<string, int>, totals_by_location: array<string, int>}
     */
    public static function aggregations(Collection $visitReports): array
    {
        return [
            'totals_by_bird_type' => $visitReports
                ->groupBy('bird_type_id')
                ->mapWithKeys(fn (Collection $group): array => [
                    (string) $group->first()?->birdType?->name => (int) $group->sum('quantity'),
                ])
                ->all(),
            'totals_by_location' => $visitReports
                ->groupBy('location_id')
                ->mapWithKeys(fn (Collection $group): array => [
                    (string) $group->first()?->location?->name => (int) $group->sum('quantity'),
                ])
                ->all(),
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private static function visitColumns(Client $client): array
    {
        return [
            ['key' => 'visit_date_init', 'label' => self::columnLabel('Inicio')],
            ['key' => 'visit_date_end', 'label' => self::columnLabel('Fin')],
            ['key' => 'employee.name', 'label' => self::columnLabel('Empleado')],
            ...array_map(
                static fn (array $column): array => [
                    'key' => (string) $column['key'],
                    'label' => self::columnLabel((string) $column['label']),
                ],
                app(VisitSpreadsheetQuantityColumns::class)->forClient($client),
            ),
            ['key' => 'visit_observation', 'label' => self::columnLabel('Observacion')],
        ];
    }

    private static function columnLabel(string $label): string
    {
        return Str::ucfirst($label);
    }
}
