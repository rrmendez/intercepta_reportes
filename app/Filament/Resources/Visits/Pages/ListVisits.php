<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Filament\Pages\ImportVisitReports;
use App\Filament\Resources\Visits\VisitResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('import')
                ->label('Import Visits')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(ImportVisitReports::getUrl()),
        ];
    }
}
