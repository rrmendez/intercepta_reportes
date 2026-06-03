<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Visit;
use App\ReportStatus;
use App\Services\Clients\ClientListingMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-06-02 10:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('calculates average visits per client', function (): void {
    $employee = Employee::query()->create([
        'name' => 'Operario',
        'active' => true,
    ]);

    $clients = collect([
        Client::query()->create(['name' => 'Cliente A', 'active' => true]),
        Client::query()->create(['name' => 'Cliente B', 'active' => true]),
    ]);

    foreach ($clients as $client) {
        foreach (range(1, 3) as $index) {
            Visit::query()->create([
                'client_id' => $client->id,
                'employee_id' => $employee->id,
                'date_init' => now()->subDays($index),
            ]);
        }
    }

    $metrics = app(ClientListingMetricsService::class);

    expect($metrics->averageVisitsPerClient())->toBe(3.0)
        ->and($metrics->clientCount())->toBe(2)
        ->and($metrics->visitCount())->toBe(6);
});

it('counts sent reports and last month sent reports', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Reportes',
        'active' => true,
    ]);

    Report::query()->create([
        'client_id' => $client->id,
        'month' => 5,
        'year' => 2026,
        'status' => ReportStatus::Sent,
        'email_sent_at' => '2026-05-15 12:00:00',
    ]);

    Report::query()->create([
        'client_id' => $client->id,
        'month' => 6,
        'year' => 2026,
        'status' => ReportStatus::Sent,
        'email_sent_at' => '2026-06-01 09:00:00',
    ]);

    Report::query()->create([
        'client_id' => $client->id,
        'month' => 6,
        'year' => 2026,
        'status' => ReportStatus::Draft,
    ]);

    $metrics = app(ClientListingMetricsService::class);

    expect($metrics->sentReportsCount())->toBe(2)
        ->and($metrics->sentReportsLastMonthCount())->toBe(1)
        ->and($metrics->lastMonthLabel())->toBe('Mayo 2026');
});

it('returns six monthly trend points', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Tendencia',
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Operario',
        'active' => true,
    ]);

    Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-06-01 08:00:00',
    ]);

    Report::query()->create([
        'client_id' => $client->id,
        'month' => 6,
        'year' => 2026,
        'status' => ReportStatus::Sent,
        'email_sent_at' => '2026-06-01 09:00:00',
    ]);

    $trends = app(ClientListingMetricsService::class)->monthlyTrends();

    expect($trends['labels'])->toHaveCount(6)
        ->and($trends['visitsAverage'])->toHaveCount(6)
        ->and($trends['sentReports'])->toHaveCount(6)
        ->and($trends['visitsAverage'][5])->toBe(1.0)
        ->and($trends['sentReports'][5])->toBe(1);
});

it('returns zero averages when there are no clients', function (): void {
    $metrics = app(ClientListingMetricsService::class);

    expect($metrics->averageVisitsPerClient())->toBe(0.0)
        ->and($metrics->sentReportsCount())->toBe(0)
        ->and($metrics->sentReportsLastMonthCount())->toBe(0);
});
