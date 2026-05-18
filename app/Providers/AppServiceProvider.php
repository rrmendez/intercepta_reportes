<?php

namespace App\Providers;

use App\Contracts\HtmlToPdfConverter;
use App\Services\PdfHtmlToBinaryConverter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HtmlToPdfConverter::class, PdfHtmlToBinaryConverter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CreateAction::configureUsing(fn (CreateAction $action): CreateAction => $action->label('Crear'));
        ViewAction::configureUsing(fn (ViewAction $action): ViewAction => $action->label('Ver'));
        EditAction::configureUsing(fn (EditAction $action): EditAction => $action->label('Editar'));
        DeleteAction::configureUsing(fn (DeleteAction $action): DeleteAction => $action->label('Eliminar'));
        DeleteBulkAction::configureUsing(fn (DeleteBulkAction $action): DeleteBulkAction => $action->label('Eliminar seleccionados'));
        BulkActionGroup::configureUsing(fn (BulkActionGroup $action): BulkActionGroup => $action->label('Acciones'));
    }
}
