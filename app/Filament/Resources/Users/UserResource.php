<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Users;

    protected static string|UnitEnum|null $navigationGroup = 'Administracion';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Usuario')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                        Select::make('roles')
                            ->label('Rol')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->minItems(1)
                            ->maxItems(1)
                            ->preload()
                            ->searchable()
                            ->required(),
                        TextInput::make('password')
                            ->label('Contrasena')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->confirmed()
                            ->minLength(8)
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('password_confirmation')
                            ->label('Confirmar contrasena')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Verificado')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rol')
                    ->relationship('roles', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('changePassword')
                    ->label('Cambiar contrasena')
                    ->icon(Heroicon::OutlinedKey)
                    ->schema([
                        TextInput::make('password')
                            ->label('Contrasena')
                            ->password()
                            ->revealable()
                            ->required()
                            ->confirmed()
                            ->minLength(8),
                        TextInput::make('password_confirmation')
                            ->label('Confirmar contrasena')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'password' => Hash::make($data['password']),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Contrasena actualizada correctamente')
                            ->send();
                    }),
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
        return 'usuario';
    }

    public static function getPluralModelLabel(): string
    {
        return 'usuarios';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
