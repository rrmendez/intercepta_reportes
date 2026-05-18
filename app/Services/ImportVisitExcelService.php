<?php

namespace App\Services;

use App\Actions\VisitImport\NormalizeCompactVisitPayloadAction;
use App\ClientImportMode;
use App\Models\Client;
use App\Models\Location;
use App\Models\Visit;
use App\Models\VisitImport;
use App\Services\VisitImport\Parsers\CsvVisitImportParser;
use App\Services\VisitImport\Parsers\XlsxVisitImportParser;
use App\Services\VisitImport\Persistence\VisitImportPersistence;
use App\Services\VisitImport\Validation\VisitImportStructureValidator;
use App\Services\VisitImport\VisitImportPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    public function __construct(
        private readonly CsvVisitImportParser $csvParser,
        private readonly XlsxVisitImportParser $xlsxParser,
        private readonly VisitImportStructureValidator $structureValidator,
        private readonly VisitImportPersistence $persistence,
        private readonly NormalizeCompactVisitPayloadAction $normalizeCompactVisitPayload,
    ) {}

    /**
     * @param  array{user_id?: int|null, original_filename?: string|null, stored_file_path?: string|null, provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $context
     * @return array{
     *     status: string,
     *     total_rows: int,
     *     persisted_rows: int,
     *     skipped_rows: int,
     *     warnings: array<int, string>,
     *     summary_message: string,
     *     client_name: string,
     *     sections: array<int, string>,
     *     bird_types: array<int, string>,
     * }
     */
    public function import(string $absolutePath, ?int $fallbackClientId = null, array $context = []): array
    {
        return DB::transaction(function () use ($absolutePath, $fallbackClientId, $context): array {
            $prepared = $this->preparePayloadForImport($absolutePath, $fallbackClientId, $context);
            $preview = $this->persistence->preview($prepared['payload']);

            $replaceNotice = $this->maybeReplacePreviousImportVisits($context, $fallbackClientId, $prepared);
            if ($replaceNotice !== null) {
                $prepared['warnings'][] = $replaceNotice;
            }

            $visitImport = $this->createVisitImportRecord(
                prepared: $prepared,
                preview: $preview,
                absolutePath: $absolutePath,
                fallbackClientId: $fallbackClientId,
                context: $context,
            );

            $result = $this->persistence->persist($prepared['payload'], $visitImport->id);
            $merged = array_merge($result, $this->buildImportMetadata($prepared, $result));

            $this->finalizeVisitImportRecord(
                visitImport: $visitImport,
                mergedResult: $merged,
                preview: $preview,
                persistResult: $result,
            );

            return $merged;
        });
    }

    /**
     * @param  array{user_id?: int|null, original_filename?: string|null, stored_file_path?: string|null, provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $context
     * @return array{
     *     total_rows: int,
     *     valid_rows: int,
     *     invalid_rows: int,
     *     errors: array<int, string>,
     *     warnings: array<int, string>,
     * }
     */
    public function preview(string $absolutePath, ?int $fallbackClientId = null, array $context = []): array
    {
        $provision = $this->provisionFromContext($context);
        $openedRollbackTransaction = false;

        if ($provision) {
            DB::beginTransaction();
            $openedRollbackTransaction = true;
        }

        try {
            $prepared = $this->preparePayloadForImport($absolutePath, $fallbackClientId, $context);
            $preview = $this->persistence->preview($prepared['payload']);
            $merged = array_merge($preview, [
                'warnings' => $prepared['warnings'],
            ]);

            if ($openedRollbackTransaction) {
                DB::rollBack();
            }

            return $merged;
        } catch (Throwable $e) {
            if ($openedRollbackTransaction) {
                DB::rollBack();
            }

            throw $e;
        }
    }

    /**
     * @param  array{user_id?: int|null, original_filename?: string|null, stored_file_path?: string|null, provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $context
     * @return array{
     *     payload: VisitImportPayload,
     *     warnings: array<int, string>,
     *     summarySections: array<int, string>,
     *     summaryBirdTypes: array<int, string>,
     *     clientName: string,
     * }
     */
    private function preparePayloadForImport(string $absolutePath, ?int $fallbackClientId, array $context = []): array
    {
        $payload = $this->parsePayload($absolutePath);
        $normalized = $this->normalizePayloadForImport($payload, $fallbackClientId, $absolutePath, $context);
        $this->structureValidator->validate($normalized['payload']);

        return $normalized;
    }

    private function parsePayload(string $absolutePath): VisitImportPayload
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->csvParser->parse($absolutePath),
            'xlsx' => $this->xlsxParser->parse($absolutePath),
            default => throw ValidationException::withMessages([
                'file' => ['Tipo de archivo no soportado. Usa CSV o XLSX.'],
            ]),
        };
    }

    /**
     * @param  array{user_id?: int|null, original_filename?: string|null, stored_file_path?: string|null, provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $context
     * @return array{
     *     payload: VisitImportPayload,
     *     warnings: array<int, string>,
     *     summarySections: array<int, string>,
     *     summaryBirdTypes: array<int, string>,
     *     clientName: string,
     * }
     */
    private function normalizePayloadForImport(
        VisitImportPayload $payload,
        ?int $fallbackClientId,
        string $absolutePath,
        array $context = [],
    ): array {
        if ($this->containsColumns($payload->headers, VisitImportStructureValidator::requiredColumns())) {
            return [
                'payload' => $payload,
                'warnings' => [],
                'summarySections' => [],
                'summaryBirdTypes' => [],
                'clientName' => $this->resolveSummaryClientName($payload, $fallbackClientId, $absolutePath),
            ];
        }

        $compactColumns = $this->resolveCompactColumns($payload->headers);

        if ($compactColumns === null) {
            return [
                'payload' => $payload,
                'warnings' => [],
                'summarySections' => [],
                'summaryBirdTypes' => [],
                'clientName' => $this->resolveSummaryClientName($payload, $fallbackClientId, $absolutePath),
            ];
        }

        $provision = $this->provisionFromContext($context);
        $client = $this->resolveClientForCompactImport($fallbackClientId, $absolutePath, $provision);

        if ($provision) {
            $mode = $this->normalizeCompactVisitPayload->inferImportMode(
                $client,
                $compactColumns['quantity_columns'],
                true,
            );
            $this->ensureProvisioningLocationsForMode($client, $mode, $compactColumns['quantity_columns']);
            $client->refresh();
        }

        $result = ($this->normalizeCompactVisitPayload)(
            $client,
            $payload,
            $compactColumns['entry_column'],
            $compactColumns['quantity_columns'],
            $provision,
        );

        $mode = $this->normalizeCompactVisitPayload->inferImportMode(
            $client->fresh(),
            $compactColumns['quantity_columns'],
            $provision,
        );
        $client->forceFill(['import_mode' => $mode])->save();

        return [
            'payload' => $result->payload,
            'warnings' => $result->warnings,
            'summarySections' => $result->summarySections,
            'summaryBirdTypes' => $result->summaryBirdTypes,
            'clientName' => $client->name,
        ];
    }

    /**
     * @param  array{provision_client_and_sections?: bool}  $context
     */
    private function provisionFromContext(array $context): bool
    {
        return isset($context['provision_client_and_sections']) && $context['provision_client_and_sections'] === true;
    }

    /**
     * @param  array{replace_previous_import_same_filename?: bool, original_filename?: string|null}  $context
     * @param  array{warnings: array<int, string>, clientName: string, ...}  $prepared
     */
    private function maybeReplacePreviousImportVisits(array $context, ?int $fallbackClientId, array $prepared): ?string
    {
        if (! isset($context['replace_previous_import_same_filename']) || $context['replace_previous_import_same_filename'] !== true) {
            return null;
        }

        $originalFilename = isset($context['original_filename']) && is_string($context['original_filename'])
            ? $context['original_filename']
            : '';

        if ($originalFilename === '') {
            return null;
        }

        $clientId = $this->resolveClientIdForLog($fallbackClientId, (string) ($prepared['clientName'] ?? ''));

        if (! is_int($clientId)) {
            return null;
        }

        $deleted = $this->deleteVisitsFromPreviousImportsSameFilename($clientId, $originalFilename);

        if ($deleted === 0) {
            return null;
        }

        return 'Se eliminaron '.$deleted.' visita(s) de importaciones anteriores con el mismo nombre de archivo ('.$originalFilename.').';
    }

    private function deleteVisitsFromPreviousImportsSameFilename(int $clientId, string $originalFilename): int
    {
        $importIds = VisitImport::query()
            ->where('client_id', $clientId)
            ->where('original_filename', $originalFilename)
            ->pluck('id');

        if ($importIds->isEmpty()) {
            return 0;
        }

        return Visit::query()
            ->whereIn('visit_import_id', $importIds->all())
            ->delete();
    }

    /**
     * @param  array<int, string>  $quantityColumns
     */
    private function ensureProvisioningLocationsForMode(Client $client, ClientImportMode $mode, array $quantityColumns): void
    {
        if ($mode === ClientImportMode::MultiSectorSingleBird) {
            foreach ($quantityColumns as $header) {
                $name = $this->sanitizeProvisioningLocationName($client, trim((string) $header));
                Location::query()->firstOrCreate(
                    [
                        'client_id' => $client->id,
                        'name' => $name,
                    ],
                    ['active' => true],
                );
            }

            return;
        }

        if ($mode === ClientImportMode::SingleSectorSingleBird || $mode === ClientImportMode::SingleSectorMultiBird) {
            $activeCount = $client->locations()->where('active', true)->count();
            if ($activeCount === 0) {
                Location::query()->firstOrCreate(
                    [
                        'client_id' => $client->id,
                        'name' => $client->name,
                    ],
                    ['active' => true],
                );
            }
        }
    }

    private function sanitizeProvisioningLocationName(Client $client, string $header): string
    {
        if ($header === '') {
            return 'Seccion';
        }

        if (strcasecmp($header, $client->name) === 0) {
            return $client->name.' (seccion)';
        }

        return $header;
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
                'file' => ['Formato compacto invalido: se esperaban columnas entre "Salida" y "Observaciones".'],
            ]);
        }

        $quantityColumns = array_values(array_slice(
            $headers,
            $salidaIndex + 1,
            $observacionesIndex - $salidaIndex - 1,
        ));

        if ($quantityColumns === []) {
            throw ValidationException::withMessages([
                'file' => ['Formato compacto invalido: no se encontraron columnas entre "Salida" y "Observaciones".'],
            ]);
        }

        return [
            'entry_column' => $entryColumn,
            'quantity_columns' => $quantityColumns,
        ];
    }

    /**
     * @param  array{
     *     payload: VisitImportPayload,
     *     warnings: array<int, string>,
     *     summarySections: array<int, string>,
     *     summaryBirdTypes: array<int, string>,
     *     clientName: string,
     * }  $prepared
     * @param  array{status: string, total_rows: int, persisted_rows: int, skipped_rows: int}  $persistResult
     * @return array{
     *     warnings: array<int, string>,
     *     summary_message: string,
     *     client_name: string,
     *     sections: array<int, string>,
     *     bird_types: array<int, string>,
     * }
     */
    private function buildImportMetadata(array $prepared, array $persistResult): array
    {
        $sections = implode(', ', $prepared['summarySections']);
        $birds = implode(', ', $prepared['summaryBirdTypes']);
        $parts = [
            'Cliente: '.$prepared['clientName'],
        ];

        if ($sections !== '') {
            $parts[] = 'Secciones: '.$sections;
        }

        if ($birds !== '') {
            $parts[] = 'Aves: '.$birds;
        }

        $parts[] = (string) $persistResult['persisted_rows'].' registros importados';

        return [
            'warnings' => $prepared['warnings'],
            'summary_message' => implode(' | ', $parts),
            'client_name' => $prepared['clientName'],
            'sections' => $prepared['summarySections'],
            'bird_types' => $prepared['summaryBirdTypes'],
        ];
    }

    private function resolveSummaryClientName(
        VisitImportPayload $payload,
        ?int $fallbackClientId,
        string $absolutePath,
    ): string {
        if (is_int($fallbackClientId)) {
            $client = Client::query()->find($fallbackClientId);

            if ($client instanceof Client) {
                return $client->name;
            }
        }

        $headers = $payload->headers;
        $clientNameIndex = array_search('client_name', $headers, true);

        if (is_int($clientNameIndex) && $payload->rows->isNotEmpty()) {
            $firstRow = $payload->rows->first();

            if (is_array($firstRow)) {
                $name = trim((string) ($firstRow[$clientNameIndex] ?? ''));

                if ($name !== '') {
                    return $name;
                }
            }
        }

        return $this->resolveClientFromFileName($absolutePath, false)->name;
    }

    private function resolveClientForCompactImport(?int $fallbackClientId, string $absolutePath, bool $provision): Client
    {
        if (is_int($fallbackClientId)) {
            $client = Client::query()->find($fallbackClientId);

            if ($client instanceof Client) {
                return $client;
            }

            throw ValidationException::withMessages([
                'client_id' => ['El cliente seleccionado no es valido.'],
            ]);
        }

        return $this->resolveClientFromFileName($absolutePath, $provision);
    }

    /**
     * Resumen para la UI cuando el aprovisionamiento esta activo: nombre legible inferido del archivo
     * (prefijo antes de Constancia_de_Servicio, guiones bajos como espacios y separacion de CamelCase)
     * y si ya existe un cliente con el mismo nombre normalizado (coincidencia exacta, sin busqueda parcial)
     * o se creara uno nuevo al importar.
     *
     * @return array{
     *     kind: 'existing_client'|'will_create_client'|'error'|'unparseable',
     *     display_name: ?string,
     *     matched_client_name?: string,
     *     message?: string,
     * }
     */
    public function compactClientProvisioningHintFromPath(string $absolutePath): array
    {
        try {
            $displayName = $this->extractClientDisplayNameFromFileName($absolutePath);
        } catch (ValidationException $exception) {
            return [
                'kind' => 'unparseable',
                'display_name' => null,
                'message' => $this->firstValidationMessageFromException($exception),
            ];
        }

        $outcome = $this->evaluateClientResolutionOutcome($absolutePath);

        if ($outcome['status'] === 'matched') {
            return [
                'kind' => 'existing_client',
                'display_name' => $displayName,
                'matched_client_name' => $outcome['client']->name,
            ];
        }

        if ($outcome['status'] === 'ambiguous_exact') {
            return [
                'kind' => 'error',
                'display_name' => $displayName,
                'message' => $outcome['message'],
            ];
        }

        return [
            'kind' => 'will_create_client',
            'display_name' => $displayName,
        ];
    }

    /**
     * @return array{status: 'matched', client: Client}|array{status: 'ambiguous_exact', message: string}|array{status: 'none'}
     */
    private function evaluateClientResolutionOutcome(string $absolutePath): array
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
                return ['status' => 'matched', 'client' => $exactMatch];
            }
        }

        if ($exactMatches->count() > 1) {
            return [
                'status' => 'ambiguous_exact',
                'message' => 'El nombre del archivo coincide con mas de un cliente.',
            ];
        }

        return ['status' => 'none'];
    }

    private function resolveClientFromFileName(string $absolutePath, bool $provision = false): Client
    {
        $outcome = $this->evaluateClientResolutionOutcome($absolutePath);

        if ($outcome['status'] === 'matched') {
            return $outcome['client'];
        }

        if ($outcome['status'] === 'ambiguous_exact') {
            throw ValidationException::withMessages([
                'file' => [$outcome['message']],
            ]);
        }

        if ($provision) {
            $displayName = $this->extractClientDisplayNameFromFileName($absolutePath);

            return Client::query()->firstOrCreate(
                ['name' => $displayName],
                ['active' => true],
            );
        }

        throw ValidationException::withMessages([
            'file' => ['No hay un cliente cuyo nombre coincida exactamente con el prefijo del archivo (antes de Constancia_de_Servicio). Ajusta el nombre del archivo o registra el cliente con el mismo nombre.'],
        ]);
    }

    private function firstValidationMessageFromException(ValidationException $exception): string
    {
        return (string) (collect($exception->errors())->flatten()->first() ?? 'No se pudo inferir el cliente desde el nombre del archivo.');
    }

    /**
     * @return array{humanized: string, token: string}
     */
    private function clientFileNameSegmentMeta(string $absolutePath): array
    {
        $baseName = $this->stripTrailingUuidSuffixFromImportBasename(
            (string) pathinfo($absolutePath, PATHINFO_FILENAME),
        );
        $lowerBase = Str::lower($baseName);

        $clientSegment = $baseName;
        foreach (['_constancia_de_servicio', '-constancia-de-servicio'] as $marker) {
            if (! str_contains($lowerBase, $marker)) {
                continue;
            }

            $pos = strpos($lowerBase, $marker);
            if ($pos !== false) {
                $clientSegment = substr($baseName, 0, $pos);
            }

            break;
        }

        $clientSegment = (string) preg_replace('/(?:_\d{8}|-\d{8})$/', '', $clientSegment);
        $clientSegment = trim($clientSegment, '_-');

        if ($clientSegment === '') {
            throw ValidationException::withMessages([
                'file' => ['No se pudo inferir el cliente desde el nombre del archivo.'],
            ]);
        }

        $humanized = $this->humanizeClientSegmentFromFileName($clientSegment);

        if ($humanized === '') {
            throw ValidationException::withMessages([
                'file' => ['No se pudo inferir el cliente desde el nombre del archivo.'],
            ]);
        }

        $humanized = Str::title(Str::lower($humanized));

        return [
            'humanized' => $humanized,
            'token' => $this->normalizeHeaderToken($humanized),
        ];
    }

    private function stripTrailingUuidSuffixFromImportBasename(string $filenameWithoutExtension): string
    {
        return (string) preg_replace(
            '/-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            '',
            $filenameWithoutExtension,
        );
    }

    private function extractClientTokenFromFileName(string $absolutePath): string
    {
        return $this->clientFileNameSegmentMeta($absolutePath)['token'];
    }

    private function extractClientDisplayNameFromFileName(string $absolutePath): string
    {
        return $this->clientFileNameSegmentMeta($absolutePath)['humanized'];
    }

    /**
     * Convierte el prefijo del archivo (underscores y CamelCase) a texto comparable con {@see normalizeHeaderToken()}.
     */
    private function humanizeClientSegmentFromFileName(string $segment): string
    {
        $segment = str_replace('-', '_', trim($segment, '_-'));
        if ($segment === '') {
            return '';
        }

        $parts = explode('_', $segment);
        $expanded = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $expanded[] = $this->expandCamelCaseWords($part);
        }

        return trim(implode(' ', $expanded));
    }

    private function expandCamelCaseWords(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $spaced = (string) preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $value);
        $spaced = (string) preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $spaced);

        return trim($spaced);
    }

    private function normalizeHeaderToken(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    /**
     * @param  array{
     *     payload: VisitImportPayload,
     *     warnings: array<int, string>,
     *     summarySections: array<int, string>,
     *     summaryBirdTypes: array<int, string>,
     *     clientName: string,
     * }  $prepared
     * @param  array{total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>}  $preview
     * @param  array{user_id?: int|null, original_filename?: string|null, stored_file_path?: string|null, provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $context
     */
    private function createVisitImportRecord(
        array $prepared,
        array $preview,
        string $absolutePath,
        ?int $fallbackClientId,
        array $context,
    ): VisitImport {
        $userId = isset($context['user_id']) && is_int($context['user_id']) ? $context['user_id'] : null;
        $originalFilename = isset($context['original_filename']) && is_string($context['original_filename']) && $context['original_filename'] !== ''
            ? $context['original_filename']
            : basename($absolutePath);
        $storedPath = isset($context['stored_file_path']) && is_string($context['stored_file_path'])
            ? $context['stored_file_path']
            : null;

        $clientId = $this->resolveClientIdForLog($fallbackClientId, (string) $prepared['clientName']);

        return VisitImport::query()->create([
            'client_id' => $clientId,
            'user_id' => $userId,
            'original_filename' => $originalFilename,
            'stored_file_path' => $storedPath,
            'summary_message' => '',
            'total_rows' => (int) ($preview['total_rows'] ?? 0),
            'persisted_rows' => 0,
            'skipped_rows' => 0,
            'invalid_rows' => (int) ($preview['invalid_rows'] ?? 0),
            'import_status' => 'processing',
            'errors' => array_values($preview['errors'] ?? []),
            'warnings' => array_values($prepared['warnings'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $mergedResult
     * @param  array{total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>}  $preview
     * @param  array{status: string, total_rows: int, persisted_rows: int, skipped_rows: int}  $persistResult
     */
    private function finalizeVisitImportRecord(
        VisitImport $visitImport,
        array $mergedResult,
        array $preview,
        array $persistResult,
    ): void {
        $warnings = array_values($mergedResult['warnings'] ?? []);
        $persisted = (int) ($persistResult['persisted_rows'] ?? 0);
        $skipped = (int) ($persistResult['skipped_rows'] ?? 0);
        $total = (int) ($persistResult['total_rows'] ?? 0);
        $invalid = (int) ($preview['invalid_rows'] ?? 0);

        $importStatus = match (true) {
            $total > 0 && $persisted === 0 => 'failed',
            $skipped > 0 || $invalid > 0 => 'partial',
            default => 'success',
        };

        $visitImport->update([
            'summary_message' => (string) ($mergedResult['summary_message'] ?? ''),
            'total_rows' => $total,
            'persisted_rows' => $persisted,
            'skipped_rows' => $skipped,
            'invalid_rows' => $invalid,
            'import_status' => $importStatus,
            'errors' => array_values($preview['errors'] ?? []),
            'warnings' => $warnings,
        ]);
    }

    private function resolveClientIdForLog(?int $fallbackClientId, string $clientName): ?int
    {
        if (is_int($fallbackClientId)) {
            $id = Client::query()->whereKey($fallbackClientId)->value('id');

            return is_int($id) || is_numeric($id) ? (int) $id : null;
        }

        if ($clientName !== '') {
            $id = Client::query()->where('name', $clientName)->value('id');

            return is_int($id) || is_numeric($id) ? (int) $id : null;
        }

        return null;
    }
}
