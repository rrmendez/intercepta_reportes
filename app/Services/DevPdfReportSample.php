<?php

declare(strict_types=1);

namespace App\Services;

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Report;
use App\Models\Visit;
use App\Models\VisitReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Fixed in-memory fixtures for local PDF preview routes ({@see DevPdfSamplePreviewController}).
 */
final class DevPdfReportSample
{
    /**
     * @return array{
     *     client: Client,
     *     report: Report,
     *     period: array{
     *         client: Client,
     *         date_from: CarbonImmutable,
     *         date_until: CarbonImmutable,
     *         period_label: string,
     *         visits: Collection<int, Visit>,
     *         visit_reports: Collection<int, VisitReport>,
     *         aggregations: array{totals_by_bird_type: array<string, int>, totals_by_location: array<string, int>},
     *         rich_content_data: array<string, mixed>,
     *         snapshot: array<string, mixed>
     *     }
     * }
     */
    public static function build(ClientImportMode $mode): array
    {
        return match ($mode) {
            ClientImportMode::SingleSectorSingleBird => self::singleSectorSingleBirdConaprole(),
            default => self::genericPlaceholder($mode),
        };
    }

    /**
     * Basado en «Informe final una zona un ave.pdf» (Conaprole Planta Industrial Nº 11).
     *
     * @return array{
     *     client: Client,
     *     report: Report,
     *     period: array{
     *         client: Client,
     *         date_from: CarbonImmutable,
     *         date_until: CarbonImmutable,
     *         period_label: string,
     *         visits: Collection<int, Visit>,
     *         visit_reports: Collection<int, VisitReport>,
     *         aggregations: array{totals_by_bird_type: array<string, int>, totals_by_location: array<string, int>},
     *         rich_content_data: array<string, mixed>,
     *         snapshot: array<string, mixed>
     *     }
     * }
     */
    private static function singleSectorSingleBirdConaprole(): array
    {
        $client = Client::make([
            'id' => 1,
            'name' => 'Conaprole Planta Industrial Nº 11 – San José Rincón del Pino',
            'address' => 'San José, Rincón del Pino',
            'active' => true,
            'import_mode' => ClientImportMode::SingleSectorSingleBird,
        ]);

        $employee = Employee::make([
            'id' => 1,
            'name' => 'Manuel Maier',
            'active' => true,
        ]);

        $location = Location::make([
            'id' => 1,
            'client_id' => 1,
            'name' => 'Planta central',
            'active' => true,
        ]);

        $birdType = BirdType::make([
            'id' => 1,
            'slug' => 'palomas',
            'name' => 'Palomas',
            'common_name' => 'Paloma doméstica',
            'common_name_plural' => 'Palomas domésticas',
            'scientific_name' => 'Columba livia',
            'active' => true,
        ]);

        /** @var list<array{date: string, init: string, end: string, qty: int, observation: string}> $historicalRows */
        $historicalRows = [
            ['date' => '2025-10-15', 'init' => '17:00', 'end' => '18:00', 'qty' => 125, 'observation' => 'Relevamiento inicial'],
            ['date' => '2025-11-12', 'init' => '16:30', 'end' => '17:30', 'qty' => 98, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2025-12-08', 'init' => '18:00', 'end' => '19:00', 'qty' => 72, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-01-20', 'init' => '17:00', 'end' => '18:00', 'qty' => 45, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-02-14', 'init' => '16:00', 'end' => '17:00', 'qty' => 18, 'observation' => 'Control realizado con normalidad'],
        ];

        /** @var list<array{date: string, init: string, end: string, qty: int, observation: string}> $rows */
        $rows = [
            ['date' => '2026-03-02', 'init' => '19:00', 'end' => '20:00', 'qty' => 0, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-03', 'init' => '14:00', 'end' => '15:00', 'qty' => 0, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-04', 'init' => '17:00', 'end' => '18:00', 'qty' => 0, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-09', 'init' => '16:30', 'end' => '17:30', 'qty' => 0, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-10', 'init' => '16:30', 'end' => '17:30', 'qty' => 1, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-12', 'init' => '18:00', 'end' => '19:00', 'qty' => 0, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-16', 'init' => '17:00', 'end' => '18:00', 'qty' => 0, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-18', 'init' => '16:30', 'end' => '17:30', 'qty' => 0, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-19', 'init' => '19:00', 'end' => '20:00', 'qty' => 2, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-24', 'init' => '17:00', 'end' => '18:00', 'qty' => 2, 'observation' => 'Se captura 1 paloma'],
            ['date' => '2026-03-25', 'init' => '16:00', 'end' => '17:00', 'qty' => 0, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-26', 'init' => '18:00', 'end' => '19:00', 'qty' => 0, 'observation' => 'Control realizado con normalidad'],
            ['date' => '2026-03-30', 'init' => '18:30', 'end' => '19:30', 'qty' => 0, 'observation' => 'Control realizado con normalidad'],
        ];

        $visits = collect();
        $visitReports = collect();
        $historicalVisitReports = collect();

        foreach ($historicalRows as $index => $row) {
            [$visit, $visitReport] = self::makeVisitPair(
                visitId: 1000 + $index,
                client: $client,
                employee: $employee,
                location: $location,
                birdType: $birdType,
                row: $row,
            );

            $historicalVisitReports->push($visitReport);
        }

        foreach ($rows as $index => $row) {
            [$visit, $visitReport] = self::makeVisitPair(
                visitId: $index + 1,
                client: $client,
                employee: $employee,
                location: $location,
                birdType: $birdType,
                row: $row,
            );

            $visits->push($visit);
            $visitReports->push($visitReport);
            $historicalVisitReports->push($visitReport);
        }

        $from = CarbonImmutable::parse('2026-03-01')->startOfDay();
        $until = CarbonImmutable::parse('2026-03-31')->endOfDay();
        $periodLabel = 'marzo 2026';

        $report = Report::make([
            'id' => 58,
            'client_id' => 1,
            'month' => 3,
            'year' => 2026,
            'date_from' => $from->toDateString(),
            'date_until' => $until->toDateString(),
        ])->setRelation('client', $client);

        $report->setAttribute('generated_at', CarbonImmutable::parse('2026-04-05 10:00:00'));

        $aggregations = TemplateRichContent::aggregations($visitReports);
        $quantityKey = 'qty_1_1';

        $visitTableRows = collect($rows)
            ->map(fn (array $row): array => [
                'visit_fecha' => CarbonImmutable::parse($row['date'])->format('d/m/Y'),
                'visit_entrada' => $row['init'],
                'visit_salida' => $row['end'],
                $quantityKey => (string) $row['qty'],
                'visit_observation' => $row['observation'],
            ])
            ->all();

        $richContentData = array_merge(
            TemplateRichContent::reportData(
                client: $client,
                periodLabel: $periodLabel,
                visits: $visits,
                visitReports: $visitReports,
                aggregations: $aggregations,
                report: $report,
            ),
            [
                'visits' => $visitTableRows,
                'visit_columns' => [
                    ['key' => 'visit_fecha', 'label' => 'Fecha'],
                    ['key' => 'visit_entrada', 'label' => 'Entrada'],
                    ['key' => 'visit_salida', 'label' => 'Salida'],
                    ['key' => $quantityKey, 'label' => 'Conteo palomas'],
                    ['key' => 'visit_observation', 'label' => 'Observaciones'],
                ],
                'visits_count' => count($visitTableRows),
                'total_quantity' => (int) $visitReports->sum('quantity'),
                'totals_by_bird_type' => ['Paloma doméstica' => 125],
                'totals_by_location' => ['Planta central' => (int) $visitReports->sum('quantity')],
            ],
        );

        $period = [
            'client' => $client,
            'date_from' => $from,
            'date_until' => $until,
            'period_label' => $periodLabel,
            'visits' => $visits,
            'visit_reports' => $visitReports,
            'historical_visit_reports' => $historicalVisitReports,
            'aggregations' => [
                'totals_by_bird_type' => $richContentData['totals_by_bird_type'],
                'totals_by_location' => $richContentData['totals_by_location'],
            ],
            'rich_content_data' => $richContentData,
            'snapshot' => [
                'period' => $periodLabel,
                'visits_count' => $visits->count(),
            ],
        ];

        return [
            'client' => $client,
            'report' => $report,
            'period' => $period,
        ];
    }

    /**
     * @param  array{date: string, init: string, end: string, qty: int, observation: string}  $row
     * @return array{0: Visit, 1: VisitReport}
     */
    private static function makeVisitPair(
        int $visitId,
        Client $client,
        Employee $employee,
        Location $location,
        BirdType $birdType,
        array $row,
    ): array {
        $visit = Visit::make([
            'id' => $visitId,
            'client_id' => (int) $client->id,
            'employee_id' => (int) $employee->id,
            'date_init' => "{$row['date']} {$row['init']}:00",
            'date_end' => "{$row['date']} {$row['end']}:00",
            'observation' => $row['observation'],
        ])->setRelations([
            'employee' => $employee,
            'visitReports' => collect(),
        ]);

        $visitReport = VisitReport::make([
            'id' => $visitId,
            'visit_id' => $visitId,
            'location_id' => (int) $location->id,
            'bird_type_id' => (int) $birdType->id,
            'quantity' => $row['qty'],
            'observation' => null,
        ])->setRelations([
            'location' => $location,
            'birdType' => $birdType,
            'visit' => $visit,
        ]);

        $visit->setRelation('visitReports', collect([$visitReport]));

        return [$visit, $visitReport];
    }

    /**
     * @return array{
     *     client: Client,
     *     report: Report,
     *     period: array{
     *         client: Client,
     *         date_from: CarbonImmutable,
     *         date_until: CarbonImmutable,
     *         period_label: string,
     *         visits: Collection<int, Visit>,
     *         visit_reports: Collection<int, VisitReport>,
     *         aggregations: array{totals_by_bird_type: array<string, int>, totals_by_location: array<string, int>},
     *         rich_content_data: array<string, mixed>,
     *         snapshot: array<string, mixed>
     *     }
     * }
     */
    private static function genericPlaceholder(ClientImportMode $mode): array
    {
        $client = Client::make([
            'id' => 1,
            'name' => 'Cliente demostración S.A.',
            'address' => 'Av. 18 de Julio 1234, Montevideo',
            'active' => true,
            'import_mode' => $mode,
        ]);

        $report = Report::make([
            'id' => 99,
            'client_id' => 1,
            'month' => 1,
            'year' => 2026,
            'date_from' => '2026-01-01',
            'date_until' => '2026-01-31',
        ])->setRelation('client', $client);

        $report->setAttribute('generated_at', CarbonImmutable::parse('2026-05-14 10:30:00'));

        $periodLabel = 'enero 2026';
        $visits = new Collection;
        $visitReports = new Collection;
        $aggregations = TemplateRichContent::aggregations($visitReports);

        $richContentData = array_merge(
            TemplateRichContent::reportData(
                client: $client,
                periodLabel: $periodLabel,
                visits: $visits,
                visitReports: $visitReports,
                aggregations: $aggregations,
                report: $report,
            ),
            [
                'visits_count' => 7,
                'total_observations' => 24,
                'total_quantity' => 156,
                'totals_by_bird_type' => [
                    'Paloma doméstica' => 88,
                    'Gaviota cocinera' => 42,
                ],
                'totals_by_location' => [
                    'Planta industrial — sector norte' => 70,
                    'Planta industrial — sector sur' => 60,
                ],
            ],
        );

        $from = CarbonImmutable::parse('2026-01-01')->startOfDay();
        $until = CarbonImmutable::parse('2026-01-31')->endOfDay();

        return [
            'client' => $client,
            'report' => $report,
            'period' => [
                'client' => $client,
                'date_from' => $from,
                'date_until' => $until,
                'period_label' => $periodLabel,
                'visits' => $visits,
                'visit_reports' => $visitReports,
                'aggregations' => [
                    'totals_by_bird_type' => $richContentData['totals_by_bird_type'],
                    'totals_by_location' => $richContentData['totals_by_location'],
                ],
                'rich_content_data' => $richContentData,
                'snapshot' => [
                    'period' => $periodLabel,
                    'visits_count' => 7,
                ],
            ],
        ];
    }
}
