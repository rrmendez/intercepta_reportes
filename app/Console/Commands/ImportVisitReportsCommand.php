<?php

namespace App\Console\Commands;

use App\Services\VisitImport\VisitImportBatchService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportVisitReportsCommand extends Command
{
    protected $signature = 'visits:import
                            {--directory=abril_2026 : Carpeta con archivos Excel/CSV}
                            {--provision-client-and-sections : Crear cliente y secciones si no existen}
                            {--replace : Eliminar visitas de importaciones anteriores con el mismo nombre de archivo}
                            {--delete-clients : Borrar clientes que coincidan con los archivos del lote antes de importar}
                            {--user= : ID de usuario para notificaciones Filament}
                            {--queue : Encolar jobs (recomendado para lotes grandes)}
                            {--sync : Ejecutar imports inline sin cola}
                            {--dry-run : Solo validar y mostrar preview}
                            {--execute : Ejecutar la importación real}';

    protected $description = 'Importa en lote reportes de visitas desde una carpeta local';

    public function handle(VisitImportBatchService $batchService): int
    {
        $dryRun = ! $this->option('execute') || (bool) $this->option('dry-run');
        $sync = (bool) $this->option('sync');

        if ($sync && (bool) $this->option('queue')) {
            $this->error('No puedes usar --sync y --queue al mismo tiempo.');

            return self::FAILURE;
        }

        try {
            $directory = $batchService->resolveDirectory($this->option('directory'));
            $files = $batchService->discoverImportFiles($directory);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($files === []) {
            $this->warn('No se encontraron archivos .xlsx, .xls o .csv en el directorio indicado.');

            return self::SUCCESS;
        }

        $absolutePaths = collect($files)
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->values()
            ->all();

        $this->info('Archivos detectados: '.count($absolutePaths));

        $deleteClientsSummary = ['deleted' => 0, 'names' => [], 'would_delete' => []];
        if ((bool) $this->option('delete-clients')) {
            $deleteClientsSummary = $batchService->deleteClientsMatchingFiles($absolutePaths, $dryRun);
            $this->renderClientDeletionSummary($deleteClientsSummary, $dryRun);
        }

        $staged = $batchService->stageFiles($absolutePaths);
        $context = [
            'provision_client_and_sections' => (bool) $this->option('provision-client-and-sections'),
            'replace_previous_import_same_filename' => (bool) $this->option('replace'),
        ];
        $preview = $batchService->verifyBatch($staged, $context);

        $this->renderPreviewTable($preview);

        $importables = collect($preview)->where('can_import', true)->count();
        if ($importables === 0) {
            $this->error('Ningún archivo quedó listo para importar. Corrige los errores e intenta nuevamente.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry-run completado. Usa --execute para ejecutar la importación real.');

            return self::SUCCESS;
        }

        $result = $batchService->dispatchBatch(
            $preview,
            $context,
            $this->resolveUserIdOption(),
            $sync,
        );

        if (! $result['success']) {
            $this->error((string) $result['message']);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info((string) $result['message']);
        $this->line('Archivos procesados: '.(int) $result['imported_files'].' de '.(int) $result['expected_files']);
        $this->line('Filas estimadas a importar: '.(int) $result['total_rows']);

        return self::SUCCESS;
    }

    /**
     * @param  array{deleted: int, names: array<int, string>, would_delete: array<int, string>}  $summary
     */
    private function renderClientDeletionSummary(array $summary, bool $dryRun): void
    {
        $this->newLine();

        if ($dryRun) {
            $count = count($summary['would_delete']);
            $this->info('Clientes que se borrarían antes de importar: '.$count);
            foreach ($summary['would_delete'] as $name) {
                $this->line('  - '.$name);
            }

            return;
        }

        $this->info('Clientes eliminados antes de importar: '.(int) $summary['deleted']);
        foreach ($summary['names'] as $name) {
            $this->line('  - '.$name);
        }
    }

    /**
     * @param  array<int, array{file_name: string, display_file_name: string, file_path: string, can_import: bool, total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>, warnings: array<int, string>}>  $preview
     */
    private function renderPreviewTable(array $preview): void
    {
        $rows = collect($preview)
            ->map(function (array $entry): array {
                $errors = $entry['errors'] ?? [];
                $warnings = $entry['warnings'] ?? [];
                $messages = array_values(array_filter([
                    $errors !== [] ? implode('; ', $errors) : null,
                    $warnings !== [] ? 'Avisos: '.implode('; ', $warnings) : null,
                ]));

                return [
                    (string) ($entry['file_name'] ?? $entry['display_file_name'] ?? $entry['file_path'] ?? '?'),
                    (string) ($entry['total_rows'] ?? 0),
                    (string) ($entry['valid_rows'] ?? 0),
                    (string) ($entry['invalid_rows'] ?? 0),
                    (bool) ($entry['can_import'] ?? false) ? 'si' : 'no',
                    implode(' | ', $messages),
                ];
            })
            ->all();

        $this->newLine();
        $this->table(
            ['Archivo', 'Filas', 'Validas', 'Invalidas', 'Importable', 'Errores / avisos'],
            $rows,
        );
    }

    private function resolveUserIdOption(): ?int
    {
        $user = $this->option('user');

        if (is_numeric($user)) {
            return (int) $user;
        }

        return null;
    }
}
