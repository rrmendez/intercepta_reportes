<?php

use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\Reports\ReportChartSeriesBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds daily line chart series grouped by bird type and location', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente graficos',
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    $locationA = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Sector A',
        'active' => true,
    ]);

    $locationB = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Sector B',
        'active' => true,
    ]);

    $palomas = BirdType::query()->create([
        'name' => 'Palomas',
        'active' => true,
    ]);

    $gaviotas = BirdType::query()->create([
        'name' => 'Gaviotas',
        'active' => true,
    ]);

    $visitDayOne = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-10 09:00:00',
        'date_end' => '2026-05-10 10:00:00',
    ]);

    $visitDayTwo = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-12 09:00:00',
        'date_end' => '2026-05-12 10:00:00',
    ]);

    VisitReport::query()->create([
        'visit_id' => $visitDayOne->id,
        'location_id' => $locationA->id,
        'bird_type_id' => $palomas->id,
        'quantity' => 3,
    ]);

    VisitReport::query()->create([
        'visit_id' => $visitDayOne->id,
        'location_id' => $locationB->id,
        'bird_type_id' => $gaviotas->id,
        'quantity' => 2,
    ]);

    VisitReport::query()->create([
        'visit_id' => $visitDayTwo->id,
        'location_id' => $locationA->id,
        'bird_type_id' => $palomas->id,
        'quantity' => 5,
    ]);

    $visitReports = VisitReport::query()
        ->with(['visit', 'location', 'birdType'])
        ->get();

    $config = app(ReportChartSeriesBuilder::class)->build(
        $visitReports,
        '2026-05-10',
        '2026-05-12',
    );

    expect($config['charts'])->toHaveCount(2)
        ->and($config['charts'][0]['id'])->toBe('report-chart-bird-type')
        ->and($config['charts'][0]['labels'])->toBe(['10/05', '11/05', '12/05']);

    $palomasDataset = collect($config['charts'][0]['datasets'])->firstWhere('label', 'Palomas');
    $gaviotasDataset = collect($config['charts'][0]['datasets'])->firstWhere('label', 'Gaviotas');

    expect($palomasDataset['data'])->toBe([3, 0, 5])
        ->and($gaviotasDataset['data'])->toBe([2, 0, 0]);

    $sectorADataset = collect($config['charts'][1]['datasets'])->firstWhere('label', 'Sector A');
    $sectorBDataset = collect($config['charts'][1]['datasets'])->firstWhere('label', 'Sector B');

    expect($sectorADataset['data'])->toBe([3, 0, 5])
        ->and($sectorBDataset['data'])->toBe([2, 0, 0]);
});

it('returns empty datasets when there are no visit reports', function (): void {
    $config = app(ReportChartSeriesBuilder::class)->build(
        collect(),
        '2026-05-01',
        '2026-05-03',
    );

    expect($config['charts'][0]['datasets'])->toBe([])
        ->and($config['charts'][1]['datasets'])->toBe([])
        ->and($config['charts'][0]['labels'])->toBe(['01/05', '02/05', '03/05']);
});
