<?php

declare(strict_types=1);

namespace App\Services\VisitSpreadsheet;

use App\Models\Client;
use App\Models\Visit;
use App\Models\VisitReport;
use Carbon\Carbon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Illuminate\Support\Str;

final class VisitSpreadsheetColumns
{
    private const string SPREADSHEET_DATETIME_FORMAT = 'd/m/Y H:i';

    /**
     * @return array<Column>
     */
    public function forClient(?Client $client, bool $readOnly = false): array
    {
        $canEditVisit = fn (Visit $visit): bool => ! $readOnly && (auth()->user()?->can('update', $visit) ?? false);

        $canEditQuantitySlot = function (Visit $visit, int $locationId, int $birdTypeId) use ($readOnly): bool {
            if ($readOnly) {
                return false;
            }

            $user = auth()->user();
            if ($user === null) {
                return false;
            }

            $report = VisitReport::query()
                ->where('visit_id', $visit->getKey())
                ->where('location_id', $locationId)
                ->where('bird_type_id', $birdTypeId)
                ->first();

            if ($report !== null) {
                return $user->can('update', $report);
            }

            return $user->can('create', VisitReport::class) && $user->can('update', $visit);
        };

        $quantityColumns = [];

        foreach ($this->quantitySpecs($client) as $spec) {
            $locationId = $spec['location_id'];
            $birdTypeId = $spec['bird_type_id'];

            $quantityColumns[] = TextInputColumn::make($spec['key'])
                ->label($this->label((string) $spec['label']))
                ->type('number')
                ->rules(['nullable', 'integer', 'min:0'])
                ->getStateUsing(function (Visit $visit) use ($locationId, $birdTypeId): string {
                    $report = $visit->visitReports->first(
                        fn (VisitReport $r): bool => (int) $r->location_id === $locationId && (int) $r->bird_type_id === $birdTypeId
                    );

                    return $report !== null ? (string) $report->quantity : '';
                })
                ->updateStateUsing(function (Visit $visit, mixed $state) use ($locationId, $birdTypeId, $canEditQuantitySlot): ?string {
                    if (! $canEditQuantitySlot($visit, $locationId, $birdTypeId)) {
                        return null;
                    }

                    $trimmed = is_string($state) ? trim($state) : $state;

                    $report = VisitReport::query()
                        ->where('visit_id', $visit->getKey())
                        ->where('location_id', $locationId)
                        ->where('bird_type_id', $birdTypeId)
                        ->first();

                    if ($trimmed === '' || $trimmed === null) {
                        if ($report !== null) {
                            $report->quantity = 0;
                            $report->save();
                        }

                        return $report !== null ? '0' : '';
                    }

                    $qty = max(0, (int) $trimmed);

                    if ($report === null) {
                        VisitReport::query()->create([
                            'visit_id' => (int) $visit->getKey(),
                            'location_id' => $locationId,
                            'bird_type_id' => $birdTypeId,
                            'quantity' => $qty,
                            'observation' => null,
                        ]);
                    } else {
                        $report->quantity = $qty;
                        $report->save();
                    }

                    return (string) $qty;
                })
                ->disabled(fn (Visit $visit): bool => ! $canEditQuantitySlot($visit, $locationId, $birdTypeId));
        }

        return [
            TextInputColumn::make('visit_date_init')
                ->label($this->label('Inicio'))
                ->rules(['required', 'date_format:d/m/Y H:i'])
                ->getStateUsing(fn (Visit $visit): string => $this->formatDateTime($visit->date_init))
                ->updateStateUsing(function (Visit $visit, mixed $state): ?string {
                    if (! is_string($state) || trim($state) === '') {
                        return null;
                    }

                    $visit->date_init = $this->parseDateTime($state);
                    $visit->save();

                    return $this->formatDateTime($visit->date_init);
                })
                ->disabled(fn (Visit $visit): bool => ! $canEditVisit($visit)),
            TextInputColumn::make('visit_date_end')
                ->label($this->label('Fin'))
                ->rules(['nullable', 'date_format:d/m/Y H:i'])
                ->getStateUsing(fn (Visit $visit): string => $this->formatDateTime($visit->date_end))
                ->updateStateUsing(function (Visit $visit, mixed $state): ?string {
                    if (! is_string($state) || trim($state) === '') {
                        $visit->date_end = null;
                        $visit->save();

                        return '';
                    }

                    $visit->date_end = $this->parseDateTime($state);
                    $visit->save();

                    return $this->formatDateTime($visit->date_end);
                })
                ->disabled(fn (Visit $visit): bool => ! $canEditVisit($visit)),
            TextColumn::make('employee.name')
                ->label($this->label('Empleado')),
            ...$quantityColumns,
            TextInputColumn::make('visit_observation')
                ->label($this->label('Observacion'))
                ->rules(['nullable', 'string', 'max:65535'])
                ->getStateUsing(fn (Visit $visit): string => (string) ($visit->observation ?? ''))
                ->updateStateUsing(function (Visit $visit, mixed $state): string {
                    $visit->observation = is_string($state) ? $state : '';
                    $visit->save();

                    return (string) ($visit->observation ?? '');
                })
                ->disabled(fn (Visit $visit): bool => ! $canEditVisit($visit)),
        ];
    }

    private function formatDateTime(?Carbon $value): string
    {
        return $value?->format(self::SPREADSHEET_DATETIME_FORMAT) ?? '';
    }

    private function parseDateTime(string $value): Carbon
    {
        return Carbon::createFromFormat(self::SPREADSHEET_DATETIME_FORMAT, trim($value));
    }

    private function label(string $label): string
    {
        return Str::ucfirst($label);
    }

    /**
     * @return list<array{key: string, label: string, location_id: int, bird_type_id: int}>
     */
    private function quantitySpecs(?Client $client): array
    {
        if ($client === null) {
            return [];
        }

        return app(VisitSpreadsheetQuantityColumns::class)->forClient($client);
    }
}
