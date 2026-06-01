<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\HistoricVisitImport\HistoricVisitImportService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessHistoricVisitImportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public bool $dryRun = true,
        public ?int $userId = null,
        public ?string $directory = null,
    ) {}

    public function handle(HistoricVisitImportService $historicVisitImportService): array
    {
        $directory = $this->directory ?? base_path('historico');

        try {
            $summary = $historicVisitImportService->processDirectory($directory, $this->dryRun, $this->userId);

            Log::info('Importacion historica de visitas finalizada.', [
                'dry_run' => $this->dryRun,
                'directory' => $directory,
                'total_files' => $summary['total_files'],
                'successful_files' => $summary['successful_files'],
                'failed_files' => $summary['failed_files'],
            ]);

            $this->sendCompletedNotification($summary);

            return $summary;
        } catch (Throwable $exception) {
            Log::error('Fallo la importacion historica de visitas.', [
                'dry_run' => $this->dryRun,
                'directory' => $directory,
                'message' => $exception->getMessage(),
            ]);

            $this->sendFailedNotification($exception);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function sendCompletedNotification(array $summary): void
    {
        if (! is_int($this->userId)) {
            return;
        }

        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        $mode = $this->dryRun ? 'Simulacion (dry-run)' : 'Importacion ejecutada';
        $lines = [
            $mode,
            'Archivos procesados: '.($summary['total_files'] ?? 0),
            'Exitosos: '.($summary['successful_files'] ?? 0).'. Fallidos: '.($summary['failed_files'] ?? 0).'.',
            'Filas validas: '.($summary['total_valid_rows'] ?? 0).'. Visitas '.($this->dryRun ? 'a importar' : 'importadas').': '.($summary['total_persisted_rows'] ?? 0).'.',
            'Fechas ya registradas omitidas: '.($summary['total_skipped_existing_dates'] ?? 0).'.',
            'Reportes de visita: '.($summary['total_visit_reports'] ?? 0).'.',
            ($this->dryRun ? 'Importaciones historicas previas: ' : 'Importaciones historicas eliminadas: ')
                .($summary['previous_historic_imports'] ?? 0)
                .' ('.($summary['previous_historic_visits'] ?? 0).' visitas).',
        ];

        $failedClients = $summary['clients_failed'] ?? [];

        if (is_array($failedClients) && $failedClients !== []) {
            $lines[] = 'Clientes con error:';
            foreach (array_slice($failedClients, 0, 8) as $failure) {
                if (! is_array($failure)) {
                    continue;
                }

                $lines[] = '– '.($failure['filename'] ?? '?').': '.($failure['error'] ?? 'Error');
            }

            if (count($failedClients) > 8) {
                $lines[] = '… y '.(count($failedClients) - 8).' mas.';
            }
        }

        $notification = Notification::make()
            ->title($this->dryRun ? 'Simulacion historica lista' : 'Importacion historica lista')
            ->body(collect($lines)->implode("\n"));

        if (($summary['failed_files'] ?? 0) > 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $user->notifyNow($notification->toDatabase());
    }

    private function sendFailedNotification(Throwable $exception): void
    {
        if (! is_int($this->userId)) {
            return;
        }

        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        $user->notifyNow(
            Notification::make()
                ->title('Importacion historica fallida')
                ->body('No se pudo completar la importacion historica. '.$exception->getMessage())
                ->danger()
                ->persistent()
                ->toDatabase(),
        );
    }
}
