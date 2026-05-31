<?php

namespace App\Filament\Resources\Clients;

// use App\Filament\Resources\Clients\Pages\CreateClient;
// use App\Filament\Resources\Clients\Pages\EditClient;
use App\ClientImportMode;
use App\Filament\Resources\Clients\Pages\EditBasePdfTemplate;
use App\Filament\Resources\Clients\Pages\EditClientTemplate;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\RelationManagers\SectionsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\TemplatesRelationManager;
use App\Filament\Resources\Reports\Pages\ComposeReport;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Client;
use App\Models\Report;
use App\ReportStatus;
use App\Services\GenerateMonthlyReportPdfService;
use App\Services\ReportPdfTemplateDefaults;
use App\Services\Reports\ReportPeriodData;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'General';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Empresa')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->maxLength(255),
                TextInput::make('address')
                    ->label('Direccion')
                    ->columnSpanFull()
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Notas')
                    ->hidden()
                    ->rows(3)
                    ->columnSpanFull(),
                Section::make('Secciones')
                    ->description('Define las secciones de este cliente. Se usan al importar reportes de visita para asociar visitas con secciones especificas del cliente.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('locations')
                            ->hiddenLabel()
                            ->relationship()
                            ->deletable(fn (?array $state): bool => count($state ?? []) > 1)
                            ->simple(
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->placeholder('Nombre de la seccion')
                                    ->required()
                                    ->maxLength(255),
                            )
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar seccion')
                            ->columnSpanFull(),
                    ]),
                Toggle::make('active')
                    ->label('Activo')
                    ->default(true)
                    ->columnSpanFull()
                    ->hidden()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Empresa')
                    ->description(fn (Client $record): ?string => $record->address)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Activo'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare),
                Action::make('generateReport')
                    ->label('Generar reporte')
                    ->icon(Heroicon::DocumentChartBar)
                    ->modalHeading(fn (Client $record): string => 'Generar reporte para '.$record->name)
                    ->modalSubmitActionLabel('Continuar')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->schema([
                        Hidden::make('date_from')
                            ->default(fn (): string => self::defaultReportRange()['date_from']),
                        Hidden::make('date_until')
                            ->default(fn (): string => self::defaultReportRange()['date_until']),
                    ])
                    ->modalContent(fn (Action $action, Client $record): View => self::reportVisitsPreview($record, $action->getRawData()))
                    ->action(function (array $data, Client $record) {
                        Gate::authorize('create', Report::class);
                        Gate::authorize('view', $record);

                        $defaults = self::defaultReportRange();
                        $session = session()->get(self::reportModalRangeSessionKey((int) $record->getKey()));

                        $dateFrom = is_array($session) ? ($session['date_from'] ?? null) : null;
                        $dateUntil = is_array($session) ? ($session['date_until'] ?? null) : null;

                        if (! filled($dateFrom) || ! filled($dateUntil)) {
                            $dateFrom = $data['date_from'] ?? $defaults['date_from'];
                            $dateUntil = $data['date_until'] ?? $defaults['date_until'];
                        }

                        $periodData = app(ReportPeriodData::class);
                        [$from, $until] = $periodData->normalizeRange((string) $dateFrom, (string) $dateUntil);

                        $pdfService = app(GenerateMonthlyReportPdfService::class);
                        $template = $pdfService->resolveTemplateForClient($record, null);
                        $period = $periodData->load($record, $from, $until);

                        $editorPayload = $template?->pdf_template ?? ReportPdfTemplateDefaults::bladeSourceForClient($record);
                        $mergedData = array_merge($period['snapshot'], [
                            ComposeReport::EDITOR_DATA_KEY => $editorPayload,
                        ]);

                        $report = Report::query()->create([
                            'client_id' => $record->id,
                            'generated_by_user_id' => auth()->id(),
                            'template_id' => $template?->id,
                            'month' => $from->month,
                            'year' => $from->year,
                            'date_from' => $from->toDateString(),
                            'date_until' => $until->toDateString(),
                            'status' => ReportStatus::Draft,
                            'data' => $mergedData,
                        ]);

                        return redirect()->to(ReportResource::getUrl('compose', [
                            'report_id' => $report->getKey(),
                            'client_id' => $record->getKey(),
                            'date_from' => $from->toDateString(),
                            'date_until' => $until->toDateString(),
                        ]));
                    }),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->icon(Heroicon::OutlinedTrash),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
            TemplatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'template' => EditClientTemplate::route('/{record}/template'),
            'base-template' => EditBasePdfTemplate::route('/base-template/{importMode}'),
        ];
    }

    public static function baseTemplateUrl(ClientImportMode $mode): string
    {
        return static::getUrl('base-template', ['importMode' => $mode->value]);
    }

    public static function reportModalRangeSessionKey(int|string $clientId): string
    {
        return 'filament.report_modal_range.'.(string) $clientId;
    }

    /**
     * @return array{date_from: string, date_until: string}
     */
    private static function defaultReportRange(): array
    {
        $start = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        return [
            'date_from' => $start->toDateString(),
            'date_until' => $start->endOfMonth()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function reportVisitsPreview(Client $client, array $data): View
    {
        $range = [
            ...self::defaultReportRange(),
            ...array_filter([
                'date_from' => $data['date_from'] ?? null,
                'date_until' => $data['date_until'] ?? null,
            ], filled(...)),
        ];

        return view('filament.resources.reports.pages.visits-preview-table', [
            'clientId' => (int) $client->getKey(),
            'dateFrom' => (string) $range['date_from'],
            'dateUntil' => (string) $range['date_until'],
        ]);
    }

    public static function getModelLabel(): string
    {
        return 'cliente';
    }

    public static function getPluralModelLabel(): string
    {
        return 'clientes';
    }
}
