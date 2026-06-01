<?php

namespace App\Services\HistoricVisitImport;

use App\Models\Client;
use App\Models\Visit;
use App\Models\VisitImport;
use App\Services\BirdTypes\BirdTypeResolver;
use App\Services\VisitImport\Validation\VisitImportStructureValidator;
use App\Services\VisitImport\VisitImportPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class HistoricVisitImportService
{
    public const string HISTORIC_OBSERVATION = 'datos historicos, sin referencia de hora y usuarios';

    public const string HISTORIC_IMPORT_SOURCE = 'historic://';

    private const string FAKE_EMPLOYEE_NAME = 'Importacion historica';

    private const string FAKE_TIME_INIT = '09:00:00';

    private const string FAKE_TIME_END = '10:00:00';

    public function __construct(
        private readonly HistoricVisitSpreadsheetReader $reader,
        private readonly HistoricVisitImportPersistence $persistence,
        private readonly BirdTypeResolver $birdTypeResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function processDirectory(string $directory, bool $dryRun = true, ?int $userId = null): array
    {
        if (! File::isDirectory($directory)) {
            throw new RuntimeException('El directorio no existe: '.$directory);
        }

        $previousHistoricCleanup = $dryRun
            ? $this->countPreviousHistoricImports()
            : $this->deletePreviousHistoricImports();

        $files = collect(File::files($directory))
            ->filter(fn (\SplFileInfo $file): bool => in_array(strtolower($file->getExtension()), ['xls', 'xlsx'], true))
            ->sortBy(fn (\SplFileInfo $file): string => $file->getFilename())
            ->values();

        $fileResults = [];
        $successfulFiles = 0;
        $failedFiles = 0;
        $skippedFiles = 0;
        $totalSourceRows = 0;
        $totalValidRows = 0;
        $totalInvalidRows = 0;
        $totalPersistedRows = 0;
        $totalSkippedRows = 0;
        $totalSkippedExistingDates = 0;
        $totalVisitReports = 0;
        $clientsImported = [];
        $clientsFailed = [];

        foreach ($files as $file) {
            $result = $this->processFile($file->getPathname(), $dryRun, $userId);
            $fileResults[] = $result;

            if ($result['status'] === 'success') {
                $successfulFiles++;
                $clientName = (string) ($result['client_name'] ?? '');

                if ($clientName !== '') {
                    $clientsImported[$clientName] = ($clientsImported[$clientName] ?? 0) + 1;
                }
            } elseif ($result['status'] === 'failed') {
                $failedFiles++;
                $clientsFailed[] = [
                    'filename' => $result['filename'],
                    'client_name' => $result['client_name'] ?? null,
                    'error' => $result['error'] ?? 'Error desconocido.',
                ];
            } else {
                $skippedFiles++;
            }

            $totalSourceRows += (int) ($result['total_rows'] ?? 0);
            $totalValidRows += (int) ($result['valid_rows'] ?? 0);
            $totalInvalidRows += (int) ($result['invalid_rows'] ?? 0);
            $totalPersistedRows += (int) ($result['persisted_rows'] ?? 0);
            $totalSkippedRows += (int) ($result['skipped_rows'] ?? 0);
            $totalSkippedExistingDates += (int) ($result['skipped_existing_dates'] ?? 0);
            $totalVisitReports += (int) ($result['visit_reports'] ?? 0);
        }

        return [
            'dry_run' => $dryRun,
            'directory' => $directory,
            'previous_historic_imports' => $previousHistoricCleanup['imports'] ?? 0,
            'previous_historic_visits' => $previousHistoricCleanup['visits'] ?? 0,
            'total_files' => $files->count(),
            'successful_files' => $successfulFiles,
            'failed_files' => $failedFiles,
            'skipped_files' => $skippedFiles,
            'total_source_rows' => $totalSourceRows,
            'total_valid_rows' => $totalValidRows,
            'total_invalid_rows' => $totalInvalidRows,
            'total_persisted_rows' => $totalPersistedRows,
            'total_skipped_rows' => $totalSkippedRows,
            'total_skipped_existing_dates' => $totalSkippedExistingDates,
            'total_visit_reports' => $totalVisitReports,
            'clients_imported' => array_keys($clientsImported),
            'clients_failed' => $clientsFailed,
            'files' => $fileResults,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function processFile(string $absolutePath, bool $dryRun = true, ?int $userId = null): array
    {
        $filename = basename($absolutePath);

        try {
            $clientDisplayName = $this->extractClientDisplayNameFromFilename($filename);
            $client = $this->resolveExistingClientOrFail($clientDisplayName);
            $spreadsheetData = $this->reader->read($absolutePath);
            $sectionNames = $this->resolveSectionNames($client, $spreadsheetData['section_column_indices']);
            $existingVisitDates = $this->existingVisitDatesForClient($client);
            $buildResult = $this->buildPayload(
                $client->name,
                $spreadsheetData['rows'],
                $spreadsheetData['section_column_indices'],
                $sectionNames,
                $existingVisitDates,
            );

            $payload = $buildResult['payload'];
            $preview = $this->persistence->preview($payload);

            if ($dryRun) {
                return [
                    'status' => 'success',
                    'filename' => $filename,
                    'client_name' => $client->name,
                    'client_resolution' => 'existing_client',
                    'matched_client_name' => $client->name,
                    'sections' => $sectionNames,
                    'total_rows' => $preview['total_rows'],
                    'valid_rows' => $preview['valid_rows'],
                    'invalid_rows' => $preview['invalid_rows'],
                    'persisted_rows' => $preview['valid_rows'],
                    'skipped_rows' => $preview['invalid_rows'],
                    'skipped_existing_dates' => $buildResult['skipped_existing_dates'],
                    'visit_reports' => $payload->rows->count(),
                    'errors' => $preview['errors'],
                    'warnings' => $preview['warnings'],
                ];
            }

            return DB::transaction(function () use ($filename, $client, $payload, $preview, $sectionNames, $buildResult, $userId): array {
                $visitImport = VisitImport::query()->create([
                    'client_id' => $client->id,
                    'user_id' => $userId,
                    'original_filename' => $filename,
                    'stored_file_path' => self::HISTORIC_IMPORT_SOURCE,
                    'summary_message' => 'Importacion historica',
                    'total_rows' => $preview['total_rows'],
                    'persisted_rows' => 0,
                    'skipped_rows' => 0,
                    'invalid_rows' => $preview['invalid_rows'],
                    'import_status' => 'processing',
                    'errors' => array_values($preview['errors'] ?? []),
                    'warnings' => array_values($preview['warnings'] ?? []),
                ]);

                $persistResult = $this->persistence->persist($payload, $client, $visitImport->id);

                $importStatus = match (true) {
                    $persistResult['total_rows'] > 0 && $persistResult['persisted_rows'] === 0 => 'failed',
                    $persistResult['skipped_rows'] > 0 || $preview['invalid_rows'] > 0 => 'partial',
                    default => 'success',
                };

                $visitImport->update([
                    'summary_message' => 'Importacion historica | '.$persistResult['persisted_rows'].' visitas',
                    'total_rows' => $persistResult['total_rows'],
                    'persisted_rows' => $persistResult['persisted_rows'],
                    'skipped_rows' => $persistResult['skipped_rows'],
                    'invalid_rows' => $preview['invalid_rows'],
                    'import_status' => $importStatus,
                ]);

                return [
                    'status' => $importStatus === 'failed' ? 'failed' : 'success',
                    'filename' => $filename,
                    'client_name' => $client->name,
                    'client_resolution' => 'existing_client',
                    'matched_client_name' => $client->name,
                    'sections' => $sectionNames,
                    'total_rows' => $persistResult['total_rows'],
                    'valid_rows' => $preview['valid_rows'],
                    'invalid_rows' => $preview['invalid_rows'],
                    'persisted_rows' => $persistResult['persisted_rows'],
                    'skipped_rows' => $persistResult['skipped_rows'],
                    'skipped_existing_dates' => $buildResult['skipped_existing_dates'],
                    'visit_reports' => $persistResult['visit_reports'],
                    'visit_import_id' => $visitImport->id,
                    'errors' => $preview['errors'],
                    'warnings' => $preview['warnings'],
                ];
            });
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'filename' => $filename,
                'client_name' => $this->safeClientNameFromFilename($filename),
                'error' => $exception->getMessage(),
                'total_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'persisted_rows' => 0,
                'skipped_rows' => 0,
                'skipped_existing_dates' => 0,
                'visit_reports' => 0,
                'errors' => [$exception->getMessage()],
                'warnings' => [],
            ];
        }
    }

    /**
     * @return array{imports: int, visits: int}
     */
    public function countPreviousHistoricImports(): array
    {
        $importIds = $this->historicImportIds();

        return [
            'imports' => $importIds->count(),
            'visits' => Visit::query()->whereIn('visit_import_id', $importIds->all())->count(),
        ];
    }

    /**
     * @return array{imports: int, visits: int}
     */
    public function deletePreviousHistoricImports(): array
    {
        $importIds = $this->historicImportIds();

        if ($importIds->isEmpty()) {
            return ['imports' => 0, 'visits' => 0];
        }

        $deletedVisits = Visit::query()->whereIn('visit_import_id', $importIds->all())->delete();
        $deletedImports = VisitImport::query()->whereIn('id', $importIds->all())->delete();

        return [
            'imports' => $deletedImports,
            'visits' => $deletedVisits,
        ];
    }

    /**
     * @param  array<int, int>  $sectionColumnIndices
     * @param  array<int, string>  $sectionNames
     * @param  array<string, true>  $existingVisitDates
     * @return array{payload: VisitImportPayload, skipped_existing_dates: int}
     */
    private function buildPayload(
        string $clientName,
        Collection $rows,
        array $sectionColumnIndices,
        array $sectionNames,
        array $existingVisitDates,
    ): array {
        $requiredColumns = VisitImportStructureValidator::requiredColumns();
        $birdTypeName = $this->birdTypeResolver->default()->name;
        $expandedRows = collect();
        $rowGroups = [];
        $sourceRowIndex = 0;
        $skippedExistingDates = 0;

        foreach ($rows as $row) {
            $dateSerial = (float) ($row['date'] ?? 0);
            $dateKey = $this->reader->excelSerialToDateString($dateSerial, '00:00:00');
            $dateKey = substr($dateKey, 0, 10);

            if (isset($existingVisitDates[$dateKey])) {
                $skippedExistingDates++;
                $sourceRowIndex++;

                continue;
            }

            $dateInit = $this->reader->excelSerialToDateString($dateSerial, self::FAKE_TIME_INIT);
            $dateEnd = $this->reader->excelSerialToDateString($dateSerial, self::FAKE_TIME_END);
            $currentSourceRowIndex = $sourceRowIndex;

            foreach ($sectionColumnIndices as $position => $columnIndex) {
                if (! array_key_exists($columnIndex, $row['quantities'])) {
                    continue;
                }

                $sectionName = $sectionNames[$position] ?? null;

                if (! is_string($sectionName)) {
                    continue;
                }

                $expandedRows->push(
                    collect($requiredColumns)
                        ->map(fn (string $column): string => match ($column) {
                            'client_name' => $clientName,
                            'employee_name' => self::FAKE_EMPLOYEE_NAME,
                            'employee_email' => '',
                            'date_init' => $dateInit,
                            'date_end' => $dateEnd,
                            'status' => 'completed',
                            'location_name' => $sectionName,
                            'bird_type_name' => $birdTypeName,
                            'quantity' => (string) $row['quantities'][$columnIndex],
                            'observation' => self::HISTORIC_OBSERVATION,
                            'visit_observation' => self::HISTORIC_OBSERVATION,
                            default => '',
                        })
                        ->all(),
                );
                $rowGroups[] = $currentSourceRowIndex;
            }

            $sourceRowIndex++;
        }

        return [
            'payload' => new VisitImportPayload($requiredColumns, $expandedRows->values(), $rowGroups),
            'skipped_existing_dates' => $skippedExistingDates,
        ];
    }

    /**
     * @param  array<int, int>  $sectionColumnIndices
     * @return array<int, string>
     */
    private function resolveSectionNames(Client $client, array $sectionColumnIndices): array
    {
        $sections = $client->locations()
            ->where('active', true)
            ->orderBy('id')
            ->pluck('name')
            ->values()
            ->all();

        if ($sections === []) {
            throw new RuntimeException(
                'El cliente "'.$client->name.'" no tiene secciones activas configuradas.',
            );
        }

        $requiredCount = count($sectionColumnIndices);

        if (count($sections) < $requiredCount) {
            throw new RuntimeException(
                'El archivo tiene '.$requiredCount.' columnas de cantidad pero el cliente "'.$client->name.'" solo tiene '.count($sections).' seccion(es) activa(s).',
            );
        }

        return array_slice($sections, 0, $requiredCount);
    }

    /**
     * @return array<string, true>
     */
    private function existingVisitDatesForClient(Client $client): array
    {
        /** @var array<string, true> $dates */
        $dates = Visit::query()
            ->where('client_id', $client->id)
            ->get(['date_init'])
            ->mapWithKeys(fn (Visit $visit): array => [
                $visit->date_init->format('Y-m-d') => true,
            ])
            ->all();

        return $dates;
    }

    private function resolveExistingClientOrFail(string $clientDisplayName): Client
    {
        $token = $this->normalizeHeaderToken($clientDisplayName);

        /** @var Collection<int, Client> $matches */
        $matches = Client::query()
            ->get(['id', 'name'])
            ->filter(fn (Client $client): bool => $this->normalizeHeaderToken($client->name) === $token)
            ->values();

        if ($matches->count() === 1) {
            $client = $matches->first();

            if ($client instanceof Client) {
                return $client;
            }
        }

        if ($matches->count() > 1) {
            throw new RuntimeException('El nombre del archivo coincide con mas de un cliente.');
        }

        throw new RuntimeException(
            'No existe un cliente registrado con el nombre "'.$clientDisplayName.'". Registre el cliente y sus secciones antes de importar.',
        );
    }

    /**
     * @return Collection<int, int>
     */
    private function historicImportIds(): Collection
    {
        return VisitImport::query()
            ->where(function ($query): void {
                $query->where('stored_file_path', self::HISTORIC_IMPORT_SOURCE)
                    ->orWhere('summary_message', 'like', 'Importacion historica%');
            })
            ->pluck('id');
    }

    public function extractClientDisplayNameFromFilename(string $filename): string
    {
        $baseName = trim((string) pathinfo($filename, PATHINFO_FILENAME));

        if ($baseName === '') {
            throw ValidationException::withMessages([
                'file' => ['No se pudo inferir el cliente desde el nombre del archivo.'],
            ]);
        }

        $baseName = (string) preg_replace('/^(?:enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)\s+\d{4}\s*-\s*/iu', '', $baseName);
        $baseName = trim($baseName);

        if ($baseName === '') {
            throw ValidationException::withMessages([
                'file' => ['No se pudo inferir el cliente desde el nombre del archivo.'],
            ]);
        }

        return Str::title(Str::lower($baseName));
    }

    private function safeClientNameFromFilename(string $filename): ?string
    {
        try {
            return $this->extractClientDisplayNameFromFilename($filename);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeHeaderToken(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }
}
