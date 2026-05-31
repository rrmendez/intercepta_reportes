<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\BirdType;
use App\Models\Client;
use App\Models\Location;
use App\Models\VisitReport;
use App\Services\VisitSpreadsheet\VisitSpreadsheetQuantityColumns;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class ReportServiceDetailsByLocationBuilder
{
    public function __construct(
        private readonly VisitSpreadsheetQuantityColumns $quantityColumns,
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
     * @param  Collection<int, VisitReport>  $visitReports
     */
    private function formatAbundanceByBirdType(Collection $visitReports): string
    {
        $lines = $visitReports
            ->groupBy('bird_type_id')
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

        if ($lines === []) {
            return '—';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, VisitReport>  $locationReports
     */
    private function captureCount(Collection $locationReports): int
    {
        $total = 0;

        foreach ($locationReports->groupBy('visit_id') as $visitGroup) {
            $text = $this->observationTextForVisitGroup($visitGroup);

            if ($text === '' || ! preg_match('/captur/u', $text)) {
                continue;
            }

            if (preg_match('/(\d+)/u', $text, $matches) === 1) {
                $total += (int) $matches[1];

                continue;
            }

            $total++;
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
            $text = $this->observationTextForVisitGroup($visitGroup);

            if ($text === '') {
                continue;
            }

            if (preg_match('/nido/u', $text) !== 1) {
                continue;
            }

            if (preg_match('/retir/u', $text) === 1 || preg_match('/quita/u', $text) === 1) {
                if (preg_match('/(\d+)/u', $text, $matches) === 1) {
                    $total += (int) $matches[1];

                    continue;
                }

                $total++;
            }
        }

        return $total;
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
