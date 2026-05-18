<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Pages\ImportVisitReports;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

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
        ];
    }
}
