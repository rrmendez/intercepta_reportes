<?php

declare(strict_types=1);

namespace App\Services\HistoricVisitImport;

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Location;
use App\Services\BirdTypes\BirdTypeResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

final class HistoricVisitColumnResolver
{
    public function __construct(
        private readonly BirdTypeResolver $birdTypeResolver,
    ) {}

    /**
     * @param  array<int, int>  $columnIndices
     * @param  array<int, string|null>  $columnHeaders
     * @return list<array{column_index: int, location_name: string, bird_type_name: string, header: ?string}>
     */
    public function resolve(Client $client, array $columnIndices, array $columnHeaders): array
    {
        $mode = $this->effectiveImportMode($client);

        return match ($mode) {
            ClientImportMode::SingleSectorSingleBird => $this->resolveSingleSectorSingleBird($client, $columnIndices, $columnHeaders),
            ClientImportMode::SingleSectorMultiBird => $this->resolveSingleSectorMultiBird($client, $columnIndices, $columnHeaders),
            ClientImportMode::MultiSectorSingleBird => $this->resolveMultiSectorSingleBird($client, $columnIndices, $columnHeaders),
            ClientImportMode::MultiSectorMultiBird => $this->resolveMultiSectorMultiBird($client, $columnIndices, $columnHeaders),
        };
    }

    private function effectiveImportMode(Client $client): ClientImportMode
    {
        $mode = $client->import_mode ?? ClientImportMode::MultiSectorSingleBird;

        $activeLocationCount = $client->locations()->where('active', true)->count();

        if ($mode === ClientImportMode::SingleSectorSingleBird && $activeLocationCount > 1) {
            return ClientImportMode::MultiSectorSingleBird;
        }

        return $mode;
    }

    /**
     * @param  array<int, int>  $columnIndices
     * @param  array<int, string|null>  $columnHeaders
     * @return list<array{column_index: int, location_name: string, bird_type_name: string, header: ?string}>
     */
    private function resolveSingleSectorSingleBird(Client $client, array $columnIndices, array $columnHeaders): array
    {
        $location = $this->requireSingleActiveLocation($client);
        $birdTypeName = $this->birdTypeResolver->default()->name;

        if ($columnIndices === []) {
            throw new RuntimeException(
                'El archivo no contiene columnas de cantidad para el cliente "'.$client->name.'".',
            );
        }

        $columnIndex = $columnIndices[0];
        $header = $this->headerForColumn($columnHeaders, $columnIndex);

        if (is_string($header) && $header !== '') {
            $birdType = $this->birdTypeResolver->resolve($header);

            if ($birdType instanceof BirdType) {
                $birdTypeName = $birdType->name;
            }
        }

        return [[
            'column_index' => $columnIndex,
            'location_name' => $location->name,
            'bird_type_name' => $birdTypeName,
            'header' => $header,
        ]];
    }

    /**
     * @param  array<int, int>  $columnIndices
     * @param  array<int, string|null>  $columnHeaders
     * @return list<array{column_index: int, location_name: string, bird_type_name: string, header: ?string}>
     */
    private function resolveSingleSectorMultiBird(Client $client, array $columnIndices, array $columnHeaders): array
    {
        $location = $this->requireSingleActiveLocation($client);
        $mappings = [];

        foreach ($columnIndices as $columnIndex) {
            $header = $this->requireHeaderLabel($columnHeaders, $columnIndex, $client->name);
            $birdType = $this->birdTypeResolver->resolve($header);

            if (! $birdType instanceof BirdType) {
                throw new RuntimeException(
                    'No se reconoce el tipo de ave "'.$header.'" en el archivo del cliente "'.$client->name.'".',
                );
            }

            $mappings[] = [
                'column_index' => $columnIndex,
                'location_name' => $location->name,
                'bird_type_name' => $birdType->name,
                'header' => $header,
            ];
        }

        return $mappings;
    }

    /**
     * @param  array<int, int>  $columnIndices
     * @param  array<int, string|null>  $columnHeaders
     * @return list<array{column_index: int, location_name: string, bird_type_name: string, header: ?string}>
     */
    private function resolveMultiSectorSingleBird(Client $client, array $columnIndices, array $columnHeaders): array
    {
        $sections = $client->locations()
            ->where('active', true)
            ->orderBy('id')
            ->get(['name']);

        if ($sections->isEmpty()) {
            throw new RuntimeException(
                'El cliente "'.$client->name.'" no tiene secciones activas configuradas.',
            );
        }

        if (count($columnIndices) > $sections->count()) {
            throw new RuntimeException(
                'El archivo tiene '.count($columnIndices).' columnas de cantidad pero el cliente "'.$client->name.'" solo tiene '.$sections->count().' seccion(es) activa(s).',
            );
        }

        $birdTypeName = $this->birdTypeResolver->default()->name;
        $mappings = [];

        foreach ($columnIndices as $position => $columnIndex) {
            $header = $this->headerForColumn($columnHeaders, $columnIndex);
            $locationName = $this->resolveSectionNameFromHeader($sections, $header, $position);

            $mappings[] = [
                'column_index' => $columnIndex,
                'location_name' => $locationName,
                'bird_type_name' => $birdTypeName,
                'header' => $header,
            ];
        }

        return $mappings;
    }

    /**
     * @param  array<int, int>  $columnIndices
     * @param  array<int, string|null>  $columnHeaders
     * @return list<array{column_index: int, location_name: string, bird_type_name: string, header: ?string}>
     */
    private function resolveMultiSectorMultiBird(Client $client, array $columnIndices, array $columnHeaders): array
    {
        $sectionsByToken = $client->locations()
            ->where('active', true)
            ->orderBy('id')
            ->get(['name'])
            ->mapWithKeys(fn (Location $location): array => [
                $this->normalizeToken($location->name) => $location->name,
            ])
            ->all();

        if ($sectionsByToken === []) {
            throw new RuntimeException(
                'El cliente "'.$client->name.'" no tiene secciones activas configuradas.',
            );
        }

        $mappings = [];

        foreach ($columnIndices as $columnIndex) {
            $header = $this->requireHeaderLabel($columnHeaders, $columnIndex, $client->name);
            $composite = $this->birdTypeResolver->matchCompositePrefix($header);

            if ($composite === null) {
                throw new RuntimeException(
                    'No se pudo interpretar la columna "'.$header.'" del cliente "'.$client->name.'". Se esperaba un encabezado compuesto (por ejemplo, "Palomas interior").',
                );
            }

            [$birdType, $locationFragment] = $composite;
            $locationToken = $this->normalizeToken($locationFragment);
            $locationName = $sectionsByToken[$locationToken] ?? null;

            if (! is_string($locationName)) {
                throw new RuntimeException(
                    'La columna "'.$header.'" referencia la seccion "'.$locationFragment.'" pero el cliente "'.$client->name.'" no tiene una seccion activa con ese nombre.',
                );
            }

            $mappings[] = [
                'column_index' => $columnIndex,
                'location_name' => $locationName,
                'bird_type_name' => $birdType->name,
                'header' => $header,
            ];
        }

        return $mappings;
    }

    private function requireSingleActiveLocation(Client $client): Location
    {
        $locations = $client->locations()
            ->where('active', true)
            ->orderBy('id')
            ->get();

        if ($locations->isEmpty()) {
            throw new RuntimeException(
                'El cliente "'.$client->name.'" no tiene secciones activas configuradas.',
            );
        }

        $location = $locations->first();

        if (! $location instanceof Location) {
            throw new RuntimeException(
                'El cliente "'.$client->name.'" no tiene secciones activas configuradas.',
            );
        }

        return $location;
    }

    /**
     * @param  array<int, string|null>  $columnHeaders
     */
    private function headerForColumn(array $columnHeaders, int $columnIndex): ?string
    {
        $header = $columnHeaders[$columnIndex] ?? null;

        if (! is_string($header)) {
            return null;
        }

        $trimmed = trim($header);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<int, string|null>  $columnHeaders
     */
    private function requireHeaderLabel(array $columnHeaders, int $columnIndex, string $clientName): string
    {
        $header = $this->headerForColumn($columnHeaders, $columnIndex);

        if ($header === null) {
            throw new RuntimeException(
                'La columna '.$columnIndex.' del archivo del cliente "'.$clientName.'" no tiene encabezado de ave o seccion.',
            );
        }

        return $header;
    }

    /**
     * @param  Collection<int, Location>  $sections
     */
    private function resolveSectionNameFromHeader($sections, ?string $header, int $position): string
    {
        if (is_string($header) && $header !== '') {
            $headerToken = $this->normalizeToken($header);

            foreach ($sections as $section) {
                if ($this->normalizeToken($section->name) === $headerToken) {
                    return $section->name;
                }
            }
        }

        $section = $sections->get($position);

        if (! $section instanceof Location) {
            throw new RuntimeException('No hay una seccion configurada para la columna '.($position + 1).'.');
        }

        return $section->name;
    }

    private function normalizeToken(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }
}
