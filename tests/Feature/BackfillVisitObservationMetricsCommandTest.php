<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createLegacyVisit(string $observation): Visit
{
    $client = Client::query()->firstOrCreate(
        ['name' => 'Cliente Backfill'],
        ['active' => true],
    );

    $employee = Employee::query()->firstOrCreate(
        ['name' => 'Técnico Backfill'],
        ['active' => true],
    );

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-03-10 16:00:00',
        'observation' => $observation,
    ]);

    Visit::query()->whereKey($visit->id)->update([
        'nests_removed' => null,
        'captures_falconry' => null,
        'captures_trap' => null,
    ]);

    return $visit->refresh();
}

it('backfills metrics for visits without extracted values', function (): void {
    $visit = createLegacyVisit("Se recorre planta 4 en su totalidad\nSe capturan dos palomas");

    expect($visit->captures_falconry)->toBeNull();

    $this->artisan('visits:backfill-observation-metrics')
        ->expectsOutputToContain('Visitas procesadas: 1. Visitas actualizadas: 1.')
        ->assertSuccessful();

    $visit->refresh();

    expect($visit->captures_falconry)->toBe(2)
        ->and($visit->captures_trap)->toBe(0)
        ->and($visit->nests_removed)->toBe(0);
});

it('skips visits that already have metrics unless force is used', function (): void {
    $visit = createLegacyVisit('Se captura una paloma');

    Visit::query()->whereKey($visit->id)->update([
        'nests_removed' => 9,
        'captures_falconry' => 9,
        'captures_trap' => 9,
    ]);

    $this->artisan('visits:backfill-observation-metrics')->assertSuccessful();

    expect($visit->refresh()->captures_falconry)->toBe(9);

    $this->artisan('visits:backfill-observation-metrics', ['--force' => true])->assertSuccessful();

    $visit->refresh();

    expect($visit->captures_falconry)->toBe(1)
        ->and($visit->captures_trap)->toBe(0)
        ->and($visit->nests_removed)->toBe(0);
});
