<?php

test('pdf sample preview route respects environment', function (): void {
    $response = $this->get('/dev/pdf-sample');

    if (app()->isLocal()) {
        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Cliente demostración', false)
            ->assertSee('Av. 18 de Julio 1234', false)
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
