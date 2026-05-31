<?php

use App\Filament\Resources\BirdTypes\BirdTypeResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Visits\VisitResource;
use App\ReportStatus;
use App\VisitStatus;

it('uses Spanish locale and translated labels', function () {
    expect(config('app.locale'))->toBe('es')
        ->and(config('app.faker_locale'))->toBe('es_ES')
        ->and(ClientResource::getNavigationGroup())->toBe('General')
        ->and(ClientResource::getNavigationLabel())->toBe('Clientes')
        ->and(VisitResource::getNavigationGroup())->toBe('General')
        ->and(VisitResource::getNavigationLabel())->toBe('Visitas')
        ->and(BirdTypeResource::getNavigationGroup())->toBe('Operaciones')
        ->and(BirdTypeResource::getNavigationLabel())->toBe('Tipos de ave')
        ->and(VisitStatus::Scheduled->label())->toBe('Programada')
        ->and(ReportStatus::Generated->label())->toBe('Generado')
        ->and(__('validation.required', ['attribute' => 'nombre']))->toBe('El campo nombre es obligatorio.');
});
