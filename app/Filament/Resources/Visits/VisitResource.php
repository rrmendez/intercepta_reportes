<?php

namespace App\Filament\Resources\Visits;

use App\Filament\Resources\Visits\Pages\CreateVisit;
use App\Filament\Resources\Visits\Pages\EditVisit;
use App\Filament\Resources\Visits\Pages\ListVisitsSpreadsheet;
use App\Models\BirdType;
use App\Models\Location;
use App\Models\Visit;
use App\VisitStatus;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'General';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Visitas';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Visita')
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('visitReports', [])),
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DateTimePicker::make('date_init')
                            ->label('Fecha de inicio')
                            ->required()
                            ->seconds(false),
                        DateTimePicker::make('date_end')
                            ->label('Fecha de finalizacion')
                            ->seconds(false),
                        Select::make('status')
                            ->label('Estado')
                            ->options(VisitStatus::options())
                            ->default(VisitStatus::Scheduled->value)
                            ->required(),
                        Textarea::make('observation')
                            ->label('Observacion')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Detalles de observacion')
                    ->schema([
                        Repeater::make('visitReports')
                            ->relationship()
                            ->schema([
                                Select::make('location_id')
                                    ->label('Seccion')
                                    ->options(fn (Get $get): array => Location::query()
                                        ->where('client_id', $get('../../client_id'))
                                        ->where('active', true)
                                        ->excludingInternalDefault()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled(fn (Get $get): bool => blank($get('../../client_id'))),
                                Select::make('bird_type_id')
                                    ->label('Tipo de ave')
                                    ->options(fn (): array => BirdType::query()
                                        ->where('active', true)
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn (BirdType $birdType): array => [
                                            $birdType->id => trim($birdType->name.' — '.$birdType->common_name),
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->required(),
                                Textarea::make('observation')
                                    ->label('Observacion')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar detalle de observacion')
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['client', 'employee']))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date_init')
                    ->label('Inicio')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('date_end')
                    ->label('Finalizacion')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(function (VisitStatus|string|null $state): string {
                        if ($state instanceof VisitStatus) {
                            return $state->label();
                        }

                        return VisitStatus::tryFrom((string) $state)?->label() ?? (string) $state;
                    })
                    ->color(function (VisitStatus|string|null $state): string {
                        $value = $state instanceof VisitStatus
                            ? $state->value
                            : (string) $state;

                        return match ($value) {
                            VisitStatus::Completed->value => 'success',
                            VisitStatus::InProgress->value => 'warning',
                            VisitStatus::Cancelled->value => 'danger',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('visit_reports_count')
                    ->counts('visitReports')
                    ->label('Cantidad'),
                TextColumn::make('observation')
                    ->label('Observacion')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client')
                    ->label('Cliente')
                    ->relationship('client', 'name'),
                SelectFilter::make('employee')
                    ->label('Empleado')
                    ->relationship('employee', 'name'),
                Filter::make('date_range')
                    ->label('Rango de fechas')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Desde'),
                        DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('date_init', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('date_init', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date_init', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getModelLabel(): string
    {
        return 'visita';
    }

    public static function getPluralModelLabel(): string
    {
        return 'visitas';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisitsSpreadsheet::route('/'),
            'create' => CreateVisit::route('/create'),
            'edit' => EditVisit::route('/{record}/edit'),
        ];
    }
}
