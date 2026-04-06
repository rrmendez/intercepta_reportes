<?php

namespace App\Services;

use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Visit;
use App\Models\VisitReport;
use App\VisitStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;

class ImportVisitReportService
{
    /**
     * @return array{imported: int, skipped: int, errors: array<int, array{line: int, errors: array<int, string>}>}
     */
    public function importCsv(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Import file not found.');
        }

        $file = new SplFileObject($absolutePath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        /** @var array<int, string> $headers */
        $headers = [];

        $result = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($file as $line => $row) {
            if (! is_array($row) || $this->isEmptyRow($row)) {
                continue;
            }

            if ($headers === []) {
                $headers = $this->normalizeHeaders($row);

                continue;
            }

            $data = $this->mapRow($headers, $row);

            $validator = Validator::make($data, [
                'client_name' => ['required', 'string', 'max:255'],
                'employee_name' => ['required', 'string', 'max:255'],
                'date_init' => ['required', 'date'],
                'location_name' => ['required', 'string', 'max:255'],
                'bird_type_name' => ['required', 'string', 'max:255'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);

            if ($validator->fails()) {
                $result['skipped']++;
                $result['errors'][] = [
                    'line' => $line + 1,
                    'errors' => $validator->errors()->all(),
                ];

                continue;
            }

            DB::transaction(function () use ($data): void {
                $client = Client::query()->firstOrCreate(
                    ['name' => $data['client_name']],
                    ['active' => true],
                );

                $employee = filled($data['employee_email'])
                    ? Employee::query()->firstOrCreate(
                        ['email' => $data['employee_email']],
                        [
                            'name' => $data['employee_name'],
                            'active' => true,
                        ],
                    )
                    : Employee::query()->firstOrCreate(
                        ['name' => $data['employee_name']],
                        ['active' => true],
                    );

                $location = Location::query()->firstOrCreate(
                    [
                        'client_id' => $client->id,
                        'name' => $data['location_name'],
                    ],
                    ['active' => true],
                );

                $birdType = BirdType::query()->firstOrCreate(
                    ['name' => $data['bird_type_name']],
                    ['active' => true],
                );

                $status = VisitStatus::tryFrom($data['status'] ?? '') ?? VisitStatus::Completed;

                $visit = Visit::query()->firstOrCreate(
                    [
                        'client_id' => $client->id,
                        'employee_id' => $employee->id,
                        'date_init' => $this->toDateTime($data['date_init']),
                        'date_end' => $this->toDateTime($data['date_end']) ?? $this->toDateTime($data['date_init']),
                        'status' => $status->value,
                    ],
                    [
                        'observation' => $data['visit_observation'],
                    ],
                );

                VisitReport::query()->create([
                    'visit_id' => $visit->id,
                    'location_id' => $location->id,
                    'bird_type_id' => $birdType->id,
                    'quantity' => (int) $data['quantity'],
                    'observation' => $data['observation'],
                ]);
            });

            $result['imported']++;
        }

        return $result;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    private function normalizeHeaders(array $row): array
    {
        return collect($row)
            ->map(fn (mixed $value): string => Str::of((string) $value)->trim()->snake()->toString())
            ->all();
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $row
     * @return array<string, string>
     */
    private function mapRow(array $headers, array $row): array
    {
        /** @var array<string, string> $mapped */
        $mapped = collect($headers)
            ->mapWithKeys(function (string $header, int $index) use ($row): array {
                return [$header => trim((string) ($row[$index] ?? ''))];
            })
            ->all();

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
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)->filter(fn (mixed $value): bool => trim((string) $value) !== '')->isEmpty();
    }

    private function toDateTime(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
