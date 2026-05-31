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

it('lists identified species from bird types in the report period', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Multi Sector Multi Ave',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorMultiBird,
    ]);

    $almacen = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Almacen',
        'active' => true,
    ]);

    $deposito = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Deposito',
        'active' => true,
    ]);

    $paloma = BirdType::query()->where('slug', 'palomas')->firstOrFail();
    $cotorra = BirdType::query()->where('slug', 'cotorras')->firstOrFail();
    $tordo = BirdType::query()->where('slug', 'tordos')->firstOrFail();

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    seedCurrentSituationVisitReports(
        client: $client,
        location: $almacen,
        birdType: $paloma,
        employee: $employee,
        date: '2025-11-05',
        quantity: 87,
        observation: 'Primera visita historica paloma',
    );

    seedCurrentSituationVisitReports(
        client: $client,
        location: $deposito,
        birdType: $cotorra,
        employee: $employee,
        date: '2025-11-05',
        quantity: 22,
        observation: 'Primera visita historica cotorra',
    );

    seedCurrentSituationVisitReports(
        client: $client,
        location: $almacen,
        birdType: $tordo,
        employee: $employee,
        date: '2025-11-05',
        quantity: 9,
        observation: 'Primera visita historica tordo',
    );

    foreach ([$paloma, $cotorra, $tordo] as $birdType) {
        seedCurrentSituationVisitReports(
            client: $client,
            location: $almacen,
            birdType: $birdType,
            employee: $employee,
            date: '2026-05-10',
            quantity: 3,
            observation: 'Control '.$birdType->name,
        );
    }

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
        ->and($html)->toContain('se registró la presencia de otras especies de aves en el predio')
        ->and($html)->toContain(
            'Especies identificadas: Cotorra (Myiopsitta monachus), Paloma doméstica (Columba livia) y Tordo (Molothrus bonariensis).',
        )
        ->and($html)->toContain('<th>Tipo de ave</th>')
        ->and($html)->toContain('<th>Población Inicial</th>')
        ->and($html)->toContain('Cotorra (Myiopsitta monachus)')
        ->and($html)->toContain('Paloma doméstica (Columba livia)')
        ->and($html)->toContain('Tordo (Molothrus bonariensis)')
        ->and($html)->toMatch('/<td>\s*87\s*<\/td>/')
        ->and($html)->toMatch('/<td>\s*22\s*<\/td>/')
        ->and($html)->toMatch('/<td>\s*9\s*<\/td>/');
});
