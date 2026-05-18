<?php

namespace App\Services\VisitImport\Helpers;

use App\Services\ImportVisitExcelService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VisitImportFileHelper
{
    public function __construct(
        private readonly ImportVisitExcelService $importVisitExcelService,
    ) {}

    /**
     * @param  array<int, mixed>  $files
     * @param  array{provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $context
     * @return array<int, array{file_name: string, display_file_name: string, file_path: string, can_import: bool, total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>, warnings: array<int, string>}>
     */
    public function verifyFiles(array $files, array $context = []): array
    {
        return collect($files)
            ->map(function (mixed $file) use ($context): array {
                [$filePath, $fileName] = $this->normalizeFileForImport($file);

                try {
                    $preview = $this->importVisitExcelService->preview(
                        Storage::disk('local')->path($filePath),
                        null,
                        $context,
                    );

                    return [
                        'file_name' => $fileName,
                        'display_file_name' => $this->displayFileNameForImportUi($filePath, $fileName),
                        'file_path' => $filePath,
                        'can_import' => $preview['valid_rows'] > 0,
                        'total_rows' => $preview['total_rows'],
                        'valid_rows' => $preview['valid_rows'],
                        'invalid_rows' => $preview['invalid_rows'],
                        'errors' => $preview['errors'],
                        'warnings' => $preview['warnings'] ?? [],
                    ];
                } catch (ValidationException $exception) {
                    return [
                        'file_name' => $fileName,
                        'display_file_name' => $this->displayFileNameForImportUi($filePath, $fileName),
                        'file_path' => $filePath,
                        'can_import' => false,
                        'total_rows' => 0,
                        'valid_rows' => 0,
                        'invalid_rows' => 0,
                        'errors' => [$this->getFirstValidationError($exception)],
                        'warnings' => [],
                    ];
                } catch (Throwable $exception) {
                    report($exception);

                    return [
                        'file_name' => $fileName,
                        'display_file_name' => $this->displayFileNameForImportUi($filePath, $fileName),
                        'file_path' => $filePath,
                        'can_import' => false,
                        'total_rows' => 0,
                        'valid_rows' => 0,
                        'invalid_rows' => 0,
                        'errors' => ['Error inesperado al validar el archivo.'],
                        'warnings' => [],
                    ];
                }
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $files
     * @return array<int, array{file_name: string, hint: array<string, mixed>}>
     */
    public function compactClientProvisioningHintsForFiles(array $files): array
    {
        return collect($files)
            ->map(function (mixed $file): array {
                [$filePath, $fileName] = $this->normalizeFileForImport($file);
                $absolutePath = Storage::disk('local')->path($filePath);
                $hint = $this->importVisitExcelService->compactClientProvisioningHintFromPath($absolutePath);

                return [
                    'file_name' => $this->displayFileNameForImportUi($filePath, $fileName),
                    'hint' => $hint,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizeFileForImport(mixed $file): array
    {
        if (is_string($file) && Storage::disk('local')->exists($file)) {
            return [$file, basename($file)];
        }

        if ($this->isLivewireTemporaryUploadedFile($file)) {
            $originalName = (string) $file->getClientOriginalName();
            $storedPath = $this->storeAsImportFile(
                (string) $file->getRealPath(),
                $originalName,
            );

            return [$storedPath, $originalName];
        }

        if (is_string($file)) {
            $tempPath = $this->resolveLivewireTemporaryPath($file);

            if ($tempPath !== null) {
                $storedPath = $this->storeTemporaryDiskFileAsImportFile($tempPath, $file);

                return [$storedPath, basename($file)];
            }
        }

        return [(string) $file, basename((string) $file)];
    }

    private function isLivewireTemporaryUploadedFile(mixed $file): bool
    {
        return is_object($file)
            && method_exists($file, 'getClientOriginalName')
            && method_exists($file, 'getRealPath');
    }

    private function resolveLivewireTemporaryPath(string $file): ?string
    {
        $livewireTemporaryDirectory = trim((string) config('livewire.temporary_file_upload.directory', 'livewire-tmp'), '/');
        $candidatePath = $livewireTemporaryDirectory.'/'.ltrim($file, '/');

        if (Storage::disk('local')->exists($candidatePath)) {
            return $candidatePath;
        }

        return null;
    }

    private function storeTemporaryDiskFileAsImportFile(string $temporaryPath, string $originalNameHint): string
    {
        $absoluteTemporaryPath = Storage::disk('local')->path($temporaryPath);
        $extension = $this->resolveExtensionFromStoredFile($temporaryPath, $originalNameHint);
        $storedFileName = $this->buildStoredFileName($originalNameHint, $extension);
        $storedPath = 'imports/visit-reports/'.$storedFileName;

        Storage::disk('local')->makeDirectory('imports/visit-reports');
        copy($absoluteTemporaryPath, Storage::disk('local')->path($storedPath));

        return $storedPath;
    }

    private function storeAsImportFile(string $realPath, string $originalName): string
    {
        $extension = $this->resolveExtensionFromName($originalName);
        $storedFileName = $this->buildStoredFileName($originalName, $extension);
        $storedPath = 'imports/visit-reports/'.$storedFileName;

        Storage::disk('local')->makeDirectory('imports/visit-reports');
        copy($realPath, Storage::disk('local')->path($storedPath));

        return $storedPath;
    }

    private function resolveExtensionFromStoredFile(string $relativePath, string $originalNameHint): string
    {
        $extensionFromName = $this->resolveExtensionFromName($originalNameHint);

        if ($extensionFromName !== 'tmp') {
            return $extensionFromName;
        }

        $mimeType = Storage::disk('local')->mimeType($relativePath);

        return match ($mimeType) {
            'text/csv', 'text/plain' => 'csv',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => $extensionFromName,
        };
    }

    private function resolveExtensionFromName(string $fileName): string
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : 'tmp';
    }

    private function buildStoredFileName(string $originalName, string $extension): string
    {
        $baseName = Str::of(pathinfo($originalName, PATHINFO_FILENAME))
            ->trim()
            ->slug()
            ->value();
        $baseName = $baseName !== '' ? $baseName : 'import-file';

        return $baseName.'-'.Str::uuid().'.'.$extension;
    }

    /**
     * Nombre legible para la UI: si solo tenemos la ruta interna slug+uuid, se quita el uuid y se restauran guiones bajos; si tenemos el nombre original del upload, se usa tal cual.
     */
    private function displayFileNameForImportUi(string $storedRelativePath, string $fileNameOrBasename): string
    {
        $storedBasename = basename($storedRelativePath);

        if ($fileNameOrBasename !== $storedBasename) {
            return $fileNameOrBasename;
        }

        $fn = pathinfo($storedBasename, PATHINFO_FILENAME);
        $ext = pathinfo($storedBasename, PATHINFO_EXTENSION);
        $fnStripped = (string) preg_replace(
            '/-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            '',
            (string) $fn,
        );
        $underscored = str_replace('-', '_', $fnStripped);
        $titled = collect(explode('_', $underscored))
            ->filter(fn (string $p): bool => $p !== '')
            ->map(fn (string $p): string => Str::ucfirst(Str::lower($p)))
            ->implode('_');

        return $titled.($ext !== '' ? '.'.strtolower((string) $ext) : '');
    }

    private function getFirstValidationError(ValidationException $exception): string
    {
        return (string) (collect($exception->errors())->flatten()->first() ?? 'La validacion fallo.');
    }
}
