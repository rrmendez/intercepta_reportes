<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\BirdType;
use App\Models\VisitReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class ReportCurrentSituationAndConclusionsBuilder
{
    /**
     * @param  Collection<int, VisitReport>  $periodVisitReports
     * @param  Collection<int, VisitReport>  $historicalVisitReports
     * @return list<array{nombre_comun: string, descripcion: string, poblacion_inicial: int}>
     */
    public function initialPopulationEntries(Collection $periodVisitReports, Collection $historicalVisitReports): array
    {
        if ($periodVisitReports instanceof EloquentCollection && $periodVisitReports->isNotEmpty()) {
            $periodVisitReports->loadMissing(['visit', 'birdType']);
        }

        if ($historicalVisitReports instanceof EloquentCollection && $historicalVisitReports->isNotEmpty()) {
            $historicalVisitReports->loadMissing(['visit', 'birdType']);
        }

        $baselineReports = $this->baselineReports($periodVisitReports, $historicalVisitReports);

        if ($baselineReports->isEmpty()) {
            return [
                [
                    'nombre_comun' => 'Paloma doméstica',
                    'descripcion' => 'Columba livia',
                    'poblacion_inicial' => 0,
                ],
            ];
        }

        return $this->quantitiesByBirdType($baselineReports)
            ->map(fn (array $entry): array => [
                ...$this->birdTypeParts($entry['bird_type']),
                'poblacion_inicial' => $entry['quantity'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, VisitReport>  $periodVisitReports
     * @param  Collection<int, VisitReport>  $historicalVisitReports
     * @return array{
     *     population_entries: list<array{quantity: int, name: string, scientific_name: string}>,
     *     reduction_percentage: string|null,
     *     falconry_captures: list<array{quantity: int, name: string, scientific_name: string}>,
     *     trap_captures: list<array{quantity: int, name: string, scientific_name: string}>,
     *     nests_removed: int
     * }
     */
    public function build(Collection $periodVisitReports, Collection $historicalVisitReports): array
    {
        if ($periodVisitReports instanceof EloquentCollection && $periodVisitReports->isNotEmpty()) {
            $periodVisitReports->loadMissing(['visit', 'birdType']);
        }

        if ($historicalVisitReports instanceof EloquentCollection && $historicalVisitReports->isNotEmpty()) {
            $historicalVisitReports->loadMissing(['visit', 'birdType']);
        }

        $baselineReports = $this->baselineReports($periodVisitReports, $historicalVisitReports);
        $birdTypes = $this->birdTypesFromReports($periodVisitReports, $historicalVisitReports);
        $initialQuantity = $this->totalQuantity($baselineReports);
        $periodQuantity = $this->totalQuantity($periodVisitReports);

        return [
            'population_entries' => $this->populationEntries($periodVisitReports, $historicalVisitReports),
            'reduction_percentage' => $this->reductionPercentage($initialQuantity, $periodQuantity),
            'falconry_captures' => $this->captureEntries($periodVisitReports, $birdTypes, falconry: true),
            'trap_captures' => $this->captureEntries($periodVisitReports, $birdTypes, falconry: false),
            'nests_removed' => $this->nestsRemovedCount($periodVisitReports),
        ];
    }

    /**
     * @param  Collection<int, VisitReport>  $periodVisitReports
     * @param  Collection<int, VisitReport>  $historicalVisitReports
     * @return Collection<int, VisitReport>
     */
    private function baselineReports(Collection $periodVisitReports, Collection $historicalVisitReports): Collection
    {
        $combined = $historicalVisitReports->isNotEmpty()
            ? $historicalVisitReports
            : $periodVisitReports;

        return $this->firstVisitDayReports($combined);
    }

    /**
     * @param  Collection<int, VisitReport>  $visitReports
     * @return Collection<int, VisitReport>
     */
    private function firstVisitDayReports(Collection $visitReports): Collection
    {
        $dayKey = $this->sortedVisitDayKeys($visitReports)[0] ?? null;

        if ($dayKey === null) {
            return collect();
        }

        return $this->reportsForDay($visitReports, $dayKey);
    }

    /**
     * @param  Collection<int, VisitReport>  $visitReports
     * @return list<string>
     */
    private function sortedVisitDayKeys(Collection $visitReports): array
    {
        $dayKeys = $visitReports
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
     * @param  Collection<int, VisitReport>  $visitReports
     * @return Collection<int, VisitReport>
     */
    private function reportsForDay(Collection $visitReports, string $dayKey): Collection
    {
        return $visitReports
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
    private function totalQuantity(Collection $visitReports): int
    {
        return (int) $visitReports->sum('quantity');
    }

    /**
     * @param  Collection<int, VisitReport>  $periodVisitReports
     * @param  Collection<int, VisitReport>  $historicalVisitReports
     * @return list<array{quantity: int, name: string, scientific_name: string}>
     */
    private function populationEntries(Collection $periodVisitReports, Collection $historicalVisitReports): array
    {
        $birdTypes = $this->birdTypesFromReports($periodVisitReports, $historicalVisitReports);

        if ($birdTypes->isEmpty()) {
            return [];
        }

        $quantitiesByBirdType = $this->quantitiesByBirdType($periodVisitReports)
            ->keyBy(fn (array $entry): int => (int) $entry['bird_type']->getKey());

        return $birdTypes
            ->map(function (BirdType $birdType) use ($quantitiesByBirdType): array {
                $quantityEntry = $quantitiesByBirdType->get((int) $birdType->getKey());
                $quantity = (int) ($quantityEntry['quantity'] ?? 0);

                return [
                    'quantity' => $quantity,
                    'name' => $this->birdNameForQuantity($birdType, $quantity),
                    'scientific_name' => $this->birdTypeParts($birdType)['descripcion'],
                ];
            })
            ->values()
            ->all();
    }

    private function reductionPercentage(int $initialQuantity, int $periodQuantity): ?string
    {
        if ($initialQuantity <= 0) {
            return null;
        }

        $reduction = (($initialQuantity - $periodQuantity) / $initialQuantity) * 100;

        return number_format(max(0, $reduction), 2, '.', '');
    }

    /**
     * @param  Collection<int, VisitReport>  $periodVisitReports
     * @param  Collection<int, BirdType>  $birdTypes
     * @return list<array{quantity: int, name: string, scientific_name: string}>
     */
    private function captureEntries(Collection $periodVisitReports, Collection $birdTypes, bool $falconry): array
    {
        if ($birdTypes->isEmpty()) {
            return [];
        }

        return $birdTypes
            ->map(function (BirdType $birdType) use ($periodVisitReports, $falconry): array {
                $count = $this->captureCountForBirdType($periodVisitReports, $birdType, $falconry);

                return [
                    'quantity' => $count,
                    'name' => $this->birdNameForQuantity($birdType, $count),
                    'scientific_name' => trim((string) ($birdType->scientific_name ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, VisitReport>  $periodVisitReports
     */
    private function captureCountForBirdType(Collection $periodVisitReports, BirdType $birdType, bool $falconry): int
    {
        $total = 0;

        foreach ($periodVisitReports->where('bird_type_id', $birdType->getKey())->groupBy('visit_id') as $visitGroup) {
            $text = $this->observationTextForVisitGroup($visitGroup);

            if ($text === '' || preg_match('/captur/u', $text) !== 1) {
                continue;
            }

            $isTrapCapture = preg_match('/trampa/u', $text) === 1;

            if ($falconry && $isTrapCapture) {
                continue;
            }

            if (! $falconry && ! $isTrapCapture) {
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
     * @param  Collection<int, VisitReport>  $periodVisitReports
     */
    private function nestsRemovedCount(Collection $periodVisitReports): int
    {
        $total = 0;

        foreach ($periodVisitReports->groupBy('visit_id') as $visitGroup) {
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
     * @param  Collection<int, VisitReport>  $periodVisitReports
     */
    /**
     * @param  Collection<int, VisitReport>  $periodVisitReports
     * @param  Collection<int, VisitReport>  $historicalVisitReports
     * @return Collection<int, BirdType>
     */
    private function birdTypesFromReports(Collection $periodVisitReports, Collection $historicalVisitReports): Collection
    {
        return $periodVisitReports
            ->merge($historicalVisitReports)
            ->map(fn (VisitReport $report): ?BirdType => $report->birdType)
            ->filter()
            ->unique(fn (BirdType $birdType): int => (int) $birdType->getKey())
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  Collection<int, VisitReport>  $visitReports
     * @return Collection<int, array{bird_type: BirdType, quantity: int}>
     */
    private function quantitiesByBirdType(Collection $visitReports): Collection
    {
        return $visitReports
            ->groupBy('bird_type_id')
            ->sortKeys()
            ->map(function (Collection $group): ?array {
                $birdType = $group->first()?->birdType;

                if ($birdType === null) {
                    return null;
                }

                return [
                    'bird_type' => $birdType,
                    'quantity' => (int) $group->sum('quantity'),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{nombre_comun: string, descripcion: string}
     */
    private function birdTypeParts(BirdType $birdType): array
    {
        return [
            'nombre_comun' => trim((string) $birdType->common_name),
            'descripcion' => trim((string) ($birdType->scientific_name ?? '')),
        ];
    }

    private function birdTypeLabel(BirdType $birdType): string
    {
        return $birdType->labelWithScientific();
    }

    private function birdNameForQuantity(BirdType $birdType, int $quantity): string
    {
        return $birdType->labelForPdf($quantity);
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
