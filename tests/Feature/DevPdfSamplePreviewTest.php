<?php

use App\ClientImportMode;

test('pdf sample preview route respects environment', function (): void {
    $response = $this->get('/dev/pdf-sample');

    if (app()->isLocal()) {
        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Conaprole Planta Industrial', false)
            ->assertSee('dev-pdf-preview-toolbar', false)
            ->assertSee('Registro del control de fauna', false)
            ->assertSee('Conteo palomas', false)
            ->assertSee('Se captura 1 paloma', false)
            ->assertSee('Evolución del control de fauna', false)
            ->assertSee('CONTACTO', false)
            ->assertSee('report-chart-fauna-period', false)
            ->assertSee('report-pdf-fixed-footer-root', false);
    } else {
        $response->assertNotFound();
    }
});

it('injects margin debug css when debug_margins is set', function (): void {
    if (! app()->isLocal()) {
        expect(true)->toBeTrue();

        return;
    }

    $this->get('/dev/pdf-sample?debug_margins=1')
        ->assertOk()
        ->assertSee('dev-pdf-margin-debug', false)
        ->assertSee('box-shadow: inset', false)
        ->assertSee('outline: 4px solid #a21caf', false);
});

it('loads single sector single bird template by default', function (): void {
    if (! app()->isLocal()) {
        expect(true)->toBeTrue();

        return;
    }

    $this->get('/dev/pdf-sample')
        ->assertOk()
        ->assertSee('Situación inicial del predio', false);
});

it('accepts template query for each import mode', function (): void {
    if (! app()->isLocal()) {
        expect(true)->toBeTrue();

        return;
    }

    foreach (ClientImportMode::cases() as $mode) {
        $this->get('/dev/pdf-sample?template='.$mode->value)
            ->assertOk();
    }
});
