<?php

namespace App\Filament\Resources\BirdTypes\Pages;

use App\Filament\Resources\BirdTypes\BirdTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBirdType extends EditRecord
{
    protected static string $resource = BirdTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
