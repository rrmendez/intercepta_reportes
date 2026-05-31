<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Report;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportPdfTemplateDefaults;
use App\Services\Reports\ReportPeriodData;
use Carbon\CarbonImmutable;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('shows the first historical visit quantity in the initial situation table', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Multi Sector',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Almacen',
        'active' => true,
    ]);

    $birdType = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $birdType,
        employee: $employee,
        date: '2025-11-05',
        quantity: 87,
        observation: 'Primera visita historica',
    );

    seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $birdType,
        employee: $employee,
        date: '2026-05-12',
        quantity: 4,
        observation: 'Visita del periodo',
    );

    $report = Report::make([
        'client_id' => $client->id,
        'date_from' => '2026-05-01',
        'date_until' => '2026-05-31',
        'generated_at' => now(),
    ])->setRelation('client', $client);

    $period = app(ReportPeriodData::class)->load(
        $client,
        CarbonImmutable::parse('2026-05-01'),
        CarbonImmutable::parse('2026-05-31'),
        null,
    );

    $html = app(ReportBladeStringRenderer::class)->renderDocument(
        ReportPdfTemplateDefaults::bladeSourceForClient($client),
        $client,
        $report,
        $period,
    );

    expect($html)->toContain('Situación inicial del predio')
        ->and($html)->toContain('<th>Cantidad</th>')
        ->and($html)->not->toContain('<th>Población Inicial</th>')
        ->and($html)->toContain('Paloma doméstica (<em>Columba livia</em>)')
        ->and($html)->toMatch('/<td>\s*87\s*<\/td>/');
});

it('renders one service details table per sector with last visit abundance', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Multi Sector',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    $locationA = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Almacen',
        'active' => true,
    ]);

    $locationB = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Planta',
        'active' => true,
    ]);

    $birdType = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    seedCurrentSituationVisitReports(
        client: $client,
        location: $locationA,
        birdType: $birdType,
        employee: $employee,
        date: '2026-05-10',
        quantity: 12,
        observation: 'Control almacen',
    );

    seedCurrentSituationVisitReports(
        client: $client,
        location: $locationA,
        birdType: $birdType,
        employee: $employee,
        date: '2026-05-20',
        quantity: 5,
        observation: 'Ultima visita almacen',
    );

    seedCurrentSituationVisitReports(
        client: $client,
        location: $locationB,
        birdType: $birdType,
        employee: $employee,
        date: '2026-05-15',
        quantity: 8,
        observation: 'Control planta',
    );

    $report = Report::make([
        'client_id' => $client->id,
        'date_from' => '2026-05-01',
        'date_until' => '2026-05-31',
        'generated_at' => now(),
    ])->setRelation('client', $client);

    $period = app(ReportPeriodData::class)->load(
        $client,
        CarbonImmutable::parse('2026-05-01'),
        CarbonImmutable::parse('2026-05-31'),
        null,
    );

    $html = app(ReportBladeStringRenderer::class)->renderDocument(
        ReportPdfTemplateDefaults::bladeSourceForClient($client),
        $client,
        $report,
        $period,
    );

    expect($html)->toContain('Detalles del servicio por lugar de control')
        ->and(substr_count($html, '<th colspan="2">Almacen</th>'))->toBe(1)
        ->and(substr_count($html, '<th colspan="2">Planta</th>'))->toBe(1)
        ->and($html)->toContain('5 Palomas domésticas (Columba livia)')
        ->and($html)->toContain('8 Palomas domésticas (Columba livia)');
});
