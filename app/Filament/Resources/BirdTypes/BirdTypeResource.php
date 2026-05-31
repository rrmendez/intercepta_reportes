<?php

namespace App\Filament\Resources\BirdTypes;

use App\Filament\Resources\BirdTypes\Pages\ListBirdTypes;
use App\Models\BirdType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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
use Illuminate\Support\Str;
use UnitEnum;

class BirdTypeResource extends Resource
{
    protected static ?string $model = BirdType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Tipos de ave';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Etiqueta de importacion (Excel)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Identificador')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Clave interna unica. Se sugiere automaticamente desde la etiqueta de importacion.'),
                TextInput::make('common_name')
                    ->label('Nombre comun (PDF)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('common_name_plural')
                    ->label('Plural (PDF)')
                    ->maxLength(255),
                Textarea::make('scientific_name')
                    ->label('Nombre cientifico')
                    ->rows(2)
                    ->columnSpanFull(),
                Repeater::make('aliases')
                    ->label('Variantes aceptadas al importar')
                    ->relationship()
                    ->schema([
                        TextInput::make('alias')
                            ->label('Alias')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Agregar variante')
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
                    ->label('Importacion')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('common_name')
                    ->label('Nombre comun')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('scientific_name')
                    ->label('Nombre cientifico')
                    ->searchable()
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('aliases_count')
                    ->label('Variantes')
                    ->counts('aliases')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Activo'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Editar tipo de ave')
                    ->modalSubmitActionLabel('Guardar')
                    ->mutateFormDataUsing(function (array $data): array {
                        if (filled($data['slug'] ?? null)) {
                            return $data;
                        }

                        $source = (string) ($data['name'] ?? $data['common_name'] ?? '');
                        $data['slug'] = Str::slug($source) !== '' ? Str::slug($source) : Str::slug($source, '-');

                        return $data;
                    }),
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
