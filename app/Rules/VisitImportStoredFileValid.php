<?php

namespace App\Rules;

use App\Services\VisitImport\Helpers\VisitImportFileHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida un archivo ya almacenado en el disco local (ruta relativa del FileUpload).
 * Solo falla si el preview devuelve mensajes en {@see $entry['errors']}; no bloquea
 * archivos con filas inválidas pero revisables en el paso siguiente del wizard.
 */
final class VisitImportStoredFileValid implements ValidationRule
{
    public function __construct(
        private readonly bool $provisionClientAndSections = false,
        private readonly bool $replacePreviousImportSameFilename = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $entry = app(VisitImportFileHelper::class)->verifyFiles([$value], [
            'provision_client_and_sections' => $this->provisionClientAndSections,
            'replace_previous_import_same_filename' => $this->replacePreviousImportSameFilename,
        ])[0] ?? null;

        if ($entry === null) {
            return;
        }

        foreach ($entry['errors'] ?? [] as $message) {
            $fail((string) $message);
        }
    }
}
