<?php

namespace App\Services\VisitImport\Persistence;

use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\BirdTypes\BirdTypeResolver;
use App\Services\VisitImport\VisitImportPayload;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class VisitImportPersistence
{
    private const int MAX_PREVIEW_ERRORS = 100;

    public function __construct(
        private readonly BirdTypeResolver $birdTypeResolver,
    ) {}

    /**
     * @return array{total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>, warnings: array<int, string>}
     */
    public function preview(VisitImportPayload $payload): array
    {
        $totalRows = $payload->totalSourceRows();
        $errors = [];
        $warnings = [];
        $groupHasInvalid = [];

        foreach ($payload->rows as $index => $row) {
            $sourceRowIndex = $payload->rowGroupAt((int) $index);
            $rowNumber = $sourceRowIndex + 2;
            $preparedRow = $this->prepareRowForValidation($payload->headers, $row, $rowNumber);
            $data = $preparedRow['data'];
            $warnings = array_merge($warnings, $preparedRow['warnings']);
            $validationResult = $this->validateRow($data);

            if ($validationResult['is_valid']) {
                $groupHasInvalid[$sourceRowIndex] = $groupHasInvalid[$sourceRowIndex] ?? false;

                continue;
            }

            $groupHasInvalid[$sourceRowIndex] = true;

            if (count($errors) >= self::MAX_PREVIEW_ERRORS) {
                continue;
            }

            $existingErrorForRow = collect($errors)
                ->first(fn (string $error): bool => str_starts_with($error, 'Fila '.$rowNumber.':'));

            if (! is_string($existingErrorForRow)) {
                $errors[] = 'Fila '.$rowNumber.': '.($validationResult['errors'][0] ?? 'Datos de fila invalidos.');
            }
        }

        $invalidRows = collect($groupHasInvalid)
            ->filter(fn (bool $hasInvalid): bool => $hasInvalid)
            ->count();
        $validRows = max($totalRows - $invalidRows, 0);

        return [
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'errors' => $errors,
            'warnings' => array_values($warnings),
        ];
    }

    /**
     * @return array{status: string, total_rows: int, persisted_rows: int, skipped_rows: int}
     */
    public function persist(VisitImportPayload $payload, ?int $visitImportId = null): array
    {
        $totalRows = $payload->totalSourceRows();
        $persistedSourceRows = [];

        foreach ($payload->rows as $index => $row) {
            $sourceRowIndex = $payload->rowGroupAt((int) $index);
            $rowNumber = $sourceRowIndex + 2;
            $preparedRow = $this->prepareRowForValidation($payload->headers, $row, $rowNumber);
            $data = $preparedRow['data'];
            $validationResult = $this->validateRow($data);

            if (! $validationResult['is_valid']) {
                continue;
            }

            $dateInit = $validationResult['date_init'];
            $dateEnd = $validationResult['date_end'] ?? $dateInit;

            $client = Client::query()->firstOrCreate(
                ['name' => $data['client_name']],
                ['active' => true],
            );

            $employee = $this->resolveEmployee($data);

            $location = Location::query()->firstOrCreate(
                [
                    'client_id' => $client->id,
                    'name' => $data['location_name'],
                ],
                ['active' => true],
            );

            $birdType = $validationResult['bird_type'];

            if (! $birdType instanceof BirdType) {
                continue;
            }

            $status = $this->resolveStatus($data['status']);

            $visit = Visit::query()->firstOrCreate(
                [
                    'client_id' => $client->id,
                    'employee_id' => $employee->id,
                    'date_init' => $dateInit,
                    'date_end' => $dateEnd,
                    'status' => $status->value,
                ],
                [
                    'observation' => $data['visit_observation'],
                    'visit_import_id' => $visitImportId,
                ],
            );

            if ($visitImportId !== null) {
                Visit::query()->whereKey($visit->id)->update(['visit_import_id' => $visitImportId]);
            }

            VisitReport::query()->create([
                'visit_id' => $visit->id,
                'location_id' => $location->id,
                'bird_type_id' => $birdType->id,
                'quantity' => (int) $data['quantity'],
                'observation' => $data['observation'],
            ]);

            $persistedSourceRows[$sourceRowIndex] = true;
        }

        $persistedRows = count($persistedSourceRows);
        $skippedRows = max($totalRows - $persistedRows, 0);

        return [
            'status' => $persistedRows > 0 ? 'imported' : 'validated',
            'total_rows' => $totalRows,
            'persisted_rows' => $persistedRows,
            'skipped_rows' => $skippedRows,
        ];
    }

    /**
     * @param  array<string, string>  $data
     * @return array{is_valid: bool, errors: array<int, string>, date_init: ?CarbonImmutable, date_end: ?CarbonImmutable, bird_type: ?BirdType}
     */
    public function validateRowData(array $data): array
    {
        return $this->validateRow($data);
    }

    /**
     * @param  array<string, string>  $data
     * @return array{is_valid: bool, errors: array<int, string>, date_init: ?CarbonImmutable, date_end: ?CarbonImmutable, bird_type: ?BirdType}
     */
    private function validateRow(array $data): array
    {
        $validator = $this->makeRowValidator($data);

        if ($validator->fails()) {
            return [
                'is_valid' => false,
                'errors' => $validator->errors()->all(),
                'date_init' => null,
                'date_end' => null,
                'bird_type' => null,
            ];
        }

        $birdType = $this->birdTypeResolver->resolve($data['bird_type_name']);

        if ($birdType === null) {
            $suggestions = collect($this->birdTypeResolver->importLabelMap())
                ->map(fn (BirdType $type): string => $type->name)
                ->unique()
                ->implode(', ');

            return [
                'is_valid' => false,
                'errors' => ['Tipo de ave desconocido: "'.$data['bird_type_name'].'". Tipos registrados: '.$suggestions.'.'],
                'date_init' => null,
                'date_end' => null,
                'bird_type' => null,
            ];
        }

        $dateInit = $this->toDateTime($data['date_init']);

        if (! $dateInit instanceof CarbonImmutable) {
            return [
                'is_valid' => false,
                'errors' => ['Formato de fecha de inicio invalido.'],
                'date_init' => null,
                'date_end' => null,
                'bird_type' => null,
            ];
        }

        return [
            'is_valid' => true,
            'errors' => [],
            'date_init' => $dateInit,
            'date_end' => $this->toDateTime($data['date_end']),
            'bird_type' => $birdType,
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    private function makeRowValidator(array $data): ValidatorContract
    {
        return Validator::make($data, [
            'client_name' => ['required', 'string', 'max:255'],
            'employee_name' => ['required', 'string', 'max:255'],
            'employee_email' => ['nullable', 'email', 'max:255'],
            'date_init' => ['required'],
            'date_end' => ['nullable'],
            'status' => ['nullable', 'string', 'max:255'],
            'location_name' => ['required', 'string', 'max:255'],
            'bird_type_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:0'],
            'observation' => ['nullable', 'string'],
            'visit_observation' => ['nullable', 'string'],
        ]);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $row
     * @return array{data: array<string, string>, warnings: array<int, string>}
     */
    private function prepareRowForValidation(array $headers, array $row, int $rowNumber): array
    {
        $data = $this->mapRow($headers, $row);
        $warnings = [];
        $quantity = $data['quantity'] ?? '';

        if ($quantity !== '' && is_numeric($quantity) && (float) $quantity === floor((float) $quantity) && (int) $quantity < 0) {
            $warnings[] = 'Fila '.$rowNumber.': la cantidad '.$quantity.' es negativa y se convertira a 0.';
            $data['quantity'] = '0';
        }

        return [
            'data' => $data,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $row
     * @return array<string, string>
     */
    private function mapRow(array $headers, array $row): array
    {
        /** @var array<string, string> $mapped */
        $mapped = collect($headers)
            ->mapWithKeys(fn (string $header, int $index): array => [
                $header => trim((string) ($row[$index] ?? '')),
            ])
            ->all();

        if (array_key_exists('quantity', $mapped)) {
            $mapped['quantity'] = $this->normalizeWholeNumberQuantity($mapped['quantity']);
        }

        return array_merge([
            'client_name' => '',
            'employee_name' => '',
            'employee_email' => '',
            'date_init' => '',
            'date_end' => '',
            'status' => '',
            'location_name' => '',
            'bird_type_name' => '',
            'quantity' => '',
            'observation' => '',
            'visit_observation' => '',
        ], $mapped);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function resolveEmployee(array $data): Employee
    {
        if (filled($data['employee_email'])) {
            return Employee::query()->firstOrCreate(
                ['email' => $data['employee_email']],
                [
                    'name' => $data['employee_name'],
                    'active' => true,
                ],
            );
        }

        return Employee::query()->firstOrCreate(
            ['name' => $data['employee_name']],
            ['active' => true],
        );
    }

    private function normalizeWholeNumberQuantity(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return $trimmed;
        }

        $numeric = (float) $trimmed;

        if ($numeric !== floor($numeric)) {
            return $trimmed;
        }

        return (string) (int) $numeric;
    }

    private function resolveStatus(string $status): VisitStatus
    {
        $normalizedStatus = Str::of($status)
            ->trim()
            ->lower()
            ->replace(' ', '_')
            ->value();

        $mappedStatus = match ($normalizedStatus) {
            '', 'completado', 'finalizado' => VisitStatus::Completed->value,
            'en_progreso', 'progreso' => VisitStatus::InProgress->value,
            'programado', 'agendado' => VisitStatus::Scheduled->value,
            'cancelado' => VisitStatus::Cancelled->value,
            default => $normalizedStatus,
        };

        return VisitStatus::tryFrom($mappedStatus) ?? VisitStatus::Completed;
    }

    private function toDateTime(?string $value): ?CarbonImmutable
    {
        $trimmedValue = trim((string) $value);

        if ($trimmedValue === '') {
            return null;
        }

        if (is_numeric($trimmedValue)) {
            return $this->excelSerialToDateTime((float) $trimmedValue);
        }

        $timezone = config('app.timezone');

        foreach ([
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
        ] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $trimmedValue, $timezone);
            } catch (Throwable) {
                continue;
            }

            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        try {
            return CarbonImmutable::parse($trimmedValue, $timezone);
        } catch (Throwable) {
            return null;
        }
    }

    private function excelSerialToDateTime(float $serialValue): ?CarbonImmutable
    {
        if ($serialValue <= 0) {
            return null;
        }

        $days = (int) floor($serialValue);
        $seconds = (int) round(($serialValue - $days) * 86400);
        $timezone = config('app.timezone');

        return CarbonImmutable::create(1899, 12, 30, 0, 0, 0, $timezone)
            ->addDays($days)
            ->addSeconds($seconds);
    }
}
