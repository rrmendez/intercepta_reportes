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

it('renders the single-sector service details page with fixed labels and dynamic values', function (): void {
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
        date: '2026-03-30',
        quantity: 3,
        observation: 'Control realizado con normalidad',
    );

    $html = renderSingleSectorSingleBirdTemplateHtml($client);

    expect($html)->toContain('Detalles del servicio por lugar de control')
        ->and($html)->toContain('Conaprole Planta Industrial Nº 11')
        ->and($html)->not->toContain('Planta central')
        ->and($html)->toContain('Método')
        ->and($html)->toContain('Cetrería y Trampa de captura viva.')
        ->and($html)->toContain('Gavilán Mixto (Parabuteo unicinctus)')
        ->and($html)->toContain('Abundancia al último día de servicio')
        ->and($html)->toContain('3 Palomas domésticas (Columba livia)')
        ->and($html)->not->toContain('$capturas_sector')
        ->and($html)->not->toContain('@foreach');
});

it('renders captures and removed nests from visit observations', function (ClientImportMode $importMode): void {
    $client = Client::query()->create([
        'name' => 'Ducsa',
        'active' => true,
        'import_mode' => $importMode,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Ducsa',
        'active' => true,
    ]);

    $birdType = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    seedCurrentSituationVisitReports($client, $location, $birdType, $employee, '2026-03-11', 2, "Se captura una paloma\nCon cetreria");
    seedCurrentSituationVisitReports($client, $location, $birdType, $employee, '2026-03-17', 0, "Se captura una paloma\nSe retira un nido");
    seedCurrentSituationVisitReports($client, $location, $birdType, $employee, '2026-03-30', 1, 'Se captura 1 paloma');

    $html = renderSingleSectorSingleBirdTemplateHtml($client);

    expect($html)->toMatch('/<td>Capturas<\/td>\s*<td>3<\/td>/')
        ->and($html)->toMatch('/<td>Nidos retirados<\/td>\s*<td>1<\/td>/')
        ->and($html)->toMatch('/Capturas con cetrería o métodos alternativos:\s*3 Palomas domésticas/')
        ->and($html)->toContain('Se retiraron 1 nidos.');
})->with([
    'single sector single bird' => ClientImportMode::SingleSectorSingleBird,
    'single sector multi bird' => ClientImportMode::SingleSectorMultiBird,
    'multi sector single bird' => ClientImportMode::MultiSectorSingleBird,
    'multi sector multi bird' => ClientImportMode::MultiSectorMultiBird,
]);

it('includes the single-sector service details page in the single sector single bird template', function (): void {
    $source = file_get_contents(resource_path('pdf-report-templates/single_sector_single_bird.blade.php'));

    expect($source)->toBeString()
        ->and($source)->toContain('Detalles del servicio por lugar de control')
        ->and($source)->toContain('report-service-details-page')
        ->and($source)->not->toContain("@include('pdf.partials.report-service-details-by-location-page-single-sector')")
        ->and($source)->not->toContain("@include('pdf.partials.report-service-details-by-location-page')");
});

it('renders the service details page with one table per section', function (): void {
    $reportServiceDetailsByLocation = [
        'sections' => [
            [
                'location_id' => 1,
                'title' => 'Conaprole Planta Industrial Nº 11',
                'capturas' => 1,
                'nidos_retirados' => 0,
                'abundancia' => '5 Palomas doméstica (Columba livia)',
            ],
        ],
    ];

    $html = view('pdf.partials.report-service-details-by-location-page', [
        'report_service_details_by_location' => $reportServiceDetailsByLocation,
    ])->render();

    expect($html)->toContain('Detalles del servicio por lugar de control')
        ->and($html)->toContain('Conaprole Planta Industrial Nº 11')
        ->and($html)->toContain('Abundancia al último día de servicio')
        ->and($html)->toContain('5 Palomas doméstica (Columba livia)')
        ->and($html)->toContain('Gavilán Mixto (Parabuteo unicinctus)');
});

it('renders service details in the dev pdf sample preview', function (): void {
    if (! app()->isLocal()) {
        expect(true)->toBeTrue();

        return;
    }

    $this->get(route('dev.pdf-sample', [
        'template' => 'single_sector_single_bird',
    ]))
        ->assertOk()
        ->assertSee('Detalles del servicio por lugar de control', false)
        ->assertSee('Conaprole Planta Industrial Nº 11', false)
        ->assertSee('Palomas doméstica', false);
});
