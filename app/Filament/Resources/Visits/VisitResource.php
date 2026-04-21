<?php

namespace App\Filament\Resources\Visits;

use App\Filament\Resources\Visits\Pages\CreateVisit;
use App\Filament\Resources\Visits\Pages\EditVisit;
use App\Filament\Resources\Visits\Pages\ListVisits;
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

    protected static string|UnitEnum|null $navigationGroup = 'Business';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Visits';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Visit')
                    ->schema([
                        Select::make('client_id')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('visitReports', [])),
                        Select::make('employee_id')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DateTimePicker::make('date_init')
                            ->required()
                            ->seconds(false),
                        DateTimePicker::make('date_end')
                            ->seconds(false),
                        Select::make('status')
                            ->options(VisitStatus::options())
                            ->default(VisitStatus::Scheduled->value)
                            ->required(),
                        Textarea::make('observation')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Visit Reports')
                    ->schema([
                        Repeater::make('visitReports')
                            ->relationship()
                            ->schema([
                                Select::make('location_id')
                                    ->label('Location')
                                    ->options(fn (Get $get): array => Location::query()
                                        ->where('client_id', $get('../../client_id'))
                                        ->where('active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled(fn (Get $get): bool => blank($get('../../client_id'))),
                                Select::make('bird_type_id')
                                    ->label('Bird Type')
                                    ->options(fn (): array => BirdType::query()
                                        ->where('active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->required(),
                                Textarea::make('observation')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add Observation Detail')
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
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date_init')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('date_end')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
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
                    ->label('Quantity'),
                TextColumn::make('observation')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client')
                    ->relationship('client', 'name'),
                SelectFilter::make('employee')
                    ->relationship('employee', 'name'),
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
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

    public static function getPages(): array
    {
        return [
            'index' => ListVisits::route('/'),
            'create' => CreateVisit::route('/create'),
            'edit' => EditVisit::route('/{record}/edit'),
        ];
    }
}
