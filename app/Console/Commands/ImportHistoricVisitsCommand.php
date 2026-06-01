<?php

namespace App\Console\Commands;

use App\Jobs\ProcessHistoricVisitImportsJob;
use App\Services\HistoricVisitImport\HistoricVisitImportService;
use Illuminate\Console\Command;

class ImportHistoricVisitsCommand extends Command
{
    protected $signature = 'historico:import
                            {--dry-run : Simula la importacion sin guardar datos}
                            {--execute : Ejecuta la importacion real}
                            {--directory= : Directorio con archivos historicos (default: historico/)}
                            {--queue : Encolar el job en lugar de ejecutarlo en linea}
                            {--user= : ID de usuario para notificacion al finalizar}';

    protected $description = 'Importa visitas historicas desde archivos Excel en la carpeta historico';

    public function handle(HistoricVisitImportService $historicVisitImportService): int
    {
        $dryRun = ! $this->option('execute') || $this->option('dry-run');
        $directory = $this->resolveDirectory();
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;

        if ($this->option('queue')) {
            ProcessHistoricVisitImportsJob::dispatch($dryRun, $userId, $directory);
            $this->info('Job de importacion historica encolado.'.($dryRun ? ' (dry-run)' : ' (ejecucion real)'));

            return self::SUCCESS;
        }

        $summary = $historicVisitImportService->processDirectory($directory, $dryRun, $userId);
        $this->renderSummary($summary);

        return ($summary['failed_files'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveDirectory(): string
    {
        $directory = $this->option('directory');

        if (is_string($directory) && $directory !== '') {
            return $directory;
        }

        return base_path('historico');
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function renderSummary(array $summary): void
    {
        $this->newLine();
        $this->info($summary['dry_run'] ? '=== Simulacion de importacion historica (dry-run) ===' : '=== Importacion historica ejecutada ===');
        $this->line('Directorio: '.($summary['directory'] ?? ''));
        $this->line('Archivos totales: '.($summary['total_files'] ?? 0));
        $this->line('Exitosos: '.($summary['successful_files'] ?? 0).' | Fallidos: '.($summary['failed_files'] ?? 0).' | Omitidos: '.($summary['skipped_files'] ?? 0));
        $this->line('Filas fuente: '.($summary['total_source_rows'] ?? 0));
        $this->line('Filas validas: '.($summary['total_valid_rows'] ?? 0).' | Invalidas: '.($summary['total_invalid_rows'] ?? 0));
        $this->line('Visitas '.($summary['dry_run'] ? 'a importar' : 'importadas').': '.($summary['total_persisted_rows'] ?? 0).' | Omitidas: '.($summary['total_skipped_rows'] ?? 0));
        $this->line('Fechas ya registradas (omitidas): '.($summary['total_skipped_existing_dates'] ?? 0));
        $this->line('Reportes de visita: '.($summary['total_visit_reports'] ?? 0));
        $this->line(
            ($summary['dry_run'] ? 'Importaciones historicas previas a reemplazar: ' : 'Importaciones historicas eliminadas: ')
            .($summary['previous_historic_imports'] ?? 0)
            .' ('.($summary['previous_historic_visits'] ?? 0).' visitas)',
        );

        $clientsImported = $summary['clients_imported'] ?? [];

        if (is_array($clientsImported) && $clientsImported !== []) {
            $this->newLine();
            $this->info('Clientes procesados correctamente ('.count($clientsImported).'):');
            foreach ($clientsImported as $clientName) {
                $this->line('  ✓ '.$clientName);
            }
        }

        $clientsFailed = $summary['clients_failed'] ?? [];

        if (is_array($clientsFailed) && $clientsFailed !== []) {
            $this->newLine();
            $this->error('Archivos con error ('.count($clientsFailed).'):');
            foreach ($clientsFailed as $failure) {
                if (! is_array($failure)) {
                    continue;
                }

                $this->line('  ✗ '.($failure['filename'] ?? '?').' — '.($failure['error'] ?? 'Error desconocido'));
            }
        }

        $files = $summary['files'] ?? [];

        if (! is_array($files) || $files === []) {
            return;
        }

        $this->newLine();
        $this->info('Detalle por archivo:');

        $rows = collect($files)
            ->map(function (mixed $file): array {
                if (! is_array($file)) {
                    return ['?', '?', '?', '?', '?', '?'];
                }

                return [
                    (string) ($file['filename'] ?? '?'),
                    (string) ($file['status'] ?? '?'),
                    (string) ($file['client_name'] ?? '?'),
                    (string) ($file['total_rows'] ?? 0),
                    (string) ($file['valid_rows'] ?? 0),
                    (string) ($file['visit_reports'] ?? 0),
                ];
            })
            ->all();

        $this->table(
            ['Archivo', 'Estado', 'Cliente', 'Filas', 'Validas', 'Reportes'],
            $rows,
        );
    }
}
