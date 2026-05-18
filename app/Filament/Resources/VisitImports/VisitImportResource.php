<?php

namespace App\Filament\Resources\VisitImports;

use App\Filament\Resources\VisitImports\Pages\ListVisitImports;
use App\Filament\Resources\VisitImports\Pages\ViewVisitImport;
use App\Models\VisitImport;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class VisitImportResource extends Resource
{
    protected static ?string $model = VisitImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Importaciones de visitas';

    protected static ?string $recordTitleAttribute = 'original_filename';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Resumen')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Fecha')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('client.name')
                            ->label('Empresa')
                            ->placeholder('-'),
                        TextEntry::make('user.name')
                            ->label('Usuario')
                            ->placeholder('-'),
                        TextEntry::make('original_filename')
                            ->label('Archivo'),
                        TextEntry::make('stored_file_path')
                            ->label('Ruta almacenada')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('summary_message')
                            ->label('Resumen')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('import_status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'success' => 'success',
                                'partial' => 'warning',
                                'failed' => 'danger',
                                'processing' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('visits_count')
                            ->label('Visitas vinculadas')
                            ->state(fn (VisitImport $record): int => $record->visits()->count()),
                        TextEntry::make('total_rows')
                            ->label('Filas en archivo'),
                        TextEntry::make('persisted_rows')
                            ->label('Importadas OK'),
                        TextEntry::make('skipped_rows')
                            ->label('Fallidas (persistencia)'),
                        TextEntry::make('invalid_rows')
                            ->label('Filas invalidas (validacion)'),
                    ])
                    ->columns(2),
                Section::make('Advertencias')
                    ->columnSpanFull()
                    ->visible(fn (VisitImport $record): bool => $record->warnings !== null && $record->warnings !== [])
                    ->schema([
                        TextEntry::make('warnings')
                            ->label('')
                            ->formatStateUsing(fn (?array $state): string => $state === null || $state === []
                                ? '-'
                                : collect($state)->map(fn (string $line): string => '• '.$line)->implode("\n"))
                            ->columnSpanFull(),
                    ]),
                Section::make('Errores')
                    ->columnSpanFull()
                    ->visible(fn (VisitImport $record): bool => $record->errors !== null && $record->errors !== [])
                    ->schema([
                        TextEntry::make('errors')
                            ->label('')
                            ->formatStateUsing(fn (?array $state): string => $state === null || $state === []
                                ? '-'
                                : collect($state)->map(fn (string $line): string => '• '.$line)->implode("\n"))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('original_filename')
                    ->label('Archivo')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('import_status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'partial' => 'warning',
                        'failed' => 'danger',
                        'processing' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('visits_count')
                    ->label('Visitas')
                    ->counts('visits'),
                TextColumn::make('persisted_rows')
                    ->label('OK')
                    ->sortable(),
                TextColumn::make('skipped_rows')
                    ->label('Fallidas')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisitImports::route('/'),
            'view' => ViewVisitImport::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return 'importacion de visitas';
    }

    public static function getPluralModelLabel(): string
    {
        return 'importaciones de visitas';
    }
}
