<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('renders the single-bird current situation page with fixed labels and dynamic values', function (): void {
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

    seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $birdType,
        employee: $employee,
        date: '2025-10-15',
        quantity: 125,
        observation: 'Relevamiento inicial',
    );

    seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $birdType,
        employee: $employee,
        date: '2026-03-24',
        quantity: 1,
        observation: 'Se captura 1 paloma',
    );

    seedCurrentSituationVisitReports(
        client: $client,
        location: $location,
        birdType: $birdType,
        employee: $employee,
        date: '2026-03-30',
        quantity: 2,
        observation: 'Control realizado con normalidad',
    );

    $html = renderSingleSectorSingleBirdTemplateHtml($client);

    expect($html)->toContain('Situación actual y conclusiones')
        ->and($html)->toContain('Población al último día de servicio:')
        ->and($html)->toContain('3 Palomas domésticas.')
        ->and($html)->toContain('Se realizó un control biológico en la totalidad del predio')
        ->and($html)->toContain('Asimismo, se trabajó sobre los factores')
        ->and($html)->toContain('La población de aves ha disminuido en un 97.60%')
        ->and($html)->toContain('Capturas con cetrería o métodos alternativos: 0 Palomas domésticas (<em>Columba livia</em>)')
        ->and($html)->toContain('Capturas con trampas: 0 Palomas domésticas (<em>Columba livia</em>).')
        ->and($html)->toContain('Se retiraron 0 nidos.')
        ->and($html)->not->toContain('$texto_etiqueta_poblacion_ultimo_dia')
        ->and($html)->toContain('plan de trabajo implantado está siendo exitoso')
        ->and($html)->toContain('data:image/jpeg;base64,')
        ->and($html)->toContain('Manuel Maier');
});

it('renders the current situation and conclusions page with population, reduction and signature', function (): void {
    $reportCurrentSituationAndConclusions = [
        'population_entries' => [
            ['quantity' => 3, 'name' => 'Palomas domésticas'],
        ],
        'reduction_percentage' => '97.94',
        'falconry_captures' => [
            ['quantity' => 0, 'name' => 'Palomas domésticas', 'scientific_name' => 'Columba livia'],
        ],
        'trap_captures' => [
            ['quantity' => 0, 'name' => 'Palomas domésticas', 'scientific_name' => 'Columba livia'],
        ],
        'nests_removed' => 0,
    ];

    $html = view('pdf.partials.report-current-situation-and-conclusions-page', [
        'situacion_actual_y_conclusiones' => $reportCurrentSituationAndConclusions,
        'texto_conclusion' => 'En vista de los resultados alcanzados, creemos que el plan de trabajo implantado está siendo exitoso.',
    ])->render();

    expect($html)->toContain('Situación actual y conclusiones')
        ->and($html)->toContain('Población al último día de servicio')
        ->and($html)->toContain('3 Palomas domésticas.')
        ->and($html)->toContain('La población de aves ha disminuido en un 97.94%')
        ->and($html)->toContain('Capturas con cetrería o métodos alternativos')
        ->and($html)->toContain('<em>Columba livia</em>')
        ->and($html)->toContain('Capturas con trampas')
        ->and($html)->toContain('Se retiraron 0 nidos')
        ->and($html)->toContain('plan de trabajo implantado está siendo exitoso')
        ->and($html)->toContain('data:image/jpeg;base64,')
        ->and($html)->toContain('Manuel Maier');
});

it('includes the current situation and conclusions page in the single sector single bird template', function (): void {
    $source = file_get_contents(resource_path('pdf-report-templates/single_sector_single_bird.blade.php'));

    expect($source)->toBeString()
        ->and($source)->toContain('Situación actual y conclusiones')
        ->and($source)->toContain('report-current-situation-and-conclusions-page')
        ->and($source)->not->toContain("@include('pdf.partials.report-current-situation-and-conclusions-page-single-bird')")
        ->and($source)->not->toContain("@include('pdf.partials.report-current-situation-and-conclusions-page')")
        ->and($source)->not->toContain("@include('pdf.partials.report-current-situation-page')")
        ->and($source)->not->toContain("@include('pdf.partials.report-conclusions-page')")
        ->and($source)->toContain('Capturas con cetrería o métodos alternativos: 0 Palomas domésticas (<em>Columba livia</em>)')
        ->and($source)->toContain('Capturas con trampas: 0 Palomas domésticas (<em>Columba livia</em>).')
        ->and($source)->toContain('Se retiraron 0 nidos.')
        ->and($source)->not->toContain('$falconryCaptures')
        ->and($source)->not->toContain('$trapCaptures')
        ->and($source)->not->toContain('$nestsRemoved')
        ->and($source)->not->toContain('$technicianName')
        ->and($source)->toContain('Manuel Maier');
});

it('renders current situation and conclusions in the dev pdf sample preview', function (): void {
    if (! app()->isLocal()) {
        expect(true)->toBeTrue();

        return;
    }

    $this->get(route('dev.pdf-sample', [
        'template' => 'single_sector_single_bird',
    ]))
        ->assertOk()
        ->assertSee('Situación actual y conclusiones', false)
        ->assertSee('Población al último día de servicio', false)
        ->assertSee('Manuel Maier', false)
        ->assertSee('data:image/jpeg;base64,', false);
});
