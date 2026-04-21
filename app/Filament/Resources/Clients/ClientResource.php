<?php

namespace App\Filament\Resources\Clients;

// use App\Filament\Resources\Clients\Pages\CreateClient;
// use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\SectionsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\TemplatesRelationManager;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Business';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Clients';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Company Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('address')
                    ->label('Address')
                    ->columnSpanFull()
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Notes')
                    ->hidden()
                    ->rows(3)
                    ->columnSpanFull(),
                Section::make('Sections')
                    ->description('Define the sections for this client. These are used when importing visit reports to associate visits with specific sections within the client.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('locations')
                            ->hiddenLabel()
                            ->relationship()
                            ->deletable(fn (?array $state): bool => count($state ?? []) > 1)
                            ->simple(
                                TextInput::make('name')
                                    ->label('Name')
                                    ->placeholder('Section name')
                                    ->required()
                                    ->maxLength(255),
                            )
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->addActionLabel('Add Section')
                            ->columnSpanFull(),
                    ]),
                Toggle::make('active')
                    ->label('Active')
                    ->default(true)
                    ->columnSpanFull()
                    ->hidden()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->recordUrl(fn (Client $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('locations_count')
                    ->counts('locations')
                    ->label('Sections'),
                TextColumn::make('visits_count')
                    ->counts('visits')
                    ->label('Visits'),
            ])
            ->filters([
                TernaryFilter::make('active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
            TemplatesRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('General Info')
                    ->columnSpanFull()
                    ->headerActions([
                        Action::make('edit')
                            ->label('Edit')
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->url(fn (Client $record): string => static::getUrl('edit', ['record' => $record])),
                    ])
                    ->schema([
                        TextEntry::make('name')
                            ->label('Company Name'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('-'),
                        TextEntry::make('address')
                            ->label('Address')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        IconEntry::make('active')
                            ->label('Active')
                            ->boolean(),
                        TextEntry::make('locations_count')
                            ->label('Sections')
                            ->state(fn (Client $record): int => $record->locations()->count()),
                        TextEntry::make('templates_count')
                            ->label('Templates')
                            ->state(fn (Client $record): int => $record->templates()->count()),
                        TextEntry::make('visits_count')
                            ->label('Visits')
                            ->state(fn (Client $record): int => $record->visits()->count()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'view' => ViewClient::route('/{record}'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
