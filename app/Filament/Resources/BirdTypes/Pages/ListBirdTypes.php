<?php

namespace App\Filament\Resources\BirdTypes\Pages;

use App\Filament\Resources\BirdTypes\BirdTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBirdTypes extends ListRecords
{
    protected static string $resource = BirdTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
