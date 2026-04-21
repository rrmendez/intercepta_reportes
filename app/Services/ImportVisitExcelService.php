<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Location;
use App\Services\VisitImport\Parsers\CsvVisitImportParser;
use App\Services\VisitImport\Parsers\XlsxVisitImportParser;
use App\Services\VisitImport\Persistence\VisitImportPersistence;
use App\Services\VisitImport\Validation\VisitImportStructureValidator;
use App\Services\VisitImport\VisitImportPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportVisitExcelService
{
    /**
     * @var array<int, string>
     */
    private const COMPACT_FIXED_COLUMNS = [
        'fecha',
        'salida',
        'observaciones',
        'nombre_de_usuario',
    ];

    /**
     * @var array<int, string>
     */
    private const ENTRY_COLUMN_CANDIDATES = [
        'entrada',
        'entarda',
    ];

    private const string COMPACT_SINGLE_LOCATION_COLUMN = 'conteo';

    private const string DEFAULT_BIRD_TYPE_NAME = 'Palomas';

    private const string DEFAULT_STATUS = 'completed';

    public function __construct(
        private readonly CsvVisitImportParser $csvParser,
        private readonly XlsxVisitImportParser $xlsxParser,
        private readonly VisitImportStructureValidator $structureValidator,
        private readonly VisitImportPersistence $persistence,
    ) {}

    /**
     * @return array{status: string, total_rows: int, persisted_rows: int, skipped_rows: int}
     */
    public function import(string $absolutePath, ?int $fallbackClientId = null): array
    {
        $normalizedPayload = $this->preparePayloadForImport($absolutePath, $fallbackClientId);

        return $this->persistence->persist($normalizedPayload);
    }

    /**
     * @return array{total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>}
     */
    public function preview(string $absolutePath, ?int $fallbackClientId = null): array
    {
        $normalizedPayload = $this->preparePayloadForImport($absolutePath, $fallbackClientId);

        return $this->persistence->preview($normalizedPayload);
    }

    private function preparePayloadForImport(string $absolutePath, ?int $fallbackClientId): VisitImportPayload
    {
        $payload = $this->parsePayload($absolutePath);
        $normalizedPayload = $this->normalizePayloadForImport($payload, $fallbackClientId, $absolutePath);
        $this->structureValidator->validate($normalizedPayload);

        return $normalizedPayload;
    }

    private function parsePayload(string $absolutePath): VisitImportPayload
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->csvParser->parse($absolutePath),
            'xlsx' => $this->xlsxParser->parse($absolutePath),
            default => throw ValidationException::withMessages([
                'file' => ['Unsupported file type. Use CSV or XLSX.'],
            ]),
        };
    }

    private function normalizePayloadForImport(
        VisitImportPayload $payload,
        ?int $fallbackClientId,
        string $absolutePath
    ): VisitImportPayload {
        if ($this->containsColumns($payload->headers, VisitImportStructureValidator::requiredColumns())) {
            return $payload;
        }

        $compactColumns = $this->resolveCompactColumns($payload->headers);

        if ($compactColumns === null) {
            return $payload;
        }

        $client = $this->resolveClientForCompactImport($fallbackClientId, $absolutePath);

        if (
            count($compactColumns['quantity_columns']) === 1
            && $compactColumns['quantity_columns'][0] === self::COMPACT_SINGLE_LOCATION_COLUMN
        ) {
            return $this->mapCompactSingleLocationPayload(
                payload: $payload,
                client: $client,
                entryColumn: $compactColumns['entry_column'],
            );
        }

        return $this->mapCompactMultiLocationPayload(
            payload: $payload,
            client: $client,
            entryColumn: $compactColumns['entry_column'],
            quantityColumns: $compactColumns['quantity_columns'],
        );
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $requiredColumns
     */
    private function containsColumns(array $headers, array $requiredColumns): bool
    {
        return collect($requiredColumns)
            ->every(fn (string $column): bool => in_array($column, $headers, true));
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{entry_column: string, quantity_columns: array<int, string>}|null
     */
    private function resolveCompactColumns(array $headers): ?array
    {
        $entryColumn = collect(self::ENTRY_COLUMN_CANDIDATES)
            ->first(fn (string $candidate): bool => in_array($candidate, $headers, true));

        if (! is_string($entryColumn)) {
            return null;
        }

        if (! $this->containsColumns($headers, self::COMPACT_FIXED_COLUMNS)) {
            return null;
        }

        $salidaIndex = array_search('salida', $headers, true);
        $observacionesIndex = array_search('observaciones', $headers, true);

        if (! is_int($salidaIndex) || ! is_int($observacionesIndex) || $observacionesIndex <= $salidaIndex) {
            throw ValidationException::withMessages([
                'file' => ['Invalid compact format: expected columns between "Salida" and "Observaciones".'],
            ]);
        }

        $quantityColumns = array_values(array_slice(
            $headers,
            $salidaIndex + 1,
            $observacionesIndex - $salidaIndex - 1,
        ));

        if ($quantityColumns === []) {
            throw ValidationException::withMessages([
                'file' => ['Invalid compact format: no columns found between "Salida" and "Observaciones".'],
            ]);
        }

        if (count($quantityColumns) === 1 && $quantityColumns[0] !== self::COMPACT_SINGLE_LOCATION_COLUMN) {
            throw ValidationException::withMessages([
                'file' => ['Invalid compact format: single intermediate column must be "Conteo".'],
            ]);
        }

        return [
            'entry_column' => $entryColumn,
            'quantity_columns' => $quantityColumns,
        ];
    }

    private function mapCompactSingleLocationPayload(VisitImportPayload $payload, Client $client, string $entryColumn): VisitImportPayload
    {
        $requiredColumns = VisitImportStructureValidator::requiredColumns();
        $singleLocationName = $this->resolveSingleClientLocationName($client);
        $rowGroups = [];

        /** @var Collection<int, array<int, string>> $rows */
        $rows = $payload->rows
            ->map(function (array $row, int $rowIndex) use (
                $payload,
                $client,
                $requiredColumns,
                $singleLocationName,
                $entryColumn,
                &$rowGroups
            ): array {
                $compactRow = $this->rowToMap($payload->headers, $row);
                $normalizedRow = $this->buildNormalizedCompactRow(
                    compactRow: $compactRow,
                    client: $client,
                    locationName: $singleLocationName,
                    quantityColumn: self::COMPACT_SINGLE_LOCATION_COLUMN,
                    entryColumn: $entryColumn,
                );
                $rowGroups[] = $payload->rowGroupAt($rowIndex);

                return collect($requiredColumns)
                    ->map(fn (string $column): string => (string) ($normalizedRow[$column] ?? ''))
                    ->all();
            })
            ->values();

        return new VisitImportPayload($requiredColumns, $rows, $rowGroups);
    }

    /**
     * @param  array<int, string>  $quantityColumns
     */
    private function mapCompactMultiLocationPayload(
        VisitImportPayload $payload,
        Client $client,
        string $entryColumn,
        array $quantityColumns
    ): VisitImportPayload {
        $requiredColumns = VisitImportStructureValidator::requiredColumns();
        $locationByHeaderColumn = $this->resolveLocationsFromHeaderColumns($client, $quantityColumns);
        $expandedRows = collect();
        $rowGroups = [];

        foreach ($payload->rows as $rowIndex => $row) {
            $compactRow = $this->rowToMap($payload->headers, $row);
            $sourceRowGroup = $payload->rowGroupAt((int) $rowIndex);

            foreach ($locationByHeaderColumn as $quantityColumn => $locationName) {
                $normalizedRow = $this->buildNormalizedCompactRow(
                    compactRow: $compactRow,
                    client: $client,
                    locationName: $locationName,
                    quantityColumn: $quantityColumn,
                    entryColumn: $entryColumn,
                );

                $expandedRows->push(
                    collect($requiredColumns)
                        ->map(fn (string $column): string => (string) ($normalizedRow[$column] ?? ''))
                        ->all(),
                );
                $rowGroups[] = $sourceRowGroup;
            }
        }

        return new VisitImportPayload($requiredColumns, $expandedRows->values(), $rowGroups);
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
        string $entryColumn
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
            'bird_type_name' => self::DEFAULT_BIRD_TYPE_NAME,
            'quantity' => $compactRow[$quantityColumn] ?? '',
            'observation' => $observation,
            'visit_observation' => $observation,
        ];
    }

    private function resolveSingleClientLocationName(Client $client): string
    {
        $activeLocations = $client->locations()
            ->where('active', true)
            ->pluck('name')
            ->values();

        if ($activeLocations->count() !== 1) {
            throw ValidationException::withMessages([
                'file' => ['"Conteo" format requires exactly one active location for the selected client.'],
            ]);
        }

        return (string) $activeLocations->first();
    }

    /**
     * @param  array<int, string>  $headerColumns
     * @return array<string, string>
     */
    private function resolveLocationsFromHeaderColumns(Client $client, array $headerColumns): array
    {
        /** @var Collection<int, Location> $locations */
        $locations = $client->locations()
            ->where('active', true)
            ->get(['name']);

        if ($locations->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => ['The selected client has no active locations configured.'],
            ]);
        }

        /** @var array<string, string> $locationByHeader */
        $locationByHeader = $locations
            ->mapWithKeys(fn (Location $location): array => [
                $this->normalizeLocationToken($location->name) => (string) $location->name,
            ])
            ->all();

        $unmatchedColumns = collect($headerColumns)
            ->filter(fn (string $column): bool => ! array_key_exists($this->normalizeLocationToken($column), $locationByHeader))
            ->values()
            ->all();

        if ($unmatchedColumns !== []) {
            throw ValidationException::withMessages([
                'file' => [
                    'These columns must match client locations: '.implode(', ', $unmatchedColumns).'.',
                ],
            ]);
        }

        return collect($headerColumns)
            ->mapWithKeys(fn (string $column): array => [
                $column => (string) ($locationByHeader[$this->normalizeLocationToken($column)] ?? ''),
            ])
            ->all();
    }

    private function resolveClientForCompactImport(?int $fallbackClientId, string $absolutePath): Client
    {
        if (is_int($fallbackClientId)) {
            $client = Client::query()->find($fallbackClientId);

            if ($client instanceof Client) {
                return $client;
            }

            throw ValidationException::withMessages([
                'client_id' => ['Selected client is invalid.'],
            ]);
        }

        return $this->resolveClientFromFileName($absolutePath);
    }

    private function resolveClientFromFileName(string $absolutePath): Client
    {
        $fileNameToken = $this->extractClientTokenFromFileName($absolutePath);

        /** @var Collection<int, Client> $clients */
        $clients = Client::query()
            ->get(['id', 'name']);

        $exactMatches = $clients
            ->filter(fn (Client $client): bool => $this->normalizeHeaderToken($client->name) === $fileNameToken)
            ->values();

        if ($exactMatches->count() === 1) {
            $exactMatch = $exactMatches->first();

            if ($exactMatch instanceof Client) {
                return $exactMatch;
            }
        }

        if ($exactMatches->count() > 1) {
            throw ValidationException::withMessages([
                'file' => ['Ambiguous client match from file name.'],
            ]);
        }

        $fuzzyMatches = $clients
            ->filter(function (Client $client) use ($fileNameToken): bool {
                $clientToken = $this->normalizeHeaderToken($client->name);

                return str_contains($fileNameToken, $clientToken) || str_contains($clientToken, $fileNameToken);
            })
            ->values();

        if ($fuzzyMatches->count() === 1) {
            $fuzzyMatch = $fuzzyMatches->first();

            if ($fuzzyMatch instanceof Client) {
                return $fuzzyMatch;
            }
        }

        throw ValidationException::withMessages([
            'file' => ['Unable to resolve client from file name.'],
        ]);
    }

    private function extractClientTokenFromFileName(string $absolutePath): string
    {
        $baseName = (string) pathinfo($absolutePath, PATHINFO_FILENAME);
        $normalizedBaseName = $this->normalizeHeaderToken($baseName);

        $beforeConstancy = Str::before($normalizedBaseName, '_constancia_de_servicio');
        $candidate = $beforeConstancy !== '' ? $beforeConstancy : $normalizedBaseName;
        $candidate = (string) preg_replace('/_\d{8}$/', '', $candidate);
        $candidate = trim($candidate, '_');

        if ($candidate === '') {
            throw ValidationException::withMessages([
                'file' => ['Unable to infer client from file name.'],
            ]);
        }

        return $candidate;
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

        if ($this->isNumericString($trimmedDate) && $this->isNumericString($trimmedTime)) {
            return (string) (((float) $trimmedDate) + ((float) $trimmedTime));
        }

        return trim("{$trimmedDate} {$trimmedTime}");
    }

    private function isNumericString(string $value): bool
    {
        return $value !== '' && is_numeric($value);
    }

    private function normalizeHeaderToken(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    private function normalizeLocationToken(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '');
    }
}
