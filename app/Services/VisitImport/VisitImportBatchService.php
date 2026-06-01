<?php

namespace App\Services\VisitImport;

use App\Jobs\ProcessStoredVisitImportJob;
use App\Models\Client;
use App\Services\ImportVisitExcelService;
use App\Services\VisitImport\Helpers\VisitImportFileHelper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileInfo;

final class VisitImportBatchService
{
    private const array ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv'];

    public function __construct(
        private readonly VisitImportFileHelper $visitImportFileHelper,
        private readonly ImportVisitExcelService $importVisitExcelService,
    ) {}

    public function resolveDirectory(?string $path): string
    {
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Debe indicar un directorio con --directory.');
        }

        $absolutePath = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);

        if (! File::isDirectory($absolutePath)) {
            throw new RuntimeException('El directorio no existe: '.$absolutePath);
        }

        return $absolutePath;
    }

    /**
     * @return array<int, SplFileInfo>
     */
    public function discoverImportFiles(string $directory): array
    {
        return collect(File::files($directory))
            ->filter(fn (SplFileInfo $file): bool => in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true))
            ->sortBy(fn (SplFileInfo $file): string => $file->getFilename())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $absolutePaths
     * @return array<int, array{file_path: string, file_name: string}>
     */
    public function stageFiles(array $absolutePaths): array
    {
        Storage::disk('local')->makeDirectory('imports/visit-reports');

        return collect($absolutePaths)
            ->map(function (string $absolutePath): array {
                $originalName = basename($absolutePath);
                $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
                $extension = $extension !== '' ? $extension : 'tmp';
                $storedFileName = $this->buildStoredFileName($originalName, $extension);
                $storedPath = 'imports/visit-reports/'.$storedFileName;

                copy($absolutePath, Storage::disk('local')->path($storedPath));

                return [
                    'file_path' => $storedPath,
                    'file_name' => $originalName,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $absolutePaths
     * @return array{deleted: int, names: array<int, string>, would_delete: array<int, string>}
     */
    public function deleteClientsMatchingFiles(array $absolutePaths, bool $dryRun = false): array
    {
        $names = [];
        $wouldDelete = [];

        foreach ($absolutePaths as $absolutePath) {
            $client = $this->importVisitExcelService->resolveExistingClientFromFilePath($absolutePath);

            if (! $client instanceof Client) {
                continue;
            }

            $wouldDelete[] = $client->name;

            if ($dryRun) {
                continue;
            }

            $client->delete();
            $names[] = $client->name;
        }

        $wouldDelete = array_values(array_unique($wouldDelete));
        $names = array_values(array_unique($names));

        return [
            'deleted' => count($names),
            'names' => $names,
            'would_delete' => $wouldDelete,
        ];
    }

    /**
     * @param  array<int, array{file_path: string, file_name: string}>  $stagedFiles
     * @param  array{provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $context
     * @return array<int, array{file_name: string, display_file_name: string, file_path: string, can_import: bool, total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>, warnings: array<int, string>}>
     */
    public function verifyBatch(array $stagedFiles, array $context = []): array
    {
        $filePaths = collect($stagedFiles)
            ->pluck('file_path')
            ->values()
            ->all();

        $preview = $this->visitImportFileHelper->verifyFiles($filePaths, $context);

        return collect($preview)
            ->map(function (array $entry, int $index) use ($stagedFiles): array {
                $staged = $stagedFiles[$index] ?? null;

                if (is_array($staged) && isset($staged['file_name'])) {
                    $entry['file_name'] = (string) $staged['file_name'];
                }

                return $entry;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{file_name: string, file_path: string, can_import: bool, total_rows?: int}>  $preview
     * @param  array{provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $context
     * @return array{success: bool, queued: bool, message: string, imported_files: int, expected_files: int, total_rows: int, persisted_rows: int, skipped_rows: int, duration_seconds: float, file_errors: array<int, string>, import_warnings: array<int, string>}
     */
    public function dispatchBatch(array $preview, array $context, ?int $userId, bool $sync = false): array
    {
        $startedAt = microtime(true);

        $entriesToImport = collect($preview)
            ->filter(fn (array $entry): bool => (bool) ($entry['can_import'] ?? false))
            ->filter(fn (array $entry): bool => isset($entry['file_path']) && is_string($entry['file_path']) && $entry['file_path'] !== '')
            ->values();

        if ($entriesToImport->isEmpty()) {
            return [
                'success' => false,
                'queued' => false,
                'message' => 'No hay archivos validos para importar. Corrige los archivos e intenta nuevamente.',
                'imported_files' => 0,
                'expected_files' => 0,
                'total_rows' => 0,
                'persisted_rows' => 0,
                'skipped_rows' => 0,
                'duration_seconds' => round(microtime(true) - $startedAt, 1),
                'file_errors' => [],
                'import_warnings' => [],
            ];
        }

        $provision = (bool) ($context['provision_client_and_sections'] ?? false);
        $replace = (bool) ($context['replace_previous_import_same_filename'] ?? false);

        $dispatched = 0;
        foreach ($entriesToImport as $entry) {
            $filePath = (string) $entry['file_path'];
            $originalName = (string) ($entry['file_name'] ?? basename($filePath));

            if ($sync) {
                ProcessStoredVisitImportJob::dispatchSync(
                    $filePath,
                    $userId,
                    $originalName,
                    $provision,
                    $replace,
                    null,
                );
            } else {
                ProcessStoredVisitImportJob::dispatch(
                    $filePath,
                    $userId,
                    $originalName,
                    $provision,
                    $replace,
                    null,
                );
            }

            $dispatched++;
        }

        $expected = $entriesToImport->count();
        $previewRowTotal = (int) $entriesToImport->sum(fn (array $entry): int => (int) ($entry['total_rows'] ?? 0));

        if ($sync) {
            $body = $dispatched === 1
                ? 'Se importo 1 archivo de forma sincrona.'
                : 'Se importaron '.$dispatched.' archivos de forma sincrona.';
        } elseif ($dispatched === 1) {
            $body = 'Tu archivo se recibio y lo estamos procesando. En unos minutos podras ver el resultado en Importaciones de visitas.';
        } else {
            $body = 'Se recibieron '.$dispatched.' archivos y los estamos procesando. En unos minutos podras ver los resultados en Importaciones de visitas.';
        }

        if ($previewRowTotal > 0) {
            $body .= $dispatched === 1
                ? ' Al revisar el archivo vimos '.$previewRowTotal.' filas con datos para importar.'
                : ' Al revisar los archivos vimos '.$previewRowTotal.' filas con datos para importar en total.';
        }

        return [
            'success' => true,
            'queued' => ! $sync,
            'message' => $body,
            'imported_files' => $dispatched,
            'expected_files' => $expected,
            'total_rows' => $previewRowTotal,
            'persisted_rows' => 0,
            'skipped_rows' => 0,
            'duration_seconds' => round(microtime(true) - $startedAt, 1),
            'file_errors' => [],
            'import_warnings' => [],
        ];
    }

    private function buildStoredFileName(string $originalName, string $extension): string
    {
        $baseName = Str::of(pathinfo($originalName, PATHINFO_FILENAME))
            ->trim()
            ->replace('/', '_')
            ->replace('\\', '_')
            ->value();
        $baseName = $baseName !== '' ? $baseName : 'import-file';

        return $baseName.'-'.Str::uuid().'.'.$extension;
    }
}
