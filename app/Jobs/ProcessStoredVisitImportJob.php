<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ImportVisitExcelService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessStoredVisitImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public string $relativePathOnLocalDisk,
        public ?int $userId,
        public string $originalFilename,
        public bool $provisionClientAndSections = false,
        public bool $replacePreviousImportSameFilename = false,
        public ?int $fallbackClientId = null,
    ) {}

    public function handle(ImportVisitExcelService $importVisitExcelService): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->relativePathOnLocalDisk)) {
            throw new RuntimeException('No se encontro el archivo de importacion: '.$this->relativePathOnLocalDisk);
        }

        $absolutePath = $disk->path($this->relativePathOnLocalDisk);

        try {
            $result = $importVisitExcelService->import($absolutePath, $this->fallbackClientId, [
                'user_id' => $this->userId,
                'original_filename' => $this->originalFilename,
                'stored_file_path' => $this->relativePathOnLocalDisk,
                'provision_client_and_sections' => $this->provisionClientAndSections,
                'replace_previous_import_same_filename' => $this->replacePreviousImportSameFilename,
            ]);

            $this->sendCompletedDatabaseNotification($result);
        } catch (Throwable $exception) {
            Log::warning('Fallo la importacion de visitas desde archivo.', [
                'path' => $this->relativePathOnLocalDisk,
                'original_filename' => $this->originalFilename,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Job de importacion de visitas fallo de forma definitiva.', [
            'path' => $this->relativePathOnLocalDisk,
            'original_filename' => $this->originalFilename,
            'message' => $exception?->getMessage(),
        ]);

        $this->sendFailedDatabaseNotification($exception);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function sendCompletedDatabaseNotification(array $result): void
    {
        if (! is_int($this->userId)) {
            return;
        }

        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        $total = (int) ($result['total_rows'] ?? 0);
        $persisted = (int) ($result['persisted_rows'] ?? 0);
        $skipped = (int) ($result['skipped_rows'] ?? 0);
        $summary = trim((string) ($result['summary_message'] ?? ''));

        $lines = [
            'Archivo: '.$this->originalFilename,
            $summary !== '' ? 'Detalle: '.$summary : null,
            'Filas en el archivo: '.$total.'. Visitas guardadas: '.$persisted.'. Omitidas: '.$skipped.'.',
        ];

        $warnings = array_values($result['warnings'] ?? []);
        if ($warnings !== []) {
            $lines[] = 'Advertencias:';
            foreach (array_slice($warnings, 0, 6) as $warning) {
                $lines[] = '– '.(string) $warning;
            }
            if (count($warnings) > 6) {
                $lines[] = '… y '.(count($warnings) - 6).' mas.';
            }
        }

        $lines[] = 'Puedes ver el detalle en Importaciones de visitas.';

        $body = collect($lines)->filter(fn (?string $line): bool => $line !== null && $line !== '')->implode("\n");

        $notification = Notification::make()
            ->title('Importacion de visitas lista')
            ->body($body);

        if ($total > 0 && $persisted === 0) {
            $notification->danger();
        } elseif ($skipped > 0 || $warnings !== []) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $user->notifyNow($notification->toDatabase());
    }

    private function sendFailedDatabaseNotification(?Throwable $exception): void
    {
        if (! is_int($this->userId)) {
            return;
        }

        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        $detail = 'No se pudo completar la importacion de «'.$this->originalFilename.'».';
        if ($exception instanceof Throwable && $exception->getMessage() !== '') {
            $detail .= ' '.Str::limit($exception->getMessage(), 200);
        }

        $n = Notification::make()
            ->title('Importacion de visitas fallida')
            ->body($detail)
            ->danger()
            ->persistent();

        $user->notifyNow($n->toDatabase());
    }
}
