<?php

namespace App\Filament\Resources\Clients\Pages;

use App\ClientImportMode;
use App\Filament\Pages\ImportVisitReports;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Widgets\ClientsStatsOverviewWidget;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ClientsStatsOverviewWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear cliente')
                ->icon(Heroicon::OutlinedPlus)
                ->after(function (Client $record): void {
                    if ($record->locations()->exists()) {
                        return;
                    }

                    $record->locations()->create([
                        'name' => $record->name,
                        'active' => true,
                    ]);
                }),
            Action::make('importVisitReportsWizard')
                ->label('Importar visitas')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->url(ImportVisitReports::getUrl()),
            ActionGroup::make(
                collect(ClientImportMode::cases())
                    ->map(fn (ClientImportMode $mode): Action => Action::make('basePdfTemplate_'.$mode->value)
                        ->label($mode->filamentLabel())
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->url(ClientResource::baseTemplateUrl($mode)))
                    ->all(),
            )
                ->label('Plantillas PDF base')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->button(),
        ];
    }
}
