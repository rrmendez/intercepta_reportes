<?php

declare(strict_types=1);

namespace App\Services\VisitObservations;

use App\Models\VisitReport;
use Illuminate\Support\Collection;

final class VisitReportGroupMetricsResolver
{
    public function __construct(
        private readonly VisitObservationMetricsExtractor $extractor,
    ) {}

    /**
     * Prefiere las métricas persistidas en la visita. Cae al extractor sobre el texto
     * de observación cuando la visita nunca fue extraída (campos null) o cuando su
     * observación está vacía y las métricas guardadas son todas cero — en ese caso el
     * texto relevante puede vivir en la observación del VisitReport, que el hook de
     * extracción de la visita no ve.
     *
     * @param  Collection<int, VisitReport>  $visitGroup
     */
    public function resolve(Collection $visitGroup): VisitObservationMetrics
    {
        $visit = $visitGroup->first()?->visit;
        $visitObservation = trim((string) ($visit?->observation ?? ''));

        if ($visit !== null && $visit->captures_falconry !== null) {
            $hasStoredCounts = (int) $visit->nests_removed > 0
                || (int) $visit->captures_falconry > 0
                || (int) $visit->captures_trap > 0;

            if ($visitObservation !== '' || $hasStoredCounts) {
                return new VisitObservationMetrics(
                    nestsRemoved: (int) $visit->nests_removed,
                    capturesFalconry: (int) $visit->captures_falconry,
                    capturesTrap: (int) $visit->captures_trap,
                );
            }
        }

        return $this->extractor->extract($this->observationTextForVisitGroup($visitGroup));
    }

    /**
     * @param  Collection<int, VisitReport>  $visitGroup
     */
    private function observationTextForVisitGroup(Collection $visitGroup): string
    {
        $visitObservation = trim((string) ($visitGroup->first()?->visit?->observation ?? ''));

        if ($visitObservation !== '') {
            return $visitObservation;
        }

        return trim((string) ($visitGroup->first()?->observation ?? ''));
    }
}
