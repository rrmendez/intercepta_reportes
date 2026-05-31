<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\VisitReport;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class ReportChartSeriesBuilder
{
    private const int MAX_SERIES = 8;

    /**
     * @var list<string>
     */
    private const array PALETTE = [
        '#2563eb',
        '#dc2626',
        '#16a34a',
        '#ca8a04',
        '#9333ea',
        '#0891b2',
        '#ea580c',
        '#4b5563',
    ];

    /**
     * @param  Collection<int, VisitReport>  $periodVisitReports
     * @param  Collection<int, VisitReport>  $historicalVisitReports
     * @return array{charts: list<array{id: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, scales?: array<string, array<string, mixed>>}>}
     */
    public function buildFaunaEvolutionCharts(
        Collection $periodVisitReports,
        Collection $historicalVisitReports,
    ): array {
        if ($periodVisitReports instanceof EloquentCollection && $periodVisitReports->isNotEmpty()) {
            $periodVisitReports->loadMissing(['visit', 'birdType']);
        }

        if ($historicalVisitReports instanceof EloquentCollection && $historicalVisitReports->isNotEmpty()) {
            $historicalVisitReports->loadMissing(['visit', 'birdType']);
        }

        $periodBuilt = $this->buildBirdCountsByVisitDay($periodVisitReports);
        $historicalBuilt = $this->buildBirdCountsByVisitDay($historicalVisitReports);

        return [
            'charts' => [
                $this->faunaEvolutionChartDefinition(
                    id: 'report-chart-fauna-period',
                    subtitle: 'Conteos de aves',
                    labels: $periodBuilt['labels'],
                    dayKeys: $periodBuilt['day_keys'],
                    matrix: $periodBuilt['matrix'],
                ),
                $this->faunaEvolutionChartDefinition(
                    id: 'report-chart-fauna-historical',
                    subtitle: 'Conteos de aves (historico)',
                    labels: $historicalBuilt['labels'],
                    dayKeys: $historicalBuilt['day_keys'],
                    matrix: $historicalBuilt['matrix'],
                ),
            ],
        ];
    }

    /**
     * @param  list<string>  $labels
     * @param  list<string>  $dayKeys
     * @param  array<string, array<string, int>>  $matrix
     * @return array{id: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, x_axis_label: string, y_axis_label: string}
     */
    private function faunaEvolutionChartDefinition(
        string $id,
        string $subtitle,
        array $labels,
        array $dayKeys,
        array $matrix,
    ): array {
        return [
            'id' => $id,
            'title' => $subtitle,
            'display_title' => false,
            'labels' => $labels,
            'datasets' => $this->datasetsFromMatrix($matrix, $dayKeys),
            'x_axis_label' => 'Fecha',
            'y_axis_label' => 'Cantidad',
        ];
    }

    /**
     * @param  Collection<int, VisitReport>  $visitReports
     * @return array{labels: list<string>, day_keys: list<string>, matrix: array<string, array<string, int>>}
     */
    private function buildBirdCountsByVisitDay(Collection $visitReports): array
    {
        /** @var array<string, true> $dayKeySet */
        $dayKeySet = [];
        /** @var array<string, array<string, int>> $matrix */
        $matrix = [];

        foreach ($visitReports as $report) {
            $visitDate = $report->visit?->date_init;

            if ($visitDate === null) {
                continue;
            }

            $birdName = trim((string) ($report->birdType?->name ?? ''));

            if ($birdName === '') {
                $birdName = 'Sin tipo';
            }

            $dayKey = CarbonImmutable::parse($visitDate)->toDateString();
            $dayKeySet[$dayKey] = true;

            if (! isset($matrix[$birdName])) {
                $matrix[$birdName] = [];
            }

            $matrix[$birdName][$dayKey] = ($matrix[$birdName][$dayKey] ?? 0) + (int) $report->quantity;
        }

        $dayKeys = array_keys($dayKeySet);
        sort($dayKeys);

        foreach ($matrix as $birdName => $pointsByDay) {
            foreach ($dayKeys as $dayKey) {
                if (! array_key_exists($dayKey, $pointsByDay)) {
                    $matrix[$birdName][$dayKey] = 0;
                }
            }
        }

        $labels = array_map(
            fn (string $dayKey): string => CarbonImmutable::parse($dayKey)->format('d/m/Y'),
            $dayKeys,
        );

        return [
            'labels' => $labels,
            'day_keys' => $dayKeys,
            'matrix' => $matrix,
        ];
    }

    /**
     * @param  Collection<int, VisitReport>  $visitReports
     * @return array{charts: list<array{id: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}>}
     */
    public function build(Collection $visitReports, CarbonInterface|string $dateFrom, CarbonInterface|string $dateUntil): array
    {
        if ($visitReports instanceof EloquentCollection && $visitReports->isNotEmpty()) {
            $visitReports->loadMissing(['visit', 'location', 'birdType']);
        }

        $from = CarbonImmutable::parse((string) $dateFrom)->startOfDay();
        $until = CarbonImmutable::parse((string) $dateUntil)->startOfDay();
        $dayKeys = $this->dayKeys($from, $until);
        $labels = array_map(fn (string $key): string => CarbonImmutable::parse($key)->format('d/m'), $dayKeys);

        return [
            'charts' => [
                $this->chartDefinition(
                    id: 'report-chart-bird-type',
                    title: 'Cantidad por tipo de ave',
                    dayKeys: $dayKeys,
                    labels: $labels,
                    visitReports: $visitReports,
                    seriesResolver: fn (VisitReport $report): string => (string) ($report->birdType?->name ?: 'Sin tipo'),
                ),
                $this->chartDefinition(
                    id: 'report-chart-location',
                    title: 'Cantidad por ubicacion',
                    dayKeys: $dayKeys,
                    labels: $labels,
                    visitReports: $visitReports,
                    seriesResolver: fn (VisitReport $report): string => (string) ($report->location?->name ?: 'Sin ubicacion'),
                ),
            ],
        ];
    }

    /**
     * @param  list<string>  $dayKeys
     * @param  list<string>  $labels
     * @param  Collection<int, VisitReport>  $visitReports
     * @param  callable(VisitReport): string  $seriesResolver
     * @return array{id: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function chartDefinition(
        string $id,
        string $title,
        array $dayKeys,
        array $labels,
        Collection $visitReports,
        callable $seriesResolver,
    ): array {
        $matrix = [];

        foreach ($visitReports as $report) {
            $visitDate = $report->visit?->date_init;

            if ($visitDate === null) {
                continue;
            }

            $dayKey = CarbonImmutable::parse($visitDate)->toDateString();
            $seriesName = $seriesResolver($report);

            if (! isset($matrix[$seriesName])) {
                $matrix[$seriesName] = array_fill_keys($dayKeys, 0);
            }

            if (! array_key_exists($dayKey, $matrix[$seriesName])) {
                continue;
            }

            $matrix[$seriesName][$dayKey] += (int) $report->quantity;
        }

        $datasets = $this->datasetsFromMatrix($matrix, $dayKeys);

        return [
            'id' => $id,
            'title' => $title,
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * @param  array<string, array<string, int>>  $matrix
     * @param  list<string>  $dayKeys
     * @return list<array<string, mixed>>
     */
    private function datasetsFromMatrix(array $matrix, array $dayKeys): array
    {
        if ($matrix === []) {
            return [];
        }

        uasort(
            $matrix,
            fn (array $left, array $right): int => array_sum($right) <=> array_sum($left),
        );

        $matrix = array_slice($matrix, 0, self::MAX_SERIES, true);

        $datasets = [];
        $colorIndex = 0;

        foreach ($matrix as $label => $pointsByDay) {
            $color = self::PALETTE[$colorIndex % count(self::PALETTE)];
            $colorIndex++;

            $datasets[] = [
                'label' => $label,
                'data' => array_map(fn (string $dayKey): int => (int) ($pointsByDay[$dayKey] ?? 0), $dayKeys),
                'borderColor' => $color,
                'backgroundColor' => $color,
                'borderWidth' => 2,
                'pointRadius' => 2,
                'tension' => 0.2,
                'fill' => false,
            ];
        }

        return $datasets;
    }

    /**
     * @return list<string>
     */
    private function dayKeys(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $keys = [];
        $cursor = $from;

        while ($cursor->lte($until)) {
            $keys[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $keys;
    }
}
