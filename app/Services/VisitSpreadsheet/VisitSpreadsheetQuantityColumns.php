<?php

declare(strict_types=1);

namespace App\Services\VisitSpreadsheet;

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Location;
use App\Models\VisitReport;
use Illuminate\Support\Collection;

final class VisitSpreadsheetQuantityColumns
{
    private const string COMPACT_SINGLE_LABEL = 'Conteo';

    /**
     * @return list<array{key: string, label: string, location_id: int, bird_type_id: int}>
     */
    public function forClient(Client $client): array
    {
        $namedLocations = $this->namedLocationsForSpreadsheetColumns($client);

        $mode = $client->import_mode ?? ClientImportMode::MultiSectorSingleBird;

        // Migración original ponía `single_sector_single_bird` por defecto; en la práctica,
        // varias ubicaciones activas implican columnas por sección (Excel multi-sector / un ave).
        if ($mode === ClientImportMode::SingleSectorSingleBird && $namedLocations->count() > 1) {
            $mode = ClientImportMode::MultiSectorSingleBird;
        }

        $birdTypes = BirdType::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $defaultBirdId = $this->defaultBirdTypeId($birdTypes);

        if ($defaultBirdId === null) {
            return [];
        }

        return match ($mode) {
            ClientImportMode::SingleSectorSingleBird => $this->singleSectorSingleBird($client, $namedLocations, $birdTypes, $defaultBirdId),
            ClientImportMode::SingleSectorMultiBird => $this->singleSectorMultiBird($client, $namedLocations, $birdTypes),
            ClientImportMode::MultiSectorSingleBird => $this->multiSectorSingleBird(
                $this->activeLocationsForSpreadsheetMultiSector($client),
                $defaultBirdId,
            ),
            ClientImportMode::MultiSectorMultiBird => $this->multiSectorMultiBird(
                $client,
                $this->activeLocationsForSpreadsheetMultiSector($client),
                $birdTypes,
            ),
        };
    }

    private function defaultBirdTypeId(Collection $birdTypes): ?int
    {
        $palomas = $birdTypes->first(fn (BirdType $birdType): bool => $birdType->name === 'Palomas');

        return $palomas?->id ?? $birdTypes->first()?->id;
    }

    /**
     * @param  Collection<int, Location>  $namedLocations
     * @param  Collection<int, BirdType>  $birdTypes
     * @return list<array{key: string, label: string, location_id: int, bird_type_id: int}>
     */
    private function singleSectorSingleBird(Client $client, Collection $namedLocations, Collection $birdTypes, int $defaultBirdId): array
    {
        $location = $this->resolveSingleSectorLocation($client, $namedLocations);

        if ($location === null) {
            return [];
        }

        return [[
            'key' => $this->quantityKey($location->id, $defaultBirdId),
            'label' => self::COMPACT_SINGLE_LABEL,
            'location_id' => (int) $location->id,
            'bird_type_id' => $defaultBirdId,
        ]];
    }

    /**
     * @param  Collection<int, Location>  $namedLocations
     * @param  Collection<int, BirdType>  $birdTypes
     * @return list<array{key: string, label: string, location_id: int, bird_type_id: int}>
     */
    private function singleSectorMultiBird(Client $client, Collection $namedLocations, Collection $birdTypes): array
    {
        $location = $this->resolveSingleSectorLocation($client, $namedLocations);

        if ($location === null) {
            return [];
        }

        $specs = [];

        foreach ($birdTypes as $birdType) {
            $specs[] = [
                'key' => $this->quantityKey($location->id, (int) $birdType->id),
                'label' => (string) $birdType->name,
                'location_id' => (int) $location->id,
                'bird_type_id' => (int) $birdType->id,
            ];
        }

        return $specs;
    }

    /**
     * @param  Collection<int, Location>  $namedLocations
     * @return list<array{key: string, label: string, location_id: int, bird_type_id: int}>
     */
    private function multiSectorSingleBird(Collection $namedLocations, int $defaultBirdId): array
    {
        $specs = [];

        foreach ($namedLocations as $location) {
            $specs[] = [
                'key' => $this->quantityKey((int) $location->id, $defaultBirdId),
                'label' => (string) $location->name,
                'location_id' => (int) $location->id,
                'bird_type_id' => $defaultBirdId,
            ];
        }

        return $specs;
    }

    /**
     * @param  Collection<int, Location>  $namedLocations
     * @param  Collection<int, BirdType>  $birdTypes
     * @return list<array{key: string, label: string, location_id: int, bird_type_id: int}>
     */
    private function multiSectorMultiBird(Client $client, Collection $namedLocations, Collection $birdTypes): array
    {
        $pairs = VisitReport::query()
            ->whereHas('visit', fn ($query) => $query->where('client_id', $client->id))
            ->select(['location_id', 'bird_type_id'])
            ->distinct()
            ->get();

        if ($pairs->isEmpty()) {
            return $this->cartesianSpecs($namedLocations, $birdTypes);
        }

        $locationsById = $client->locations()->whereIn('id', $pairs->pluck('location_id'))->get()->keyBy('id');
        $birdsById = BirdType::query()->whereIn('id', $pairs->pluck('bird_type_id'))->get()->keyBy('id');

        $specs = [];

        foreach ($pairs as $pair) {
            $locationId = (int) $pair->location_id;
            $birdTypeId = (int) $pair->bird_type_id;
            $locationName = $locationsById->get($locationId)?->name ?? (string) $locationId;
            $birdName = $birdsById->get($birdTypeId)?->name ?? (string) $birdTypeId;
            $specs[] = [
                'key' => $this->quantityKey($locationId, $birdTypeId),
                'label' => $this->compositeBirdInLocationLabel($birdName, $locationName),
                'location_id' => $locationId,
                'bird_type_id' => $birdTypeId,
            ];
        }

        usort($specs, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $specs;
    }

    private function compositeBirdInLocationLabel(string $birdName, string $locationName): string
    {
        return "{$birdName} en {$locationName}";
    }

    /**
     * Modos multi-sector: columnas alineadas con todas las ubicaciones activas (como columnas
     * del Excel), sin excluir la fila cuyo nombre coincide con la empresa — esa exclusión
     * aplica a listados de administración, no a la planilla de conteos por sección.
     *
     * @return Collection<int, Location>
     */
    private function activeLocationsForSpreadsheetMultiSector(Client $client): Collection
    {
        return $client->locations()
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Ubicaciones “de planilla”: mismas que en administración; si ninguna entra en
     * {@see Client::namedLocations()} (p. ej. nombres iguales al de la empresa), se usan
     * todas las ubicaciones activas para no perder columnas respecto al Excel.
     *
     * @return Collection<int, Location>
     */
    private function namedLocationsForSpreadsheetColumns(Client $client): Collection
    {
        $named = $client->namedLocations()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        if ($named->isNotEmpty()) {
            return $named;
        }

        return $client->locations()
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Location>  $namedLocations
     * @param  Collection<int, BirdType>  $birdTypes
     * @return list<array{key: string, label: string, location_id: int, bird_type_id: int}>
     */
    private function cartesianSpecs(Collection $namedLocations, Collection $birdTypes): array
    {
        $specs = [];

        foreach ($namedLocations as $location) {
            foreach ($birdTypes as $birdType) {
                $specs[] = [
                    'key' => $this->quantityKey((int) $location->id, (int) $birdType->id),
                    'label' => $this->compositeBirdInLocationLabel((string) $birdType->name, (string) $location->name),
                    'location_id' => (int) $location->id,
                    'bird_type_id' => (int) $birdType->id,
                ];
            }
        }

        return $specs;
    }

    /**
     * @param  Collection<int, Location>  $namedLocations
     */
    private function resolveSingleSectorLocation(Client $client, Collection $namedLocations): ?Location
    {
        $active = $client->locations()->where('active', true)->orderBy('name')->get();

        if ($active->count() === 1) {
            return $active->first();
        }

        return $namedLocations->first();
    }

    private function quantityKey(int $locationId, int $birdTypeId): string
    {
        return "qty_{$locationId}_{$birdTypeId}";
    }
}
