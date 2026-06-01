<?php

namespace App\Services\HistoricVisitImport;

use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\VisitImport\Persistence\VisitImportPersistence;
use App\Services\VisitImport\VisitImportPayload;
use App\VisitStatus;
use Carbon\CarbonImmutable;

final class HistoricVisitImportPersistence
{
    public function __construct(
        private readonly VisitImportPersistence $visitImportPersistence,
    ) {}

    /**
     * @return array{total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>, warnings: array<int, string>}
     */
    public function preview(VisitImportPayload $payload): array
    {
        return $this->visitImportPersistence->preview($payload);
    }

    /**
     * @return array{status: string, total_rows: int, persisted_rows: int, skipped_rows: int, visit_reports: int}
     */
    public function persist(VisitImportPayload $payload, Client $client, int $visitImportId): array
    {
        $totalRows = $payload->totalSourceRows();
        $persistedSourceRows = [];
        $visitReportsCreated = 0;

        foreach ($payload->rows as $index => $row) {
            $sourceRowIndex = $payload->rowGroupAt((int) $index);
            $data = $this->mapRow($payload->headers, $row);
            $validationResult = $this->visitImportPersistence->validateRowData($data);

            if (! $validationResult['is_valid']) {
                continue;
            }

            $dateInit = $validationResult['date_init'];
            $dateEnd = $validationResult['date_end'] ?? $dateInit;
            $birdType = $validationResult['bird_type'];

            if (! $birdType instanceof BirdType || ! $dateInit instanceof CarbonImmutable) {
                continue;
            }

            $location = Location::query()
                ->where('client_id', $client->id)
                ->where('name', $data['location_name'])
                ->where('active', true)
                ->first();

            if (! $location instanceof Location) {
                continue;
            }

            $employee = Employee::query()->firstOrCreate(
                ['name' => $data['employee_name']],
                ['active' => true],
            );

            $visit = Visit::query()->firstOrCreate(
                [
                    'client_id' => $client->id,
                    'employee_id' => $employee->id,
                    'date_init' => $dateInit,
                    'date_end' => $dateEnd,
                    'status' => VisitStatus::Completed->value,
                ],
                [
                    'observation' => $data['visit_observation'],
                    'visit_import_id' => $visitImportId,
                ],
            );

            Visit::query()->whereKey($visit->id)->update(['visit_import_id' => $visitImportId]);

            VisitReport::query()->create([
                'visit_id' => $visit->id,
                'location_id' => $location->id,
                'bird_type_id' => $birdType->id,
                'quantity' => (int) $data['quantity'],
                'observation' => $data['observation'],
            ]);

            $visitReportsCreated++;
            $persistedSourceRows[$sourceRowIndex] = true;
        }

        $persistedRows = count($persistedSourceRows);
        $skippedRows = max($totalRows - $persistedRows, 0);

        return [
            'status' => $persistedRows > 0 ? 'imported' : 'validated',
            'total_rows' => $totalRows,
            'persisted_rows' => $persistedRows,
            'skipped_rows' => $skippedRows,
            'visit_reports' => $visitReportsCreated,
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
}
