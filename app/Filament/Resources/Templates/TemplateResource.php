<?php

namespace App\Filament\Resources\Templates;

use App\Filament\Resources\Templates\Pages\CreateTemplate;
use App\Filament\Resources\Templates\Pages\EditTemplate;
use App\Filament\Resources\Templates\Pages\ListTemplates;
use App\Models\Template;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class TemplateResource extends Resource
{
    protected static ?string $model = Template::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Plantillas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la plantilla')
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('active')
                            ->label('Activo')
                            ->default(true)
                            ->required(),
                        RichEditor::make('content')
                            ->label('Contenido general')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'bulletList',
                                'orderedList',
                                'redo',
                                'undo',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Segmentos')
                    ->schema([
                        Repeater::make('sections')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('order')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Titulo')
                                    ->required()
                                    ->maxLength(255),
                                RichEditor::make('text')
                                    ->label('Texto')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'underline',
                                        'bulletList',
                                        'orderedList',
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->reorderableWithButtons()
                            ->defaultItems(0)
                            ->addActionLabel('Agregar segmento')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sections_count')
                    ->counts('sections')
                    ->label('Segmentos'),
            ])
            ->filters([
                SelectFilter::make('client')
                    ->label('Cliente')
                    ->relationship('client', 'name'),
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
        return 'plantilla';
    }

    public static function getPluralModelLabel(): string
    {
        return 'plantillas';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemplates::route('/'),
            'create' => CreateTemplate::route('/create'),
            'edit' => EditTemplate::route('/{record}/edit'),
        ];
    }
}
