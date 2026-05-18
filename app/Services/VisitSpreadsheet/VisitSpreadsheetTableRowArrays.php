<?php

declare(strict_types=1);

namespace App\Services\VisitSpreadsheet;

use App\Models\Client;
use App\Models\Visit;
use App\Models\VisitReport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds visit rows for PDF Blade data using the same keys and cell values as {@see VisitSpreadsheetColumns}.
 */
final class VisitSpreadsheetTableRowArrays
{
    private const string DATETIME_FORMAT = 'd/m/Y H:i';

    public function __construct(
        private readonly VisitSpreadsheetQuantityColumns $quantityColumns,
    ) {}

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, string>>
     */
    public function forClient(Client $client, Collection $visits): array
    {
        $specs = $this->quantityColumns->forClient($client);

        return $visits->values()->map(fn (Visit $visit): array => $this->row($specs, $visit))->all();
    }

    /**
     * @param  list<array{key: string, label: string, location_id: int, bird_type_id: int}>  $specs
     * @return array<string, string>
     */
    private function row(array $specs, Visit $visit): array
    {
        $row = [
            'visit_date_init' => $this->formatDateTime($visit->date_init),
            'visit_date_end' => $this->formatDateTime($visit->date_end),
            'employee.name' => (string) ($visit->employee?->name ?? ''),
        ];

        foreach ($specs as $spec) {
            $row[$spec['key']] = $this->quantityState(
                $visit,
                $spec['location_id'],
                $spec['bird_type_id'],
            );
        }

        $row['visit_observation'] = (string) ($visit->observation ?? '');

        return $row;
    }

    private function quantityState(Visit $visit, int $locationId, int $birdTypeId): string
    {
        $report = $visit->visitReports->first(
            fn (VisitReport $r): bool => (int) $r->location_id === $locationId && (int) $r->bird_type_id === $birdTypeId,
        );

        return $report !== null ? (string) $report->quantity : '';
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof Carbon) {
            return $value->format(self::DATETIME_FORMAT);
        }

        if (is_string($value) && trim($value) === '') {
            return '';
        }

        return Carbon::parse($value)->format(self::DATETIME_FORMAT);
    }
}
