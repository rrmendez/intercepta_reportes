<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Services\Reports\ReportCurrentSituationAndConclusionsBuilder;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('builds current situation metrics from historical baseline and period totals', function (): void {
    $client = Client::query()->create([
        'name' => 'Conaprole Planta Industrial Nº 11',
        'active' => true,
        'import_mode' => ClientImportMode::SingleSectorSingleBird,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Planta central',
        'active' => true,
    ]);

    $birdType = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $employee = Employee::query()->create([
        'name' => 'Manuel Maier',
        'active' => true,
    ]);

    $historicalReports = seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $birdType,
        employee: $employee,
        date: '2025-10-15',
        quantity: 125,
        observation: 'Relevamiento inicial',
    );

    $periodReports = seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $birdType,
        employee: $employee,
        date: '2026-03-24',
        quantity: 1,
        observation: 'Se captura 1 paloma',
    )->merge(seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $birdType,
        employee: $employee,
        date: '2026-03-30',
        quantity: 2,
        observation: 'Control realizado con normalidad',
    ));

    $result = app(ReportCurrentSituationAndConclusionsBuilder::class)->build(
        $periodReports,
        $historicalReports,
    );

    expect($result['population_entries'])->toBe([
        ['quantity' => 3, 'name' => 'Palomas domésticas', 'scientific_name' => 'Columba livia'],
    ])
        ->and($result['reduction_percentage'])->toBe('97.60')
        ->and($result['falconry_captures'])->toBe([
            ['quantity' => 1, 'name' => 'Paloma doméstica', 'scientific_name' => 'Columba livia'],
        ])
        ->and($result['trap_captures'])->toBe([
            ['quantity' => 0, 'name' => 'Palomas domésticas', 'scientific_name' => 'Columba livia'],
        ])
        ->and($result['nests_removed'])->toBe(0);
});

it('builds initial population entries from the first visit day baseline', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Demo',
        'active' => true,
        'import_mode' => ClientImportMode::SingleSectorSingleBird,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Planta central',
        'active' => true,
    ]);

    $birdType = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    $historicalReports = seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $birdType,
        employee: $employee,
        date: '2025-10-15',
        quantity: 125,
        observation: 'Relevamiento inicial',
    );

    $periodReports = seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $birdType,
        employee: $employee,
        date: '2026-03-24',
        quantity: 0,
        observation: 'Control realizado con normalidad',
    );

    $result = app(ReportCurrentSituationAndConclusionsBuilder::class)->initialPopulationEntries(
        $periodReports,
        $historicalReports,
    );

    expect($result)->toBe([
        [
            'nombre_comun' => 'Paloma doméstica',
            'descripcion' => 'Columba livia',
            'poblacion_inicial' => 125,
        ],
    ]);
});
