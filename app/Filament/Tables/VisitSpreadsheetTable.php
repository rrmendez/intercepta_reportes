<?php

declare(strict_types=1);

namespace App\Filament\Tables;

use App\Filament\Resources\Visits\VisitResource;
use App\Models\Client;
use App\Models\Visit;
use App\Services\VisitSpreadsheet\VisitSpreadsheetColumns;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class VisitSpreadsheetTable
{
    public function __construct(
        private readonly VisitSpreadsheetColumns $visitSpreadsheetColumns,
    ) {}

    /**
     * @return array<Column>
     */
    public function columnsForClientId(?int $clientId, bool $readOnly = false): array
    {
        $client = filled($clientId) ? Client::query()->find((int) $clientId) : null;

        return $this->visitSpreadsheetColumns->forClient($client, readOnly: $readOnly);
    }

    /**
     * @return array<BaseFilter>
     */
    public function spreadsheetFilters(?int $forcedClientId = null, ?array $modalInitialRange = null): array
    {
        $defaultState = $forcedClientId === null
            ? [
                'client_id' => null,
                'mode' => 'this_month',
                'date_from' => null,
                'date_until' => null,
            ]
            : [
                'mode' => 'custom_range',
                'date_from' => ($modalInitialRange ?? [])['date_from'] ?? null,
                'date_until' => ($modalInitialRange ?? [])['date_until'] ?? null,
            ];

        $schema = $forcedClientId === null
            ? Grid::make(6)
                ->schema([
                    Select::make('client_id')
                        ->label('Empresa')
                        ->columnSpan(2)
                        ->options(fn (): array => Client::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('mode')
                        ->label('Periodo')
                        ->columnSpan(2)
                        ->options([
                            'this_month' => 'Este mes',
                            'last_month' => 'Mes pasado',
                            'custom_range' => 'Rango personalizado',
                        ])
                        ->default('this_month')
                        ->native(false)
                        ->live(),
                    DatePicker::make('date_from')
                        ->label('Desde')
                        ->columnSpan(1)
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->visible(fn ($get): bool => ($get('mode') ?? 'this_month') === 'custom_range'),
                    DatePicker::make('date_until')
                        ->label('Hasta')
                        ->columnSpan(1)
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->visible(fn ($get): bool => ($get('mode') ?? 'this_month') === 'custom_range'),
                ])
            : Grid::make(6)
                ->schema([
                    Select::make('mode')
                        ->label('Periodo')
                        ->columnSpan(2)
                        ->options([
                            'this_month' => 'Este mes',
                            'last_month' => 'Mes pasado',
                            'custom_range' => 'Rango personalizado',
                        ])
                        ->native(false)
                        ->live(),
                    DatePicker::make('date_from')
                        ->label('Desde')
                        ->columnSpan(2)
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->visible(fn ($get): bool => ($get('mode') ?? 'this_month') === 'custom_range'),
                    DatePicker::make('date_until')
                        ->label('Hasta')
                        ->columnSpan(2)
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->visible(fn ($get): bool => ($get('mode') ?? 'this_month') === 'custom_range'),
                ]);

        return [
            Filter::make('spreadsheet')
                ->label('')
                ->columnSpanFull()
                ->schema([$schema])
                ->default($defaultState)
                ->query(fn (Builder $query, array $data): Builder => $this->applySpreadsheetFilterQuery($query, $data, $forcedClientId)),
        ];
    }

    /**
     * @param  array<string, mixed>  $spreadsheetFilterRow
     * @return array{date_from: string, date_until: string}|null
     */
    public function resolveSpreadsheetFilterDateRange(array $spreadsheetFilterRow): ?array
    {
        $bounds = $this->spreadsheetPeriodBounds($spreadsheetFilterRow);

        if ($bounds === null) {
            return null;
        }

        [$start, $end] = $bounds;

        return [
            'date_from' => $start->toDateString(),
            'date_until' => $end->toDateString(),
        ];
    }

    public function applySpreadsheetFilterQuery(Builder $query, array $data, ?int $forcedClientId): Builder
    {
        $clientId = $forcedClientId;

        if ($clientId === null) {
            $raw = $data['client_id'] ?? null;

            if (! filled($raw)) {
                return $query->whereRaw('0 = 1');
            }

            $clientId = (int) $raw;
        }

        $query->where($query->qualifyColumn('client_id'), $clientId);

        $bounds = $this->spreadsheetPeriodBounds($data);

        if ($bounds === null) {
            return $query->whereRaw('0 = 1');
        }

        [$start, $end] = $bounds;
        $dateInitColumn = $query->qualifyColumn('date_init');

        return $query->whereBetween($dateInitColumn, [$start, $end]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function spreadsheetPeriodBounds(array $data): ?array
    {
        $mode = $data['mode'] ?? 'this_month';

        if ($mode === 'this_month') {
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();

            return [$start, $end];
        }

        if ($mode === 'last_month') {
            $start = Carbon::now()->subMonthNoOverflow()->startOfMonth();

            return [$start, $start->copy()->endOfMonth()];
        }

        if ($mode === 'custom_range') {
            $from = $data['date_from'] ?? null;
            $until = $data['date_until'] ?? null;

            if (! filled($from) || ! filled($until)) {
                return null;
            }

            return [
                Carbon::parse((string) $from)->startOfDay(),
                Carbon::parse((string) $until)->endOfDay(),
            ];
        }

        return null;
    }

    /**
     * @param  array<Column>  $columns
     */
    public function configurePresentation(
        Table $table,
        array $columns,
        bool $includeDeleteRecordAction = false,
        bool $authorizeReorder = false,
    ): void {
        $table
            ->modelLabel(VisitResource::getModelLabel())
            ->pluralModelLabel(VisitResource::getPluralModelLabel())
            ->recordTitleAttribute(VisitResource::getRecordTitleAttribute())
            ->recordTitle(function (Model $record): string {
                if (! $record instanceof Visit) {
                    return (string) $record->getKey();
                }

                $init = $record->date_init?->format('d/m/Y H:i');
                $employee = $record->relationLoaded('employee') ? $record->employee?->name : null;

                return trim(implode(' — ', array_filter([$init, $employee, '#'.$record->getKey()]))) ?: 'Visita #'.$record->getKey();
            });

        if ($authorizeReorder) {
            $table->authorizeReorder(VisitResource::canReorder(...));
        }

        $recordActions = [];

        if ($includeDeleteRecordAction) {
            $recordActions[] = DeleteAction::make()
                ->label('')
                ->tooltip(__('filament-actions::delete.single.label'))
                ->visible(fn (Visit $record): bool => auth()->user()?->can('delete', $record) ?? false);
        }

        $table
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50])
            ->columns($columns)
            ->recordUrl(null)
            ->recordActions($recordActions)
            ->defaultSort('date_init', 'desc');
    }
}
