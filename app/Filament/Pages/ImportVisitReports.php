<?php

namespace App\Filament\Pages;

use App\Services\ImportVisitReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use UnitEnum;

class ImportVisitReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 99;

    protected static ?string $navigationLabel = 'Import Visit Reports';

    protected static ?string $title = 'Import Visit Reports';

    protected string $view = 'filament.pages.import-visit-reports';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isOperator();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import CSV')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->schema([
                    FileUpload::make('file')
                        ->label('CSV / Excel file')
                        ->directory('imports/visit-reports')
                        ->disk('local')
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path((string) $data['file']);
                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                    if ($extension !== 'csv') {
                        throw new RuntimeException('Only CSV parsing is implemented in phase 1. XLSX support is prepared for phase 2.');
                    }

                    $result = app(ImportVisitReportService::class)->importCsv($path);

                    Notification::make()
                        ->title('Import finished')
                        ->body("Imported: {$result['imported']} | Skipped: {$result['skipped']}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
