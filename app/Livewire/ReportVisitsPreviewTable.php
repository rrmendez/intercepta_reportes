<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Tables\VisitSpreadsheetTable;
use App\Models\Visit;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ReportVisitsPreviewTable extends TableComponent
{
    public int $clientId;

    public string $dateFrom;

    public string $dateUntil;

    public bool $dispatchComposeRange = false;

    public bool $dispatchComposeSpreadsheetFilters = false;

    public bool $dispatchVisitDataStale = false;

    public function mount(int $clientId, string $dateFrom, string $dateUntil): void
    {
        $this->clientId = $clientId;
        $this->dateFrom = $dateFrom;
        $this->dateUntil = $dateUntil;

        $this->tableFilters = [
            'spreadsheet' => [
                'mode' => 'custom_range',
                'date_from' => $dateFrom,
                'date_until' => $dateUntil,
            ],
        ];

        $this->syncReportModalRangeSession();
    }

    public function updateTableColumnState(string $column, string $record, mixed $input): mixed
    {
        $result = parent::updateTableColumnState($column, $record, $input);

        if ($this->dispatchVisitDataStale && ! (is_array($result) && array_key_exists('error', $result))) {
            $this->dispatch('report-period-visits-changed');
        }

        return $result;
    }

    public function updatedTableFilters(): void
    {
        $this->syncReportModalRangeSession();
    }

    public function table(Table $table): Table
    {
        $spreadsheet = app(VisitSpreadsheetTable::class);

        return $table
            ->query(fn (): Builder => $this->baseQuery())
            ->tap(function (Table $inner) use ($spreadsheet): void {
                $spreadsheet->configurePresentation(
                    $inner,
                    $spreadsheet->columnsForClientId($this->clientId),
                );
            })
            ->filters(
                $spreadsheet->spreadsheetFilters($this->clientId, [
                    'date_from' => $this->dateFrom,
                    'date_until' => $this->dateUntil,
                ]),
                layout: FiltersLayout::AboveContent,
            )
            ->filtersFormColumns(6);
    }

    public function render(): View
    {
        return view('livewire.report-visits-preview-table');
    }

    private function baseQuery(): Builder
    {
        return Visit::query()
            ->with(['employee', 'visitReports'])
            ->orderByDesc('date_init')
            ->orderByDesc('id');
    }

    private function syncReportModalRangeSession(): void
    {
        $row = (array) data_get($this->tableFilters, 'spreadsheet', []);

        $range = app(VisitSpreadsheetTable::class)->resolveSpreadsheetFilterDateRange($row);

        if ($range === null) {
            return;
        }

        session()->put(ClientResource::reportModalRangeSessionKey($this->clientId), $range);

        if ($this->dispatchComposeRange) {
            $this->dispatch(
                'compose-report-range-changed',
                dateFrom: $range['date_from'],
                dateUntil: $range['date_until'],
            );
        }

        if ($this->dispatchComposeSpreadsheetFilters) {
            $this->dispatch(
                'compose-report-spreadsheet-filters-changed',
                filters: $this->tableFilters,
            );
        }
    }
}
