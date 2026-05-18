<?php

namespace App\Actions\VisitImport;

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Location;
use App\Services\VisitImport\Validation\VisitImportStructureValidator;
use App\Services\VisitImport\VisitImportPayload;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class NormalizeCompactVisitPayloadAction
{
    private const string COMPACT_SINGLE_LOCATION_COLUMN = 'conteo';

    private const string DEFAULT_BIRD_TYPE_NAME = 'Palomas';

    private const string DEFAULT_STATUS = 'completed';

    /**
     * @param  array<int, string>  $quantityColumns
     */
    public function __invoke(
        Client $client,
        VisitImportPayload $payload,
        string $entryColumn,
        array $quantityColumns,
        bool $provision = false,
    ): CompactNormalizationResult {
        $inferredMode = $this->inferModeFromColumns($client, $quantityColumns, $provision);
        $warnings = [];

        return match ($inferredMode) {
            ClientImportMode::SingleSectorSingleBird => $this->expandSingleSectorSingleBird(
                $client,
                $payload,
                $entryColumn,
                $quantityColumns,
                $warnings,
            ),
            ClientImportMode::SingleSectorMultiBird => $this->expandSingleSectorMultiBird(
                $client,
                $payload,
                $entryColumn,
                $quantityColumns,
                $warnings,
            ),
            ClientImportMode::MultiSectorSingleBird => $this->expandMultiSectorSingleBird(
                $client,
                $payload,
                $entryColumn,
                $quantityColumns,
                $warnings,
            ),
            ClientImportMode::MultiSectorMultiBird => $this->expandMultiSectorMultiBird(
                $client,
                $payload,
                $entryColumn,
                $quantityColumns,
                $warnings,
            ),
        };
    }

    /**
     * @param  array<int, string>  $quantityColumns
     */
    public function inferImportMode(Client $client, array $quantityColumns, bool $provision): ClientImportMode
    {
        return $this->inferModeFromColumns($client, $quantityColumns, $provision);
    }

    /**
     * @param  array<int, string>  $quantityColumns
     */
    private function inferModeFromColumns(Client $client, array $quantityColumns, bool $provision): ClientImportMode
    {
        if ($quantityColumns === []) {
            throw ValidationException::withMessages([
                'file' => ['No hay columnas de cantidad entre Salida y Observaciones.'],
            ]);
        }

        if (count($quantityColumns) === 1 && $quantityColumns[0] === self::COMPACT_SINGLE_LOCATION_COLUMN) {
            return ClientImportMode::SingleSectorSingleBird;
        }

        $birdByToken = $this->birdTypeTokenToName();
        $locationByToken = $this->namedLocationTokenToNameMapForInference($client, $provision);

        $allLocationColumns = $locationByToken !== [] && collect($quantityColumns)
            ->every(fn (string $column): bool => array_key_exists($this->normalizeLocationToken($column), $locationByToken));

        if ($allLocationColumns) {
            return ClientImportMode::MultiSectorSingleBird;
        }

        $allBirdColumns = collect($quantityColumns)
            ->every(fn (string $column): bool => array_key_exists($this->normalizeLocationToken($column), $birdByToken));

        $anyKnownLocation = $locationByToken !== [] && collect($quantityColumns)
            ->contains(fn (string $column): bool => array_key_exists($this->normalizeLocationToken($column), $locationByToken));

        if ($anyKnownLocation && ! $allBirdColumns) {
            return ClientImportMode::MultiSectorSingleBird;
        }

        if ($allBirdColumns) {
            return ClientImportMode::SingleSectorMultiBird;
        }

        $compositeResolved = collect($quantityColumns)
            ->map(fn (string $column): ?array => $this->resolveCompositeColumn($column, $birdByToken, $locationByToken))
            ->all();

        if (! in_array(null, $compositeResolved, true)) {
            return ClientImportMode::MultiSectorMultiBird;
        }

        if ($provision && $locationByToken === [] && ! $allBirdColumns) {
            return ClientImportMode::MultiSectorSingleBird;
        }

        $anyKnownBirdColumn = collect($quantityColumns)
            ->contains(fn (string $column): bool => array_key_exists($this->normalizeLocationToken($column), $birdByToken));

        if ($anyKnownBirdColumn && ! $allBirdColumns) {
            return ClientImportMode::SingleSectorMultiBird;
        }

        $unknown = collect($quantityColumns)
            ->filter(fn (string $column, int $index): bool => $compositeResolved[$index] === null)
            ->values()
            ->all();

        throw ValidationException::withMessages([
            'file' => [
                'Las columnas intermedias deben coincidir con secciones del cliente, tipos de ave conocidos, o pares TipoSeccion: '.implode(', ', $unknown).'.',
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $quantityColumns
     * @param  array<int, string>  $warnings
     */
    private function expandSingleSectorSingleBird(
        Client $client,
        VisitImportPayload $payload,
        string $entryColumn,
        array $quantityColumns,
        array &$warnings,
    ): CompactNormalizationResult {
        if (count($quantityColumns) !== 1 || $quantityColumns[0] !== self::COMPACT_SINGLE_LOCATION_COLUMN) {
            throw ValidationException::withMessages([
                'file' => ['El modo una seccion / una ave requiere una sola columna intermedia llamada "Conteo".'],
            ]);
        }

        $requiredColumns = VisitImportStructureValidator::requiredColumns();
        $singleLocationName = $this->resolveSingleActiveLocationName($client);
        $birdName = $this->defaultBirdTypeName();
        $rowGroups = [];
        $expandedRows = collect();

        foreach ($payload->rows as $rowIndex => $row) {
            $compactRow = $this->rowToMap($payload->headers, $row);
            $qty = trim((string) ($compactRow[self::COMPACT_SINGLE_LOCATION_COLUMN] ?? ''));
            if ($qty === '') {
                continue;
            }

            $expandedRows->push(
                $this->buildCanonicalRowValues(
                    $requiredColumns,
                    $this->buildNormalizedCompactRow(
                        $compactRow,
                        $client,
                        $singleLocationName,
                        self::COMPACT_SINGLE_LOCATION_COLUMN,
                        $entryColumn,
                        $birdName,
                    ),
                ),
            );
            $rowGroups[] = $payload->rowGroupAt((int) $rowIndex);
        }

        return new CompactNormalizationResult(
            new VisitImportPayload($requiredColumns, $expandedRows->values(), $rowGroups),
            $warnings,
            [$singleLocationName],
            [$birdName],
        );
    }

    /**
     * @param  array<int, string>  $quantityColumns
     * @param  array<int, string>  $warnings
     */
    private function expandSingleSectorMultiBird(
        Client $client,
        VisitImportPayload $payload,
        string $entryColumn,
        array $quantityColumns,
        array &$warnings,
    ): CompactNormalizationResult {
        $requiredColumns = VisitImportStructureValidator::requiredColumns();
        $singleLocationName = $this->resolveSingleActiveLocationName($client);
        $birdByToken = $this->birdTypeTokenToName();

        foreach ($birdByToken as $token => $birdName) {
            $hasColumn = collect($quantityColumns)
                ->contains(fn (string $column): bool => $this->normalizeLocationToken($column) === $token);

            if (! $hasColumn) {
                $warnings[] = 'No hay columna para el tipo de ave "'.$birdName.'"; se importara solo la informacion presente en el archivo.';
            }
        }

        foreach ($quantityColumns as $quantityColumn) {
            $token = $this->normalizeLocationToken($quantityColumn);
            if (! array_key_exists($token, $birdByToken)) {
                $warnings[] = 'La columna "'.$quantityColumn.'" no coincide con ningun tipo de ave conocido; se omitira.';
            }
        }

        $expandedRows = collect();
        $rowGroups = [];
        $birdsSeen = [];

        foreach ($payload->rows as $rowIndex => $row) {
            $compactRow = $this->rowToMap($payload->headers, $row);
            $sourceRowGroup = $payload->rowGroupAt((int) $rowIndex);

            foreach ($quantityColumns as $quantityColumn) {
                $token = $this->normalizeLocationToken($quantityColumn);
                $birdName = $birdByToken[$token] ?? null;

                if (! is_string($birdName)) {
                    continue;
                }

                $qty = trim((string) ($compactRow[$quantityColumn] ?? ''));
                if ($qty === '') {
                    continue;
                }

                $birdsSeen[$birdName] = true;
                $expandedRows->push(
                    $this->buildCanonicalRowValues(
                        $requiredColumns,
                        $this->buildNormalizedCompactRow(
                            $compactRow,
                            $client,
                            $singleLocationName,
                            $quantityColumn,
                            $entryColumn,
                            $birdName,
                        ),
                    ),
                );
                $rowGroups[] = $sourceRowGroup;
            }
        }

        return new CompactNormalizationResult(
            new VisitImportPayload($requiredColumns, $expandedRows->values(), $rowGroups),
            $warnings,
            [$singleLocationName],
            array_keys($birdsSeen),
        );
    }

    /**
     * @param  array<int, string>  $quantityColumns
     * @param  array<int, string>  $warnings
     */
    private function expandMultiSectorSingleBird(
        Client $client,
        VisitImportPayload $payload,
        string $entryColumn,
        array $quantityColumns,
        array &$warnings,
    ): CompactNormalizationResult {
        $requiredColumns = VisitImportStructureValidator::requiredColumns();
        $locationByToken = $this->namedLocationTokenToName($client);
        $birdName = $this->defaultBirdTypeName();

        foreach ($locationByToken as $token => $locationName) {
            $hasColumn = collect($quantityColumns)
                ->contains(fn (string $column): bool => $this->normalizeLocationToken($column) === $token);

            if (! $hasColumn) {
                $warnings[] = 'No hay columna para la seccion "'.$locationName.'"; se importara solo la informacion presente en el archivo.';
            }
        }

        $unknownSectionColumns = [];
        foreach ($quantityColumns as $quantityColumn) {
            $token = $this->normalizeLocationToken($quantityColumn);
            if (! array_key_exists($token, $locationByToken)) {
                $unknownSectionColumns[] = $quantityColumn;
            }
        }

        if ($unknownSectionColumns !== []) {
            $registered = $client->namedLocations()
                ->where('active', true)
                ->pluck('name')
                ->sort()
                ->values()
                ->implode(', ');

            throw ValidationException::withMessages([
                'file' => [
                    'Hay columnas entre Salida y Observaciones que no coinciden con ninguna seccion activa del cliente "'
                    .$client->name.'": '
                    .implode(', ', $unknownSectionColumns)
                    .'. Revise los encabezados para que coincidan con las secciones configuradas: '
                    .$registered
                    .'.',
                ],
            ]);
        }

        $expandedRows = collect();
        $rowGroups = [];
        $sectionsSeen = [];

        foreach ($payload->rows as $rowIndex => $row) {
            $compactRow = $this->rowToMap($payload->headers, $row);
            $sourceRowGroup = $payload->rowGroupAt((int) $rowIndex);

            foreach ($quantityColumns as $quantityColumn) {
                $token = $this->normalizeLocationToken($quantityColumn);
                $locationName = $locationByToken[$token] ?? null;

                if (! is_string($locationName)) {
                    continue;
                }

                $qty = trim((string) ($compactRow[$quantityColumn] ?? ''));
                if ($qty === '') {
                    continue;
                }

                $sectionsSeen[$locationName] = true;
                $expandedRows->push(
                    $this->buildCanonicalRowValues(
                        $requiredColumns,
                        $this->buildNormalizedCompactRow(
                            $compactRow,
                            $client,
                            $locationName,
                            $quantityColumn,
                            $entryColumn,
                            $birdName,
                        ),
                    ),
                );
                $rowGroups[] = $sourceRowGroup;
            }
        }

        return new CompactNormalizationResult(
            new VisitImportPayload($requiredColumns, $expandedRows->values(), $rowGroups),
            $warnings,
            array_keys($sectionsSeen),
            [$birdName],
        );
    }

    /**
     * @param  array<string, string>  $birdByToken
     * @param  array<string, string>  $locationByToken
     */
    private function resolveCompositeColumn(
        string $column,
        array $birdByToken,
        array $locationByToken,
    ): ?array {
        if ($locationByToken === []) {
            return null;
        }

        $headerToken = $this->normalizeLocationToken($column);

        $locationNames = array_values(array_unique(array_values($locationByToken)));

        usort(
            $locationNames,
            static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($b, $a),
        );

        foreach ($locationNames as $locationName) {
            $sectionToken = $this->normalizeLocationToken($locationName);
            if ($sectionToken === '' || ! str_ends_with($headerToken, $sectionToken)) {
                continue;
            }

            $birdTokenPrefix = substr($headerToken, 0, strlen($headerToken) - strlen($sectionToken));

            foreach ($birdByToken as $birdTok => $birdName) {
                if ($birdTok === $birdTokenPrefix) {
                    return [$birdName, $locationName];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $quantityColumns
     * @param  array<int, string>  $warnings
     */
    private function expandMultiSectorMultiBird(
        Client $client,
        VisitImportPayload $payload,
        string $entryColumn,
        array $quantityColumns,
        array &$warnings,
    ): CompactNormalizationResult {
        $requiredColumns = VisitImportStructureValidator::requiredColumns();
        $birdByToken = $this->birdTypeTokenToName();
        $locationByToken = $this->namedLocationTokenToName($client);

        $resolvedColumns = [];

        foreach ($quantityColumns as $column) {
            $pair = $this->resolveCompositeColumn($column, $birdByToken, $locationByToken);

            if ($pair === null) {
                throw ValidationException::withMessages([
                    'file' => [
                        'No se pudo interpretar la columna compuesta "'.$column.'" como tipo de ave + seccion.',
                    ],
                ]);
            }

            $resolvedColumns[$column] = $pair;
        }

        $expectedPairCount = count($locationByToken) * max(count($birdByToken), 1);

        if (count($resolvedColumns) < $expectedPairCount) {
            $warnings[] = 'El archivo no incluye columnas para todas las combinaciones de seccion y tipo de ave; se importara solo la informacion presente.';
        }

        $expandedRows = collect();
        $rowGroups = [];
        $sectionsSeen = [];
        $birdsSeen = [];

        foreach ($payload->rows as $rowIndex => $row) {
            $compactRow = $this->rowToMap($payload->headers, $row);
            $sourceRowGroup = $payload->rowGroupAt((int) $rowIndex);

            foreach ($quantityColumns as $quantityColumn) {
                /** @var array{0: string, 1: string} $pair */
                $pair = $resolvedColumns[$quantityColumn];
                [$birdName, $locationName] = $pair;

                $qty = trim((string) ($compactRow[$quantityColumn] ?? ''));
                if ($qty === '') {
                    continue;
                }

                $sectionsSeen[$locationName] = true;
                $birdsSeen[$birdName] = true;
                $expandedRows->push(
                    $this->buildCanonicalRowValues(
                        $requiredColumns,
                        $this->buildNormalizedCompactRow(
                            $compactRow,
                            $client,
                            $locationName,
                            $quantityColumn,
                            $entryColumn,
                            $birdName,
                        ),
                    ),
                );
                $rowGroups[] = $sourceRowGroup;
            }
        }

        return new CompactNormalizationResult(
            new VisitImportPayload($requiredColumns, $expandedRows->values(), $rowGroups),
            $warnings,
            array_keys($sectionsSeen),
            array_keys($birdsSeen),
        );
    }

    /**
     * @return array<string, string> token => display name
     */
    private function namedLocationTokenToName(Client $client): array
    {
        return $this->namedLocationTokenToNameMapForInference($client, false);
    }

    /**
     * @return array<string, string> token => display name
     */
    private function namedLocationTokenToNameMapForInference(Client $client, bool $allowEmpty): array
    {
        $locations = $client->namedLocations()
            ->where('active', true)
            ->get(['name']);

        if ($locations->isEmpty()) {
            if ($allowEmpty) {
                return [];
            }

            throw ValidationException::withMessages([
                'file' => ['El cliente seleccionado no tiene secciones activas configuradas.'],
            ]);
        }

        /** @var array<string, string> $map */
        $map = $locations
            ->mapWithKeys(fn (Location $location): array => [
                $this->normalizeLocationToken($location->name) => (string) $location->name,
            ])
            ->all();

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function birdTypeTokenToName(): array
    {
        return BirdType::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['name'])
            ->mapWithKeys(fn (BirdType $birdType): array => [
                $this->normalizeLocationToken($birdType->name) => (string) $birdType->name,
            ])
            ->all();
    }

    private function resolveSingleActiveLocationName(Client $client): string
    {
        $activeLocations = $client->locations()
            ->where('active', true)
            ->pluck('name')
            ->values();

        if ($activeLocations->count() !== 1) {
            throw ValidationException::withMessages([
                'file' => ['Este formato requiere exactamente una ubicacion activa para el cliente seleccionado.'],
            ]);
        }

        return (string) $activeLocations->first();
    }

    private function defaultBirdTypeName(): string
    {
        return self::DEFAULT_BIRD_TYPE_NAME;
    }

    /**
     * @param  array<string, string>  $normalizedRow
     * @param  array<int, string>  $requiredColumns
     * @return array<int, string>
     */
    private function buildCanonicalRowValues(array $requiredColumns, array $normalizedRow): array
    {
        return collect($requiredColumns)
            ->map(fn (string $column): string => (string) ($normalizedRow[$column] ?? ''))
            ->all();
    }

    /**
     * @param  array<string, string>  $compactRow
     * @return array<string, string>
     */
    private function buildNormalizedCompactRow(
        array $compactRow,
        Client $client,
        string $locationName,
        string $quantityColumn,
        string $entryColumn,
        string $birdTypeName,
    ): array {
        $observation = $compactRow['observaciones'] ?? '';

        return [
            'client_name' => $client->name,
            'employee_name' => $compactRow['nombre_de_usuario'] ?? '',
            'employee_email' => '',
            'date_init' => $this->combineCompactDateAndTime(
                $compactRow['fecha'] ?? '',
                $compactRow[$entryColumn] ?? '',
            ),
            'date_end' => $this->combineCompactDateAndTime(
                $compactRow['fecha'] ?? '',
                $compactRow['salida'] ?? '',
            ),
            'status' => self::DEFAULT_STATUS,
            'location_name' => $locationName,
            'bird_type_name' => $birdTypeName,
            'quantity' => $compactRow[$quantityColumn] ?? '',
            'observation' => $observation,
            'visit_observation' => $observation,
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $row
     * @return array<string, string>
     */
    private function rowToMap(array $headers, array $row): array
    {
        return collect($headers)
            ->mapWithKeys(fn (string $header, int $index): array => [
                $header => (string) ($row[$index] ?? ''),
            ])
            ->all();
    }

    private function combineCompactDateAndTime(string $date, string $time): string
    {
        $trimmedDate = trim($date);
        $trimmedTime = trim($time);

        if ($trimmedDate !== '' && $trimmedTime !== '' && is_numeric($trimmedDate) && is_numeric($trimmedTime)) {
            return (string) (((float) $trimmedDate) + ((float) $trimmedTime));
        }

        return trim("{$trimmedDate} {$trimmedTime}");
    }

    private function normalizeLocationToken(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '');
    }
}
