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

    private const int POINT_RADIUS = 2;

    private const int GAP_EDGE_POINT_RADIUS = 4;

    private const string POINT_STYLE = 'circle';

    private const string GAP_EDGE_POINT_STYLE = 'crossRot';

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
     * @param  array<string, array<string, int|null>>  $matrix
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
     * @return array{labels: list<string>, day_keys: list<string>, matrix: array<string, array<string, int|null>>}
     */
    private function buildBirdCountsByVisitDay(Collection $visitReports): array
    {
        /** @var array<string, true> $dayKeySet */
        $dayKeySet = [];
        $accumulator = new ChartSeriesAccumulator;

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

            $accumulator->add($birdName, $dayKey, $report);
        }

        $dayKeys = array_keys($dayKeySet);
        sort($dayKeys);

        $matrix = $accumulator->toMatrix($dayKeys);

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
        $accumulator = new ChartSeriesAccumulator;
        $dayKeySet = array_fill_keys($dayKeys, true);

        foreach ($visitReports as $report) {
            $visitDate = $report->visit?->date_init;

            if ($visitDate === null) {
                continue;
            }

            $dayKey = CarbonImmutable::parse($visitDate)->toDateString();

            if (! array_key_exists($dayKey, $dayKeySet)) {
                continue;
            }

            $accumulator->add($seriesResolver($report), $dayKey, $report);
        }

        $datasets = $this->datasetsFromMatrix($accumulator->toMatrix($dayKeys), $dayKeys);

        return [
            'id' => $id,
            'title' => $title,
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * @param  array<string, array<string, int|null>>  $matrix
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

            $data = array_map(
                fn (string $dayKey): ?int => array_key_exists($dayKey, $pointsByDay)
                    ? $pointsByDay[$dayKey]
                    : 0,
                $dayKeys,
            );

            $datasets[] = [
                'label' => $label,
                'data' => $data,
                'borderColor' => $color,
                'backgroundColor' => $color,
                'borderWidth' => 2,
                'pointRadius' => $this->pointRadii($data),
                'pointStyle' => $this->pointStyles($data),
                'spanGaps' => false,
                'tension' => 0.2,
                'fill' => false,
            ];
        }

        return $datasets;
    }

    /**
     * Los puntos que bordean un corte se agrandan para que el salto se lea como
     * "sin conteo" y no como un problema de renderizado.
     *
     * @param  list<int|null>  $data
     * @return list<int>|int
     */
    private function pointRadii(array $data): array|int
    {
        if (! $this->hasGaps($data)) {
            return self::POINT_RADIUS;
        }

        return array_map(
            fn (int $index): int => $this->isGapEdge($data, $index)
                ? self::GAP_EDGE_POINT_RADIUS
                : self::POINT_RADIUS,
            array_keys($data),
        );
    }

    /**
     * @param  list<int|null>  $data
     * @return list<string>|string
     */
    private function pointStyles(array $data): array|string
    {
        if (! $this->hasGaps($data)) {
            return self::POINT_STYLE;
        }

        return array_map(
            fn (int $index): string => $this->isGapEdge($data, $index)
                ? self::GAP_EDGE_POINT_STYLE
                : self::POINT_STYLE,
            array_keys($data),
        );
    }

    /**
     * @param  list<int|null>  $data
     */
    private function hasGaps(array $data): bool
    {
        return in_array(null, $data, true);
    }

    /**
     * @param  list<int|null>  $data
     */
    private function isGapEdge(array $data, int $index): bool
    {
        if ($data[$index] === null) {
            return false;
        }

        return array_key_exists($index - 1, $data) && $data[$index - 1] === null
            || array_key_exists($index + 1, $data) && $data[$index + 1] === null;
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
