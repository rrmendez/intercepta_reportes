<?php

namespace App\Filament\Resources\Sections;

use App\Filament\Resources\Sections\Pages\CreateSection;
use App\Filament\Resources\Sections\Pages\EditSection;
use App\Filament\Resources\Sections\Pages\ListSections;
use App\Models\Section;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SectionResource extends Resource
{
    protected static ?string $model = Section::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::QueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Segmentos';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make('Datos del segmento')
                    ->schema([
                        Select::make('template_id')
                            ->label('Plantilla')
                            ->relationship('template', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->required(),
                        TextInput::make('title')
                            ->label('Titulo')
                            ->columnSpanFull()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('order')
                            ->label('Orden')
                            ->columnSpanFull()
                            ->numeric()
                            ->integer()
                            ->required()
                            ->default(0),
                        RichEditor::make('text')
                            ->label('Texto')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('template.name')
                    ->label('Plantilla')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Titulo')
                    ->searchable(),
                TextColumn::make('order')
                    ->label('Orden')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('template')
                    ->label('Plantilla')
                    ->relationship('template', 'name'),
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
            'index' => ListSections::route('/'),
            'create' => CreateSection::route('/create'),
            'edit' => EditSection::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'segmento';
    }

    public static function getPluralModelLabel(): string
    {
        return 'segmentos';
    }
}
