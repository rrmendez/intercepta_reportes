<?php

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Report;
use App\ReportStatus;
use App\Services\ReportBladeStringRenderer;
use App\Services\Reports\ReportPeriodData;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('renders the report cover partial with title, brand and tagline', function (): void {
    $client = Client::query()->create([
        'name' => 'Conaprole Planta Industrial Nº 11 – San José Rincón del Pino',
        'active' => true,
    ]);

    $report = Report::make([
        'id' => 58,
        'client_id' => $client->id,
    ])->setRelation('client', $client);

    $html = view('pdf.partials.report-cover-page', [
        'client' => $client,
        'period_label' => 'Enero 2026',
        'report' => $report,
    ])->render();

    expect($html)->toContain('Informe del servicio de control de fauna')
        ->and($html)->toContain('header_reporte.png')
        ->and($html)->toContain('report-cover__header-strip')
        ->and($html)->toContain('min-height: 257mm')
        ->and($html)->toContain('calc(297mm / 3)')
        ->and($html)->toContain('font-size: 42pt')
        ->and($html)->toContain('z-index: 2')
        ->and($html)->toContain('hyphens: none')
        ->and($html)->toContain('word-break: normal')
        ->and($html)->not->toContain('hyphens: auto')
        ->and($html)->not->toContain('overflow-wrap: break-word')
        ->and($html)->toContain('intercepta-logo.svg')
        ->and($html)->toContain('report-cover__brand-inner')
        ->and($html)->toContain('height: 24mm')
        ->and($html)->toContain('CONTROL BIOLÓGICO DE FAUNA')
        ->and($html)->not->toContain('id="report-pdf-fixed-footer-root"');
});

it('renders the default normal page header with Intercepta logo and gold line', function (): void {
    $html = view('pdf.partials.report-pdf-default-header')->render();

    expect($html)->toContain('id="report-pdf-default-header-root"')
        ->and($html)->toContain('id="report-pdf-default-header-styles"')
        ->and($html)->toContain('report-pdf-default-header__logo')
        ->and($html)->toContain('data:image/svg+xml;base64,')
        ->and($html)->toContain('report-pdf-default-header__line')
        ->and($html)->toContain('border-top: 2px solid #d4a012')
        ->and($html)->toContain('height: 17mm')
        ->and($html)->toContain('position: fixed')
        ->and($html)->toContain('z-index: 1');
});

it('renders the initial situation page with title, text and species table', function (): void {
    $html = view('pdf.partials.report-initial-situation-page')->render();

    expect($html)->toContain('Situación inicial del predio')
        ->and($html)->toContain('font-size: 24pt')
        ->and($html)->toContain('font-family: Arial, Helvetica, sans-serif')
        ->and($html)->toContain('rgb(232, 177, 76)')
        ->and($html)->toContain('paloma doméstica')
        ->and($html)->toContain('Columba livia')
        ->and($html)->toContain('Especies identificadas')
        ->and($html)->toContain('report-initial-situation-page__table')
        ->and($html)->toContain('Población significativa');
});

it('renders the single-bird initial situation page with two-column population table', function (): void {
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
        observation: 'Control realizado',
    );

    $html = renderSingleSectorSingleBirdTemplateHtml($client);

    expect($html)->toContain('Tipo de ave')
        ->and($html)->toContain('Población Inicial')
        ->and($html)->toContain('Paloma doméstica (Columba livia)')
        ->and($html)->toContain('125')
        ->and($html)->not->toContain('Situación observada')
        ->and($html)->not->toContain('Nombre científico');
});

it('renders the single-bird objective methodology page with fixed objective text', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Demo',
        'active' => true,
        'import_mode' => ClientImportMode::SingleSectorSingleBird,
    ]);

    $html = renderSingleSectorSingleBirdTemplateHtml($client);

    expect($html)->toContain('El principal objetivo es disminuir la población inicial entre un 80% a un 90%')
        ->and($html)->toContain('En una gran cantidad de casos se logra un control del 100%')
        ->and($html)->toContain('La metodología a usar es la cetrería')
        ->and($html)->not->toContain('$texto_objetivo')
        ->and($html)->not->toContain('$texto_metodologia');
});

it('renders the objective methodology page with visit rows table', function (): void {
    $html = view('pdf.partials.report-objective-methodology-page', [
        'visit_columns' => [
            ['key' => 'visit_date_init', 'label' => 'Inicio'],
            ['key' => 'employee.name', 'label' => 'Empleado'],
            ['key' => 'qty_1_1', 'label' => 'conteo'],
            ['key' => 'visit_observation', 'label' => 'Observacion'],
        ],
        'visits' => [
            [
                'visit_date_init' => '01/05/2026 08:00',
                'visit_date_end' => '01/05/2026 09:00',
                'employee.name' => 'Funcionario',
                'qty_1_1' => '12',
                'visit_observation' => 'Sin novedades',
            ],
        ],
    ])->render();

    expect($html)->toContain('Objetivo y metodología')
        ->and($html)->toContain('font-size: 24pt')
        ->and($html)->toContain('Registro del control de fauna')
        ->and($html)->toContain('report-objective-methodology-page__table')
        ->and($html)->toContain('Inicio')
        ->and($html)->toContain('Funcionario')
        ->and($html)->toContain('<th>Conteo</th>')
        ->and($html)->not->toContain('<th>conteo</th>')
        ->and($html)->toContain('12')
        ->and($html)->toContain('Sin novedades')
        ->and($html)->not->toContain('<th>Fin</th>')
        ->and($html)->toContain('rgb(232, 177, 76)');
});

it('renders the fixed pdf footer partial with meta and logos', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Pie',
        'address' => 'Bulevar Artigas 1234, Montevideo',
        'active' => true,
    ]);

    $report = Report::query()->create([
        'client_id' => $client->id,
        'month' => 1,
        'year' => 2026,
        'date_from' => '2026-01-01',
        'date_until' => '2026-01-31',
        'status' => ReportStatus::Draft,
    ]);

    $html = view('pdf.partials.report-pdf-fixed-footer', [
        'client' => $client,
        'report' => $report,
        'period_label' => 'enero 2026',
    ])->render();

    expect($html)->toContain('id="report-pdf-fixed-footer-root"')
        ->and($html)->toContain('id="report-pdf-fixed-footer-styles"')
        ->and($html)->toContain('@media print')
        ->and(substr_count($html, 'flex-wrap: nowrap'))->toBe(2)
        ->and(substr_count($html, 'flex: 1 1 0'))->toBe(3)
        ->and($html)->toContain('overflow-wrap: normal')
        ->and($html)->toContain('word-break: normal')
        ->and($html)->not->toContain('word-break: break-word')
        ->and($html)->toContain('report-pdf-fixed-footer__client-name')
        ->and($html)->toContain('report-pdf-fixed-footer__client-address')
        ->and($html)->toContain('Cliente Pie')
        ->and($html)->toContain('Bulevar Artigas 1234, Montevideo')
        ->and($html)->toContain('enero 2026')
        ->and($html)->toContain('Informe Nº')
        ->and($html)->toContain((string) $report->id)
        ->and($html)->toContain('auc.svg')
        ->and($html)->toContain('#cfd4de')
        ->and($html)->toContain('border: none !important')
        ->and($html)->toContain('border-top: 1px solid #b8c0cc !important')
        ->and($html)->toContain('--report-pdf-outer-margin: 12mm')
        ->and($html)->toContain('bottom: 0 !important')
        ->and($html)->toContain('--report-pdf-footer-logo-max-width: 58mm')
        ->and($html)->toContain('report-pdf-fixed-footer__footer-logo')
        ->and($html)->toContain('birdlife.png')
        ->and($html)->toContain('BirdLife International');

    $aucSvg = (string) file_get_contents((string) public_path('images/auc.svg'));
    expect($aucSvg)->toContain('fill="#374151"')
        ->and($aucSvg)->not->toContain('2f54838b-86a8-4eaf-b3fe-6825def3f351');
});

it('renders fixed footer when the shipped pdf template includes the footer partial', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Renderer',
        'active' => true,
    ]);

    $report = Report::query()->create([
        'client_id' => $client->id,
        'month' => 3,
        'year' => 2026,
        'date_from' => '2026-03-01',
        'date_until' => '2026-03-31',
        'status' => ReportStatus::Draft,
    ]);

    $period = app(ReportPeriodData::class)->load($client, '2026-03-01', '2026-03-31', $report);

    $blade = (string) file_get_contents(resource_path('pdf-report-templates/default.blade.php'));

    $html = app(ReportBladeStringRenderer::class)->renderDocument($blade, $client, $report, $period);

    expect(substr_count($html, 'id="report-pdf-fixed-footer-root"'))->toBe(1)
        ->and($html)->toContain('Cliente Renderer');
});

it('includes the cover and footer partials in shipped default pdf templates', function (): void {
    foreach ([
        'default',
    ] as $slug) {
        $path = resource_path("pdf-report-templates/{$slug}.blade.php");
        $src = (string) file_get_contents($path);
        expect($src)->toContain("@include('pdf.partials.report-cover-page')")
            ->and($src)->toContain('pdf.partials.report-initial-situation-page')
            ->and($src)->toContain('pdf.partials.report-objective-methodology-page')
            ->and($src)->toContain('pdf.partials.report-contact-page')
            ->and($src)->not->toContain('pdf.partials.report-pdf-blank-pages')
            ->and($src)->toContain('pdf.partials.report-pdf-fixed-footer');
    }

    $singleBirdTemplate = (string) file_get_contents(resource_path('pdf-report-templates/single_sector_single_bird.blade.php'));

    expect($singleBirdTemplate)->toContain('Situación inicial del predio')
        ->and($singleBirdTemplate)->toContain('Objetivo y metodología')
        ->and($singleBirdTemplate)->toContain('Informe del servicio de control de fauna')
        ->and($singleBirdTemplate)->toContain('id="report-pdf-fixed-footer-root"')
        ->and($singleBirdTemplate)->toContain('report-initial-situation-page')
        ->and($singleBirdTemplate)->toContain('report-objective-methodology-page')
        ->and($singleBirdTemplate)->not->toContain("@include('pdf.partials.report-cover-page')")
        ->and($singleBirdTemplate)->not->toContain("@include('pdf.partials.report-pdf-fixed-footer')")
        ->and($singleBirdTemplate)->not->toContain("@include('pdf.partials.report-initial-situation-page-single-bird')")
        ->and($singleBirdTemplate)->not->toContain("@include('pdf.partials.report-objective-methodology-page-single-bird')")
        ->and($singleBirdTemplate)->not->toContain("@include('pdf.partials.report-service-details-by-location-page-single-sector')")
        ->and($singleBirdTemplate)->not->toContain("@include('pdf.partials.report-current-situation-and-conclusions-page-single-bird')")
        ->and($singleBirdTemplate)->not->toContain("@include('pdf.partials.report-initial-situation-page')")
        ->and($singleBirdTemplate)->not->toContain("@include('pdf.partials.report-objective-methodology-page')");

    $multiBirdTemplate = (string) file_get_contents(resource_path('pdf-report-templates/single_sector_multi_bird.blade.php'));

    expect($multiBirdTemplate)->toContain('se registró la presencia de otras especies de aves en el predio')
        ->and($multiBirdTemplate)->toContain('$especies_identificadas_linea')
        ->and($multiBirdTemplate)->toContain('Situación inicial del predio')
        ->and($multiBirdTemplate)->toContain('Objetivo y metodología')
        ->and($multiBirdTemplate)->toContain('Informe del servicio de control de fauna')
        ->and($multiBirdTemplate)->toContain('id="report-pdf-fixed-footer-root"')
        ->and($multiBirdTemplate)->toContain("@include('pdf.partials.single-sector-multi-bird-head')")
        ->and($multiBirdTemplate)->not->toContain("@include('pdf.partials.report-cover-page')")
        ->and($multiBirdTemplate)->not->toContain("@include('pdf.partials.report-pdf-fixed-footer')")
        ->and($multiBirdTemplate)->not->toContain("@include('pdf.partials.single-sector-single-bird-head')");

    $multiSectorMultiBirdTemplate = (string) file_get_contents(resource_path('pdf-report-templates/multi_sector_multi_bird.blade.php'));

    expect($multiSectorMultiBirdTemplate)->toContain('se registró la presencia de otras especies de aves en el predio')
        ->and($multiSectorMultiBirdTemplate)->toContain('$especies_identificadas_linea')
        ->and($multiSectorMultiBirdTemplate)->toContain('$tablaPoblacionInicialPorAve')
        ->and($multiSectorMultiBirdTemplate)->toContain('Situación inicial del predio')
        ->and($multiSectorMultiBirdTemplate)->toContain('Objetivo y metodología')
        ->and($multiSectorMultiBirdTemplate)->toContain('Informe del servicio de control de fauna')
        ->and($multiSectorMultiBirdTemplate)->toContain('id="report-pdf-fixed-footer-root"')
        ->and($multiSectorMultiBirdTemplate)->toContain('report-initial-situation-page')
        ->and($multiSectorMultiBirdTemplate)->toContain("@include('pdf.partials.multi-sector-multi-bird-head')")
        ->and($multiSectorMultiBirdTemplate)->not->toContain("@include('pdf.partials.report-cover-page')")
        ->and($multiSectorMultiBirdTemplate)->not->toContain("@include('pdf.partials.report-pdf-fixed-footer')")
        ->and($multiSectorMultiBirdTemplate)->not->toContain("@include('pdf.partials.single-sector-single-bird-head')");
});

it('renders five blank letter pages for pdf tail placeholders', function (): void {
    $html = view('pdf.partials.report-pdf-blank-pages')->render();

    expect(substr_count($html, 'min-height: 297mm'))->toBe(1)
        ->and(substr_count($html, 'class="report-pdf-blank-page'))->toBe(5)
        ->and($html)->toContain('report-pdf-blank-page--last');
});
