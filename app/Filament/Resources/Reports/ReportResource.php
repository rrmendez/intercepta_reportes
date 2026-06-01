<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\ComposeReport;
use App\Filament\Resources\Reports\Pages\CreateReport;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Client;
use App\Models\Report;
use App\Models\Template;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'General';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $recordTitleAttribute = 'generated_filename';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('client_id')
                ->label('Cliente')
                ->options(fn (): array => Client::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set): mixed => $set('template_id', null)),
            Select::make('template_id')
                ->label('Plantilla')
                ->options(fn (Get $get): array => filled($get('client_id'))
                    ? Template::query()
                        ->where('client_id', (int) $get('client_id'))
                        ->orderByDesc('active')
                        ->orderByDesc('id')
                        ->pluck('name', 'id')
                        ->all()
                    : [])
                ->searchable()
                ->preload()
                ->helperText('Si no seleccionas una plantilla, se usara la plantilla activa del cliente.')
                ->live(),
            DatePicker::make('date_from')
                ->label('Desde')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->default(fn (): string => CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth()->toDateString())
                ->required()
                ->live(),
            DatePicker::make('date_until')
                ->label('Hasta')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->default(fn (): string => CarbonImmutable::now()->subMonthNoOverflow()->endOfMonth()->toDateString())
                ->required()
                ->afterOrEqual('date_from')
                ->live(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('generated_filename')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'client',
                'generatedBy',
            ]))
            ->recordUrl(fn (Report $record): ?string => Gate::allows('view', $record)
                ? static::getUrl('compose', [
                    'report_id' => $record->getKey(),
                    'client_id' => $record->client_id,
                    'date_from' => $record->date_from?->toDateString() ?? now()->toDateString(),
                    'date_until' => $record->date_until?->toDateString() ?? now()->toDateString(),
                ])
                : null)
            ->columns([
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('generatedBy.name')
                    ->label('Generado por')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Fecha de creacion')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('date_from')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('date_until')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('generated_at')
                    ->label('Reporte generado')
                    ->state(fn (Report $record): ?string => Gate::allows('view', $record)
                        ? 'Ver PDF'
                        : null)
                    ->placeholder('-')
                    ->url(fn (Report $record): ?string => Gate::allows('view', $record)
                        ? static::viewPdfUrl($record)
                        : null)
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('email_sent_at')
                    ->label('Correo enviado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_between')
                    ->label('Fecha de creacion')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label('Creado desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('created_until')
                            ->label('Creado hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['created_from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('reports.created_at', '>=', $data['created_from']),
                            )
                            ->when(
                                filled($data['created_until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('reports.created_at', '<=', $data['created_until']),
                            );
                    }),
                Filter::make('period_date_from')
                    ->label('Periodo')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('Desde (periodo)')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['date_from'] ?? null),
                            fn (Builder $q): Builder => $q->whereDate('reports.date_from', '>=', $data['date_from']),
                        );
                    }),
            ])
            ->recordActions([
                Action::make('compose')
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Report $record): string => static::getUrl('compose', [
                        'report_id' => $record->getKey(),
                        'client_id' => $record->client_id,
                        'date_from' => $record->date_from?->toDateString() ?? now()->toDateString(),
                        'date_until' => $record->date_until?->toDateString() ?? now()->toDateString(),
                    ]))
                    ->visible(fn (Report $record): bool => Gate::allows('view', $record)),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->icon(Heroicon::OutlinedTrash),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function viewPdfUrl(Report $report): string
    {
        return Filament::getPanel('admin')->route('reports.download-pdf', ['report' => $report]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getModelLabel(): string
    {
        return 'reporte';
    }

    public static function getPluralModelLabel(): string
    {
        return 'reportes';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
            'create' => CreateReport::route('/create'),
            'compose' => ComposeReport::route('/compose'),
        ];
    }
}
