<?php

namespace App\Console\Commands;

use App\Models\Visit;
use App\Services\VisitObservations\VisitObservationMetricsExtractor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class BackfillVisitObservationMetricsCommand extends Command
{
    protected $signature = 'visits:backfill-observation-metrics
                            {--force : Recalcula tambien visitas que ya tienen metricas extraidas}';

    protected $description = 'Extrae nidos retirados y capturas de palomas desde las observaciones de visitas existentes';

    public function handle(VisitObservationMetricsExtractor $extractor): int
    {
        $query = Visit::query();

        if (! $this->option('force')) {
            $query->whereNull('captures_falconry');
        }

        $processed = 0;
        $updated = 0;

        $query->chunkById(200, function (Collection $visits) use ($extractor, &$processed, &$updated): void {
            foreach ($visits as $visit) {
                $processed++;

                $metrics = $extractor->extract($visit->observation);

                $visit->nests_removed = $metrics->nestsRemoved;
                $visit->captures_falconry = $metrics->capturesFalconry;
                $visit->captures_trap = $metrics->capturesTrap;

                if ($visit->isDirty()) {
                    $visit->saveQuietly();
                    $updated++;
                }
            }
        });

        $this->info("Visitas procesadas: {$processed}. Visitas actualizadas: {$updated}.");

        return self::SUCCESS;
    }
}
