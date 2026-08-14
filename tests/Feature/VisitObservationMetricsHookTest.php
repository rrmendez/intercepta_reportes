<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createVisitWithObservation(string $observation): Visit
{
    $client = Client::query()->firstOrCreate(
        ['name' => 'Cliente Metricas'],
        ['active' => true],
    );

    $employee = Employee::query()->firstOrCreate(
        ['name' => 'Técnico Metricas'],
        ['active' => true],
    );

    return Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-03-10 16:00:00',
        'date_end' => '2026-03-10 17:00:00',
        'observation' => $observation,
    ]);
}

it('extracts observation metrics when a visit is created', function (): void {
    $visit = createVisitWithObservation("Se recorre planta 4 en su totalidad\nSe capturan dos palomas");

    expect($visit->captures_falconry)->toBe(2)
        ->and($visit->captures_trap)->toBe(0)
        ->and($visit->nests_removed)->toBe(0);
});

it('recalculates metrics when the observation changes', function (): void {
    $visit = createVisitWithObservation('Se captura una paloma');

    $visit->observation = "Se quita nido en Doblados\nSe capturaron 3 palomas en trampa";
    $visit->save();

    expect($visit->refresh())
        ->nests_removed->toBe(1)
        ->captures_falconry->toBe(0)
        ->captures_trap->toBe(3);
});

it('keeps manual metric edits until the observation changes again', function (): void {
    $visit = createVisitWithObservation('Se captura una paloma');

    $visit->captures_falconry = 5;
    $visit->save();

    expect($visit->refresh()->captures_falconry)->toBe(5);

    $visit->observation = 'Se capturan dos palomas';
    $visit->save();

    expect($visit->refresh()->captures_falconry)->toBe(2);
});

it('extracts metrics on create even when metric keys are present as null', function (): void {
    $client = Client::query()->firstOrCreate(
        ['name' => 'Cliente Metricas'],
        ['active' => true],
    );

    $employee = Employee::query()->firstOrCreate(
        ['name' => 'Técnico Metricas'],
        ['active' => true],
    );

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-03-10 16:00:00',
        'observation' => 'Se capturan 3 palomas',
        'nests_removed' => null,
        'captures_falconry' => null,
        'captures_trap' => null,
    ]);

    expect($visit->captures_falconry)->toBe(3);
});

it('respects explicit metric values provided on create', function (): void {
    $client = Client::query()->firstOrCreate(
        ['name' => 'Cliente Metricas'],
        ['active' => true],
    );

    $employee = Employee::query()->firstOrCreate(
        ['name' => 'Técnico Metricas'],
        ['active' => true],
    );

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-03-10 16:00:00',
        'observation' => 'Se capturan 3 palomas',
        'captures_falconry' => 8,
    ]);

    expect($visit->captures_falconry)->toBe(8);
});

it('prefers manual metric edits when observation and metrics change together', function (): void {
    $visit = createVisitWithObservation('Se captura una paloma');

    $visit->observation = 'Se capturan dos palomas';
    $visit->captures_falconry = 9;
    $visit->save();

    expect($visit->refresh()->captures_falconry)->toBe(9);
});
