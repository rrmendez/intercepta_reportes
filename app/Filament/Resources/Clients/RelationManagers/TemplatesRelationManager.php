<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'templates';

    protected static ?string $title = 'Plantillas';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                Repeater::make('sections')
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
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
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
                TextColumn::make('sections_count')
                    ->counts('sections')
                    ->label('Segmentos'),
            ])
            ->headerActions([
                CreateAction::make(),
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
}
