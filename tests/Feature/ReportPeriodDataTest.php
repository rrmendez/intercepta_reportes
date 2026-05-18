<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\Visit;
use App\Services\Reports\ReportPeriodData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes the upper bound to end of day', function (): void {
    [$from, $until] = app(ReportPeriodData::class)->normalizeRange('2026-05-01', '2026-05-14');

    expect($from)->toBeInstanceOf(CarbonImmutable::class)
        ->and($from->toDateTimeString())->toBe('2026-05-01 00:00:00')
        ->and($until)->toBeInstanceOf(CarbonImmutable::class)
        ->and($until->toDateString())->toBe('2026-05-14')
        ->and($until->isEndOfDay())->toBeTrue();
});

it('loads visits for a period using the same spreadsheet filter row as the preview table', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente filtro',
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-02 10:00:00',
        'date_end' => '2026-05-02 11:00:00',
        'observation' => 'Dentro',
    ]);

    Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-06-01 10:00:00',
        'date_end' => '2026-06-01 11:00:00',
        'observation' => 'Fuera',
    ]);

    $row = [
        'mode' => 'custom_range',
        'date_from' => '2026-05-01',
        'date_until' => '2026-05-31',
    ];

    $period = app(ReportPeriodData::class)->loadForSpreadsheetRow($client, $row, null);

    expect($period['visits'])->toHaveCount(1)
        ->and($period['visits']->first()->observation)->toBe('Dentro');
});

it('includes visits on the last calendar day when loading by date range', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente periodo',
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-14 15:30:00',
        'date_end' => '2026-05-14 16:00:00',
        'observation' => 'Visita tarde ultimo dia',
    ]);

    $period = app(ReportPeriodData::class)->load($client, '2026-05-01', '2026-05-14');

    expect($period['visits']->count())->toBe(1);
});
