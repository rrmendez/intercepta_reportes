<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Reports\ReportResource;
use App\Models\Client;
use App\Models\Template;
use App\Services\GenerateMonthlyReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class GenerateMonthlyReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Generate Monthly Report';

    protected static ?string $title = 'Generate Monthly Report';

    protected string $view = 'filament.pages.generate-monthly-reports';

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
            Action::make('generate')
                ->label('Generate')
                ->icon(Heroicon::OutlinedPlay)
                ->schema([
                    Select::make('client_id')
                        ->options(fn (): array => Client::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Select::make('template_id')
                        ->label('Template (optional)')
                        ->options(fn (Get $get): array => Template::query()
                            ->where('client_id', $get('client_id'))
                            ->where('active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload(),
                    Select::make('month')
                        ->options(
                            collect(range(1, 12))
                                ->mapWithKeys(fn (int $month): array => [(string) $month => str_pad((string) $month, 2, '0', STR_PAD_LEFT)])
                                ->all()
                        )
                        ->default((string) now()->month)
                        ->required(),
                    Select::make('year')
                        ->options(
                            collect(range((int) now()->year - 2, (int) now()->year + 1))
                                ->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])
                                ->all()
                        )
                        ->default((string) now()->year)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $report = app(GenerateMonthlyReportService::class)->generate(
                        clientId: (int) $data['client_id'],
                        month: (int) $data['month'],
                        year: (int) $data['year'],
                        templateId: filled($data['template_id'] ?? null) ? (int) $data['template_id'] : null,
                    );

                    Notification::make()
                        ->title('Monthly report generated')
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('open')
                                ->label('Open report')
                                ->url(ReportResource::getUrl('edit', ['record' => $report])),
                        ])
                        ->send();
                }),
        ];
    }
}
