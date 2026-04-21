<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->after(function (Client $record): void {
                    if ($record->locations()->exists()) {
                        return;
                    }

                    $record->locations()->create([
                        'name' => 'Conteo',
                        'active' => true,
                    ]);
                }),
        ];
    }
}
