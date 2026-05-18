<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\Visit;
use App\Services\Reports\ReportPeriodData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('normalizes the upper bound to end of the inclusive calendar day', function (): void {
    [$from, $until] = app(ReportPeriodData::class)->normalizeRange(
        '2026-05-01',
        '2026-05-14',
    );

    expect($from->toDateString())->toBe('2026-05-01')
        ->and($from->hour)->toBe(0)
        ->and($from->minute)->toBe(0)
        ->and($until->toDateString())->toBe('2026-05-14')
        ->and($until->isEndOfDay())->toBeTrue();
});

it('rejects a range where the start calendar day is after the end calendar day', function (): void {
    app(ReportPeriodData::class)->normalizeRange(
        '2026-05-15',
        '2026-05-14',
    );
})->throws(InvalidArgumentException::class);

it('includes visits on the last calendar day when filtering by date range', function (): void {
    $client = Client::query()->create([
        'name' => 'Acme',
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Tech',
        'active' => true,
    ]);

    Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-14 14:30:00',
        'date_end' => '2026-05-14 15:00:00',
    ]);

    $period = app(ReportPeriodData::class)->load($client, '2026-05-01', '2026-05-14');

    expect($period['visits'])->toHaveCount(1);
});

it('labels a full calendar month when the range spans the entire month', function (): void {
    $label = app(ReportPeriodData::class)->periodLabel(
        CarbonImmutable::parse('2026-05-01')->startOfDay(),
        CarbonImmutable::parse('2026-05-31')->endOfDay(),
    );

    expect($label)->toBe('Mayo 2026');
});
