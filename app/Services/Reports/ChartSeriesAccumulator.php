<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\VisitReport;

/**
 * Acumula conteos por serie y por dia distinguiendo tres situaciones:
 * un conteo real (incluido el cero), un dia sin conteo posible (local cerrado)
 * y un dia sin registros para esa serie.
 */
final class ChartSeriesAccumulator
{
    /**
     * @var array<string, array<string, int>>
     */
    private array $sums = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $visitedDays = [];

    public function add(string $series, string $dayKey, VisitReport $report): void
    {
        $this->visitedDays[$series][$dayKey] = true;

        if (! $report->wasCounted()) {
            return;
        }

        $this->sums[$series][$dayKey] = ($this->sums[$series][$dayKey] ?? 0) + (int) $report->quantity;
    }

    /**
     * Devuelve `null` en los dias visitados sin ningun conteo posible, para que
     * el grafico dibuje un corte en lugar de una caida a cero.
     *
     * @param  list<string>  $dayKeys
     * @return array<string, array<string, int|null>>
     */
    public function toMatrix(array $dayKeys): array
    {
        $matrix = [];

        foreach ($this->visitedDays as $series => $daysVisited) {
            foreach ($dayKeys as $dayKey) {
                $matrix[$series][$dayKey] = match (true) {
                    isset($this->sums[$series][$dayKey]) => $this->sums[$series][$dayKey],
                    isset($daysVisited[$dayKey]) => null,
                    default => 0,
                };
            }
        }

        return $matrix;
    }
}
