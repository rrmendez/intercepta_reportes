<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Identification;

    protected static string|UnitEnum|null $navigationGroup = 'Administracion';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Empleados';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Telefono')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('document_number')
                    ->label('Documento')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                DatePicker::make('birthday')
                    ->label('Fecha de nacimiento'),
                Textarea::make('address')
                    ->label('Direccion')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('active')
                    ->label('Activo')
                    ->default(true)
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
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('birthday')
                    ->label('Fecha de nacimiento')
                    ->date()
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
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
        return 'empleado';
    }

    public static function getPluralModelLabel(): string
    {
        return 'empleados';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
        ];
    }
}
