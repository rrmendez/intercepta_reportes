<?php

namespace App\Filament\Resources\BirdTypes;

use App\Filament\Resources\BirdTypes\Pages\ListBirdTypes;
use App\Models\BirdType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class BirdTypeResource extends Resource
{
    protected static ?string $model = BirdType::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Administracion';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Tipos de ave';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Observaciones')
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('active')
                    ->label('Activo')
                    ->default(true)
                    ->columnSpanFull()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Observaciones')
                    ->searchable(),
            ])
            ->filters([
                TernaryFilter::make('active'),
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

    public static function getModelLabel(): string
    {
        return 'tipo de ave';
    }

    public static function getPluralModelLabel(): string
    {
        return 'tipos de ave';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBirdTypes::route('/'),
        ];
    }
}
