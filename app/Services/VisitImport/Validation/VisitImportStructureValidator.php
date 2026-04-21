<?php

namespace App\Services\VisitImport\Validation;

use App\Services\VisitImport\VisitImportPayload;
use Illuminate\Validation\ValidationException;

class VisitImportStructureValidator
{
    /**
     * @var array<int, string>
     */
    private const REQUIRED_COLUMNS = [
        'client_name',
        'employee_name',
        'employee_email',
        'date_init',
        'date_end',
        'status',
        'location_name',
        'bird_type_name',
        'quantity',
        'observation',
        'visit_observation',
    ];

    /**
     * @return array<int, string>
     */
    public static function requiredColumns(): array
    {
        return self::REQUIRED_COLUMNS;
    }

    public function validate(VisitImportPayload $payload): void
    {
        $missingColumns = collect(self::requiredColumns())
            ->reject(fn (string $column): bool => in_array($column, $payload->headers, true))
            ->values()
            ->all();

        if ($missingColumns !== []) {
            throw ValidationException::withMessages([
                'file' => ['Missing required columns: '.implode(', ', $missingColumns)],
            ]);
        }

        if ($payload->rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => ['The file does not contain data rows.'],
            ]);
        }
    }
}
