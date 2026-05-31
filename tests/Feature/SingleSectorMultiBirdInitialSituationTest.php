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

it('renders static initial situation copy and lists identified species from bird types', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Sector Unico Multi Ave',
        'active' => true,
        'import_mode' => ClientImportMode::SingleSectorMultiBird,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Predio',
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
        location: $location,
        birdType: $paloma,
        employee: $employee,
        date: '2025-11-05',
        quantity: 87,
        observation: 'Primera visita historica paloma',
    );

    seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $cotorra,
        employee: $employee,
        date: '2025-11-05',
        quantity: 22,
        observation: 'Primera visita historica cotorra',
    );

    seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $tordo,
        employee: $employee,
        date: '2025-11-05',
        quantity: 9,
        observation: 'Primera visita historica tordo',
    );

    foreach ([$paloma, $cotorra, $tordo] as $birdType) {
        seedCurrentSituationVisitReports(
            client: $client,
            location: $location,
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
        ->and($html)->toContain('Cotorra (<em>Myiopsitta monachus</em>)')
        ->and($html)->toContain('Paloma doméstica (<em>Columba livia</em>)')
        ->and($html)->toContain('Tordo (<em>Molothrus bonariensis</em>)')
        ->and($html)->toMatch('/<td>\s*87\s*<\/td>/')
        ->and($html)->toMatch('/<td>\s*22\s*<\/td>/')
        ->and($html)->toMatch('/<td>\s*9\s*<\/td>/');
});

it('renders current situation with population by bird type and reduction percentage', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Sector Unico Multi Ave',
        'active' => true,
        'import_mode' => ClientImportMode::SingleSectorMultiBird,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Predio',
        'active' => true,
    ]);

    $paloma = BirdType::query()->where('slug', 'palomas')->firstOrFail();
    $cotorra = BirdType::query()->where('slug', 'cotorras')->firstOrFail();
    $tordo = BirdType::query()->where('slug', 'tordos')->firstOrFail();

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    foreach ([$paloma, $cotorra, $tordo] as $birdType) {
        seedCurrentSituationVisitReports(
            client: $client,
            location: $location,
            birdType: $birdType,
            employee: $employee,
            date: '2025-11-05',
            quantity: 40,
            observation: 'Primera visita historica',
        );
    }

    seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $paloma,
        employee: $employee,
        date: '2026-05-10',
        quantity: 3,
        observation: 'Control paloma',
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

    expect($html)->toContain('Situación actual y conclusiones')
        ->and($html)->toContain('Población al último día de servicio:')
        ->and($html)->toContain('0 Cotorras (<em>Myiopsitta monachus</em>), 3 Palomas domésticas (<em>Columba livia</em>) y 0 Tordos (<em>Molothrus bonariensis</em>).')
        ->and($html)->toContain('La población de aves ha disminuido en un 97.50%')
        ->and($html)->toContain('Capturas con cetrería o métodos alternativos: 0 Palomas domésticas (<em>Columba livia</em>)')
        ->and($html)->toContain('Capturas con trampas: 0 Palomas domésticas (<em>Columba livia</em>).')
        ->and($html)->toContain('Se retiraron 0 nidos.')
        ->and($html)->toContain('plan de trabajo implantado está siendo exitoso');
});
