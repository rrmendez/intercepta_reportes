<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\BirdType;
use App\Models\Client;
use App\Models\Location;
use App\Models\VisitReport;
use App\Services\VisitObservations\VisitReportGroupMetricsResolver;
use App\Services\VisitSpreadsheet\VisitSpreadsheetQuantityColumns;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class ReportServiceDetailsByLocationBuilder
{
    private const string NOT_PERFORMED_LABEL = 'Sin conteo (local cerrado o sin acceso)';

    public function __construct(
        private readonly VisitSpreadsheetQuantityColumns $quantityColumns,
        private readonly VisitReportGroupMetricsResolver $observationMetricsResolver,
    ) {}

    /**
     * @param  Collection<int, VisitReport>  $visitReports
     * @return array{sections: list<array{
     *     location_id: int,
     *     title: string,
     *     capturas: int,
     *     nidos_retirados: int,
     *     abundancia: string
     * }>}
     */
    public function build(Client $client, Collection $visitReports): array
    {
        if ($visitReports instanceof EloquentCollection && $visitReports->isNotEmpty()) {
            $visitReports->loadMissing(['visit', 'location', 'birdType']);
        }

        $locations = $this->locationsForClient($client, $visitReports);

        if ($locations->isEmpty()) {
            return ['sections' => []];
        }

        $sections = $locations
            ->map(function (Location $location) use ($visitReports): array {
                $locationReports = $visitReports->where('location_id', $location->getKey());

                return [
                    'location_id' => (int) $location->getKey(),
                    'title' => $this->sectionTitle($location),
                    'capturas' => $this->captureCount($locationReports),
                    'nidos_retirados' => $this->nestsRemovedCount($locationReports),
                    'abundancia' => $this->abundanceFromLastVisit($locationReports),
                ];
            })
            ->values()
            ->all();

        return ['sections' => $sections];
    }

    /**
     * @param  Collection<int, VisitReport>  $visitReports
     * @return Collection<int, Location>
     */
    private function locationsForClient(Client $client, Collection $visitReports): Collection
    {
        $columnSpecs = $this->quantityColumns->forClient($client);
        $locationIds = collect($columnSpecs)
            ->pluck('location_id')
            ->unique()
            ->values();

        if ($locationIds->isEmpty()) {
            return $visitReports
                ->map(fn (VisitReport $report): ?Location => $report->location)
                ->filter()
                ->unique(fn (Location $location): int => (int) $location->getKey())
                ->sortBy('name')
                ->values();
        }

        $locationsById = $visitReports
            ->map(fn (VisitReport $report): ?Location => $report->location)
            ->filter()
            ->keyBy(fn (Location $location): int => (int) $location->getKey());

        $clientId = $client->getKey();

        if (is_int($clientId)) {
            $fromDatabase = Location::query()
                ->where('client_id', $clientId)
                ->whereIn('id', $locationIds)
                ->orderBy('name')
                ->get()
                ->keyBy(fn (Location $location): int => (int) $location->getKey());

            $locationsById = $locationsById->union($fromDatabase);
        }

        return $locationIds
            ->map(fn (int $locationId): ?Location => $locationsById->get($locationId))
            ->filter()
            ->values();
    }

    private function sectionTitle(Location $location): string
    {
        return (string) $location->name;
    }

    /**
     * @param  Collection<int, VisitReport>  $locationReports
     */
    private function abundanceFromLastVisit(Collection $locationReports): string
    {
        $lastVisitDayReports = $this->lastVisitDayReports($locationReports);

        if ($lastVisitDayReports->isEmpty()) {
            return '—';
        }

        return $this->formatAbundanceByBirdType($lastVisitDayReports);
    }

    /**
     * @param  Collection<int, VisitReport>  $locationReports
     * @return Collection<int, VisitReport>
     */
    private function lastVisitDayReports(Collection $locationReports): Collection
    {
        $dayKeys = $this->sortedVisitDayKeys($locationReports);
        $lastDayKey = $dayKeys[array_key_last($dayKeys)] ?? null;

        if ($lastDayKey === null) {
            return collect();
        }

        return $this->reportsForDay($locationReports, $lastDayKey);
    }

    /**
     * @param  Collection<int, VisitReport>  $locationReports
     * @return list<string>
     */
    private function sortedVisitDayKeys(Collection $locationReports): array
    {
        $dayKeys = $locationReports
            ->map(function (VisitReport $report): ?string {
                $visitDate = $report->visit?->date_init;

                if ($visitDate === null) {
                    return null;
                }

                return CarbonImmutable::parse($visitDate)->toDateString();
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        sort($dayKeys);

        return $dayKeys;
    }

    /**
     * @param  Collection<int, VisitReport>  $locationReports
     * @return Collection<int, VisitReport>
     */
    private function reportsForDay(Collection $locationReports, string $dayKey): Collection
    {
        return $locationReports
            ->filter(function (VisitReport $report) use ($dayKey): bool {
                $visitDate = $report->visit?->date_init;

                if ($visitDate === null) {
                    return false;
                }

                return CarbonImmutable::parse($visitDate)->toDateString() === $dayKey;
            })
            ->values();
    }

    /**
     * Los conteos que no pudieron realizarse no se informan como cero: se listan
     * aparte para no confundirlos con una ausencia real de aves.
     *
     * @param  Collection<int, VisitReport>  $visitReports
     */
    private function formatAbundanceByBirdType(Collection $visitReports): string
    {
        $countedByBirdType = $visitReports
            ->filter(fn (VisitReport $report): bool => $report->wasCounted())
            ->groupBy('bird_type_id');

        if ($countedByBirdType->isEmpty()) {
            return $visitReports->isEmpty() ? '—' : self::NOT_PERFORMED_LABEL;
        }

        $lines = $countedByBirdType
            ->sortKeys()
            ->map(function (Collection $group): string {
                $birdType = $group->first()?->birdType;
                $quantity = (int) $group->sum('quantity');

                if (! $birdType instanceof BirdType) {
                    return "{$quantity} —";
                }

                $label = $birdType->labelForPdf($quantity);
                $scientificName = trim((string) ($birdType->scientific_name ?? ''));

                if ($scientificName !== '') {
                    return "{$quantity} {$label} ({$scientificName})";
                }

                return "{$quantity} {$label}";
            })
            ->values()
            ->all();

        $countedBirdTypeIds = $countedByBirdType->keys()->map(fn (mixed $id): int => (int) $id)->all();
        $skippedNames = $this->skippedBirdTypeNames($visitReports, $countedBirdTypeIds);

        if ($skippedNames !== []) {
            $lines[] = self::NOT_PERFORMED_LABEL.': '.implode(', ', $skippedNames);
        }

        if ($lines === []) {
            return '—';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, VisitReport>  $visitReports
     * @param  list<int>  $countedBirdTypeIds
     * @return list<string>
     */
    private function skippedBirdTypeNames(Collection $visitReports, array $countedBirdTypeIds): array
    {
        return $visitReports
            ->filter(fn (VisitReport $report): bool => ! $report->wasCounted())
            ->reject(fn (VisitReport $report): bool => in_array((int) $report->bird_type_id, $countedBirdTypeIds, true))
            ->groupBy('bird_type_id')
            ->sortKeys()
            ->map(fn (Collection $group): string => (string) ($group->first()?->birdType?->name ?? 'Sin tipo'))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, VisitReport>  $locationReports
     */
    private function captureCount(Collection $locationReports): int
    {
        $total = 0;

        foreach ($locationReports->groupBy('visit_id') as $visitGroup) {
            $metrics = $this->observationMetricsResolver->resolve($visitGroup);

            $total += $metrics->capturesFalconry + $metrics->capturesTrap;
        }

        return $total;
    }

    /**
     * @param  Collection<int, VisitReport>  $locationReports
     */
    private function nestsRemovedCount(Collection $locationReports): int
    {
        $total = 0;

        foreach ($locationReports->groupBy('visit_id') as $visitGroup) {
            $total += $this->observationMetricsResolver->resolve($visitGroup)->nestsRemoved;
        }

        return $total;
    }
}
