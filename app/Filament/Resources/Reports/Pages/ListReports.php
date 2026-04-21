<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use App\Models\Client;
use App\Models\Template;
use App\Services\GenerateMonthlyReportPdfService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
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
                    $report = app(GenerateMonthlyReportPdfService::class)->generate(
                        clientId: (int) $data['client_id'],
                        month: (int) $data['month'],
                        year: (int) $data['year'],
                        templateId: filled($data['template_id'] ?? null) ? (int) $data['template_id'] : null,
                    );

                    Notification::make()
                        ->title('Monthly PDF generated')
                        ->success()
                        ->actions([
                            Action::make('open')
                                ->label('Open report')
                                ->url(
                                    blank($report->generated_file_path)
                                        ? ReportResource::getUrl('edit', ['record' => $report])
                                        : Storage::disk('local')->temporaryUrl(
                                            $report->generated_file_path,
                                            now()->addMinutes(10),
                                        ),
                                    shouldOpenInNewTab: true,
                                ),
                        ])
                        ->send();
                }),
        ];
    }
}
