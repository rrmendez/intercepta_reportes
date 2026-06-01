<?php

namespace App\Actions\VisitImport;

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Location;
use App\Services\BirdTypes\BirdTypeResolver;
use App\Services\BirdTypes\BirdTypeTokenNormalizer;
use App\Services\VisitImport\Validation\VisitImportStructureValidator;
use App\Services\VisitImport\VisitImportPayload;
use Illuminate\Validation\ValidationException;

final class NormalizeCompactVisitPayloadAction
{
    private const string COMPACT_SINGLE_LOCATION_COLUMN = 'conteo';

    private const string DEFAULT_STATUS = 'completed';

    public function __construct(
        private readonly BirdTypeResolver $birdTypeResolver,
        private readonly BirdTypeTokenNormalizer $tokenNormalizer,
    ) {}

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
     * @return array<int, string>
     */
    public function sectionNamesForProvisioning(array $quantityColumns): array
    {
        $classification = $this->classifyHybridMultiSectorColumnsForProvisioning(
            $quantityColumns,
            $this->birdTypeTokenToName(),
        );

        /** @var array<int, string> $names */
        $names = collect($classification['section_columns'])
            ->values()
            ->merge(collect($classification['composite_columns'])->map(fn (array $pair): string => $pair[1]))
            ->map(fn (string $name): string => trim($name))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();

        return $names;
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

        if ($this->isHybridMultiSectorMultiBirdFormat($quantityColumns, $birdByToken, $locationByToken, $provision)) {
            return ClientImportMode::MultiSectorMultiBird;
        }

        $allLocationColumns = $locationByToken !== [] && collect($quantityColumns)
            ->every(fn (string $column): bool => array_key_exists($this->normalizeLocationToken($column), $locationByToken));

        if ($allLocationColumns) {
            return ClientImportMode::MultiSectorSingleBird;
        }

        $allBirdColumns = collect($quantityColumns)
            ->every(fn (string $column): bool => array_key_exists($this->normalizeLocationToken($column), $birdByToken));

        if ($allBirdColumns) {
            return ClientImportMode::SingleSectorMultiBird;
        }

        if ($provision && $locationByToken === [] && ! $allBirdColumns) {
            return ClientImportMode::MultiSectorSingleBird;
        }

        $anyKnownBirdColumn = collect($quantityColumns)
            ->contains(fn (string $column): bool => array_key_exists($this->normalizeLocationToken($column), $birdByToken));

        if ($anyKnownBirdColumn && ! $allBirdColumns) {
            return ClientImportMode::SingleSectorMultiBird;
        }

        $classification = $this->classifyHybridMultiSectorColumns(
            $quantityColumns,
            $birdByToken,
            $locationByToken,
            strict: false,
        );

        $unknown = collect($quantityColumns)
            ->reject(fn (string $column): bool => in_array($column, $classification['bird_only_columns'], true)
                || array_key_exists($column, $classification['section_columns'])
                || array_key_exists($column, $classification['composite_columns']))
            ->values()
            ->all();

        throw ValidationException::withMessages([
            'file' => [
                'Las columnas intermedias deben coincidir con secciones del cliente, tipos de ave (bloque inicial ignorado), secciones sin ave (Palomas), o pares ave+seccion: '.implode(', ', $unknown).'.',
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

        $quantityColumnTokens = collect($quantityColumns)
            ->map(fn (string $column): string => $this->normalizeLocationToken($column))
            ->all();

        foreach ($this->birdTypeTokensGroupedByName($birdByToken) as $birdName => $tokens) {
            $hasColumn = collect($tokens)
                ->contains(fn (string $token): bool => in_array($token, $quantityColumnTokens, true));

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
        $defaultBirdName = $this->defaultBirdTypeName();

        $classification = $this->classifyHybridMultiSectorColumns(
            $quantityColumns,
            $birdByToken,
            $locationByToken,
        );

        foreach ($locationByToken as $token => $locationName) {
            $hasColumn = collect($classification['section_columns'])
                ->contains(fn (string $resolvedLocationName): bool => $resolvedLocationName === $locationName);

            if (! $hasColumn) {
                $warnings[] = 'No hay columna para la seccion "'.$locationName.'"; se importara solo la informacion presente en el archivo.';
            }
        }

        $expandedRows = collect();
        $rowGroups = [];
        $sectionsSeen = [];
        $birdsSeen = [];

        foreach ($payload->rows as $rowIndex => $row) {
            $compactRow = $this->rowToMap($payload->headers, $row);
            $sourceRowGroup = $payload->rowGroupAt((int) $rowIndex);

            foreach ($classification['section_columns'] as $quantityColumn => $locationName) {
                $qty = trim((string) ($compactRow[$quantityColumn] ?? ''));
                if ($qty === '') {
                    continue;
                }

                $sectionsSeen[$locationName] = true;
                $birdsSeen[$defaultBirdName] = true;
                $expandedRows->push(
                    $this->buildCanonicalRowValues(
                        $requiredColumns,
                        $this->buildNormalizedCompactRow(
                            $compactRow,
                            $client,
                            $locationName,
                            $quantityColumn,
                            $entryColumn,
                            $defaultBirdName,
                        ),
                    ),
                );
                $rowGroups[] = $sourceRowGroup;
            }

            foreach ($classification['composite_columns'] as $quantityColumn => $pair) {
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
     * @param  array<int, string>  $quantityColumns
     * @param  array<string, string>  $birdByToken
     * @param  array<string, string>  $locationByToken
     */
    private function isHybridMultiSectorMultiBirdFormat(
        array $quantityColumns,
        array $birdByToken,
        array $locationByToken,
        bool $provision = false,
    ): bool {
        if ($provision && $locationByToken === []) {
            $classification = $this->classifyHybridMultiSectorColumnsForProvisioning(
                $quantityColumns,
                $birdByToken,
            );

            if ($classification['composite_columns'] !== []) {
                return true;
            }

            return $classification['bird_only_columns'] !== []
                && $classification['section_columns'] !== [];
        }

        $classification = $this->classifyHybridMultiSectorColumns(
            $quantityColumns,
            $birdByToken,
            $locationByToken,
            strict: false,
        );

        if ($classification['composite_columns'] !== []) {
            if (count($locationByToken) > 1) {
                return true;
            }

            return $classification['section_columns'] === []
                && $classification['bird_only_columns'] === [];
        }

        return count($locationByToken) > 1
            && $classification['bird_only_columns'] !== []
            && $classification['section_columns'] !== [];
    }

    /**
     * @param  array<int, string>  $quantityColumns
     * @param  array<string, string>  $birdByToken
     * @param  array<string, string>  $locationByToken
     * @return array{
     *     bird_only_columns: list<string>,
     *     section_columns: array<string, string>,
     *     composite_columns: array<string, array{0: string, 1: string}>
     * }
     */
    private function classifyHybridMultiSectorColumns(
        array $quantityColumns,
        array $birdByToken,
        array $locationByToken,
        bool $strict = true,
    ): array {
        $birdOnlyColumns = [];
        $sectionColumns = [];
        $compositeColumns = [];
        $phase = 'birds';

        foreach ($quantityColumns as $column) {
            $token = $this->normalizeLocationToken($column);

            if ($phase === 'birds') {
                if ($this->isPureBirdColumn($column, $token, $birdByToken, $locationByToken)) {
                    $birdOnlyColumns[] = $column;

                    continue;
                }

                $phase = 'sections';
            }

            if ($phase === 'sections') {
                $locationName = $locationByToken[$token] ?? null;

                if (is_string($locationName)) {
                    $sectionColumns[$column] = $locationName;

                    continue;
                }

                $phase = 'composites';
            }

            $pair = $this->resolveCompositeColumn($column, $birdByToken, $locationByToken);

            if ($pair !== null) {
                $compositeColumns[$column] = $pair;

                continue;
            }

            if ($strict) {
                throw ValidationException::withMessages([
                    'file' => [
                        'No se pudo interpretar la columna "'.$column.'" en el bloque de secciones (Palomas) o como tipo de ave + seccion.',
                    ],
                ]);
            }
        }

        return [
            'bird_only_columns' => $birdOnlyColumns,
            'section_columns' => $sectionColumns,
            'composite_columns' => $compositeColumns,
        ];
    }

    /**
     * @param  array<int, string>  $quantityColumns
     * @param  array<string, string>  $birdByToken
     * @return array{
     *     bird_only_columns: list<string>,
     *     section_columns: array<string, string>,
     *     composite_columns: array<string, array{0: string, 1: string}>
     * }
     */
    private function classifyHybridMultiSectorColumnsForProvisioning(
        array $quantityColumns,
        array $birdByToken,
    ): array {
        $birdOnlyColumns = [];
        $sectionColumns = [];
        $compositeColumns = [];
        $phase = 'birds';

        foreach ($quantityColumns as $column) {
            $token = $this->normalizeLocationToken($column);

            if ($phase === 'birds') {
                if (
                    array_key_exists($token, $birdByToken)
                    && $this->resolveCompositeColumnForProvisioning($column, $birdByToken, sectionsPhaseOnly: true) === null
                ) {
                    $birdOnlyColumns[] = $column;

                    continue;
                }

                $phase = 'sections';
            }

            if ($phase === 'sections') {
                $composite = $this->resolveCompositeColumnForProvisioning($column, $birdByToken, sectionsPhaseOnly: true);

                if ($composite === null) {
                    $sectionColumns[$column] = trim($column);

                    continue;
                }

                $phase = 'composites';
            }

            $pair = $this->resolveCompositeColumnForProvisioning($column, $birdByToken);

            if ($pair !== null) {
                $compositeColumns[$column] = $pair;
            }
        }

        return [
            'bird_only_columns' => $birdOnlyColumns,
            'section_columns' => $sectionColumns,
            'composite_columns' => $compositeColumns,
        ];
    }

    /**
     * @param  array<string, string>  $birdByToken
     * @return array{0: string, 1: string}|null
     */
    private function resolveCompositeColumnForProvisioning(
        string $column,
        array $birdByToken,
        bool $sectionsPhaseOnly = false,
    ): ?array {
        $match = $this->birdTypeResolver->matchCompositePrefix($column);

        if ($match !== null) {
            return [$match[0]->name, $match[1]];
        }

        return null;
    }

    /**
     * @param  array<string, string>  $birdByToken
     * @param  array<string, string>  $locationByToken
     */
    private function isPureBirdColumn(
        string $column,
        string $token,
        array $birdByToken,
        array $locationByToken,
    ): bool {
        if (! array_key_exists($token, $birdByToken)) {
            return false;
        }

        return $this->resolveCompositeColumn($column, $birdByToken, $locationByToken) === null;
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
        return collect($this->birdTypeResolver->importLabelMap())
            ->mapWithKeys(fn (BirdType $birdType, string $token): array => [
                $token => (string) $birdType->name,
            ])
            ->all();
    }

    /**
     * @param  array<string, string>  $birdByToken
     * @return array<string, list<string>>
     */
    private function birdTypeTokensGroupedByName(array $birdByToken): array
    {
        $grouped = [];

        foreach ($birdByToken as $token => $birdName) {
            $grouped[$birdName][] = $token;
        }

        return $grouped;
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
        return $this->birdTypeResolver->default()->name;
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
        return $this->tokenNormalizer->normalize($value);
    }
}
