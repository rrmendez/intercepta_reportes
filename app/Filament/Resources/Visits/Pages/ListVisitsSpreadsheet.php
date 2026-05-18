<?php

declare(strict_types=1);

namespace App\Filament\Resources\Visits\Pages;

use App\Filament\Resources\Visits\VisitResource;
use App\Filament\Tables\VisitSpreadsheetTable;
use App\Models\Visit;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListVisitsSpreadsheet extends ListRecords
{
    protected static string $resource = VisitResource::class;

    protected static ?string $title = 'Visitas (vista planilla)';

    protected function shouldPersistTableFiltersInSession(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return Visit::query()
            ->with(['employee', 'visitReports'])
            ->orderByDesc('date_init')
            ->orderByDesc('id');
    }

    protected function makeTable(): Table
    {
        $table = $this->makeBaseTable()
            ->query(fn (): Builder => $this->getTableQuery())
            ->when(
                $this->getParentRecord(),
                fn (Table $table, Model $parentRecord): Table => $table->modifyQueryUsing(
                    fn (Builder $query) => static::getResource()::scopeEloquentQueryToParent($query, $parentRecord),
                ),
            )
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->when($this->getModelLabel(), fn (Table $table, string $modelLabel): Table => $table->modelLabel($modelLabel))
            ->when($this->getPluralModelLabel(), fn (Table $table, string $pluralModelLabel): Table => $table->pluralModelLabel($pluralModelLabel))
            ->recordAction(function (Model $record, Table $table): ?string {
                foreach (['view', 'edit'] as $action) {
                    $action = $table->getAction($action);

                    if (! $action) {
                        continue;
                    }

                    $action->record($record);

                    $actionGroup = $action->getGroup();

                    while ($actionGroup) {
                        $actionGroup->record($record);

                        $actionGroup = $actionGroup->getGroup();
                    }

                    if ($action->isHidden()) {
                        continue;
                    }

                    if ($action->getUrl()) {
                        continue;
                    }

                    return $action->getName();
                }

                return null;
            });

        if (! $table->hasCustomRecordUrl()) {
            $table->recordUrl(function (Model $record, Table $table): ?string {
                foreach (['view', 'edit'] as $action) {
                    $action = $table->getAction($action);

                    if (! $action) {
                        continue;
                    }

                    $action = clone $action;

                    $action->record($record);

                    $actionGroup = $action->getGroup();

                    while ($actionGroup) {
                        $actionGroup->record($record);

                        $actionGroup = $actionGroup->getGroup();
                    }

                    if ($action->isHidden()) {
                        continue;
                    }

                    $url = $action->getUrl();

                    if (! $url) {
                        continue;
                    }

                    return $url;
                }

                $resource = static::getResource();

                foreach (['view', 'edit'] as $action) {
                    if (! $resource::hasPage($action)) {
                        continue;
                    }

                    if (! $resource::{'can'.ucfirst($action)}($record)) {
                        continue;
                    }

                    return $this->getResourceUrl($action, ['record' => $record]);
                }

                return null;
            });
        }

        $this->configureSpreadsheetTable($table);

        return $table;
    }

    protected function configureSpreadsheetTable(Table $table): void
    {
        $spreadsheet = app(VisitSpreadsheetTable::class);

        $spreadsheet->configurePresentation(
            $table,
            $spreadsheet->columnsForClientId($this->spreadsheetFilterClientId()),
            includeDeleteRecordAction: true,
            authorizeReorder: true,
        );

        $table
            ->filters($spreadsheet->spreadsheetFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(6);
    }

    protected function spreadsheetFilterClientId(): ?int
    {
        $raw = data_get($this->tableDeferredFilters, 'spreadsheet.client_id')
            ?? data_get($this->tableFilters, 'spreadsheet.client_id');

        if (! filled($raw)) {
            return null;
        }

        return (int) $raw;
    }
}
