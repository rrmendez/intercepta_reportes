<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\CreateReport;
use App\Filament\Resources\Reports\Pages\EditReport;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Report;
use App\ReportStatus;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report Data')
                    ->schema([
                        Select::make('client_id')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('template_id')
                            ->relationship('template', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('month')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(12)
                            ->required(),
                        TextInput::make('year')
                            ->numeric()
                            ->integer()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->required(),
                        Select::make('status')
                            ->options(ReportStatus::options())
                            ->required(),
                        DateTimePicker::make('generated_at')
                            ->seconds(false),
                        TextInput::make('generated_file_path')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Placeholder::make('summary')
                            ->label('Summary (JSON)')
                            ->content(fn (?Report $record): string => $record?->data ? json_encode($record->data, JSON_PRETTY_PRINT) ?: '' : '-')
                            ->columnSpanFull(),
                        Textarea::make('data')
                            ->dehydrated(false)
                            ->disabled()
                            ->rows(6)
                            ->visible(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('month')
                    ->sortable(),
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ReportStatus|string|null $state): string => $state instanceof ReportStatus ? $state->label() : (ReportStatus::tryFrom((string) $state)?->label() ?? (string) $state)),
                TextColumn::make('generated_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('client')
                    ->relationship('client', 'name'),
                SelectFilter::make('month')
                    ->options(
                        collect(range(1, 12))
                            ->mapWithKeys(fn (int $month): array => [(string) $month => str_pad((string) $month, 2, '0', STR_PAD_LEFT)])
                            ->all()
                    ),
                SelectFilter::make('year')
                    ->options(
                        collect(range((int) date('Y') - 2, (int) date('Y') + 2))
                            ->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])
                            ->all()
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListReports::route('/'),
            'create' => CreateReport::route('/create'),
            'edit' => EditReport::route('/{record}/edit'),
        ];
    }
}
