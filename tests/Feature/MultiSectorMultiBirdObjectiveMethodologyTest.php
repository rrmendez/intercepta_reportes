<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Report;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportHtmlPreview;
use App\Services\ReportPdfTemplateDefaults;
use App\Services\Reports\ReportPeriodData;
use Carbon\CarbonImmutable;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('splits the visit register table when there are more than four dynamic quantity columns', function (): void {
    $html = renderMultiSectorMultiBirdTemplateWithVisitGrid(
        locationCount: 3,
        birdTypeCount: 2,
    );

    $registroHtml = objectiveMethodologyVisitRegisterHtml($html);

    expect(substr_count($registroHtml, 'report-objective-methodology-page__table-wrap'))->toBe(2)
        ->and($registroHtml)->toContain('report-objective-methodology-page--visit-table-continued')
        ->and($html)->toMatch('/\.report-objective-methodology-page--visit-table-continued[\s\S]*?page-break-before:\s*always/')
        ->and(substr_count($registroHtml, '<th>Inicio</th>'))->toBe(2)
        ->and(substr_count($registroHtml, '<th>Fin</th>'))->toBe(2)
        ->and(substr_count($registroHtml, '<th>Empleado</th>'))->toBe(2)
        ->and(substr_count($registroHtml, '<th>Observacion</th>'))->toBe(2);
});

it('includes observacion on every visit register table chunk', function (): void {
    $html = renderMultiSectorMultiBirdTemplateWithVisitGrid(
        locationCount: 3,
        birdTypeCount: 2,
    );

    $registroHtml = objectiveMethodologyVisitRegisterHtml($html);

    $continuedPos = strpos($registroHtml, 'report-objective-methodology-page--visit-table-continued');
    $firstObservacionPos = strpos($registroHtml, '<th>Observacion</th>');
    $lastObservacionPos = strrpos($registroHtml, '<th>Observacion</th>');

    expect($continuedPos)->toBeInt()->toBeGreaterThan(0)
        ->and($firstObservacionPos)->toBeInt()->toBeGreaterThan(0)
        ->and($lastObservacionPos)->toBeInt()->toBeGreaterThan($continuedPos);
});

it('shows continued visit register tables on a separate preview sheet', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Multi Sector Multi Ave Preview',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorMultiBird,
    ]);

    $locations = createActiveLocationsForClient($client, 3);
    $birdTypes = createActiveBirdTypes(2);

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-10 09:00:00',
        'date_end' => '2026-05-10 10:00:00',
        'observation' => 'Recorrido con conteos por sector y ave',
    ]);

    foreach ($locations as $location) {
        foreach ($birdTypes as $birdType) {
            VisitReport::query()->create([
                'visit_id' => $visit->id,
                'location_id' => $location->id,
                'bird_type_id' => $birdType->id,
                'quantity' => 1,
            ]);
        }
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

    $documentHtml = app(ReportBladeStringRenderer::class)->renderDocument(
        ReportPdfTemplateDefaults::bladeSourceForClient($client),
        $client,
        $report,
        $period,
    );

    $previewHtml = app(ReportHtmlPreview::class)->build(
        $documentHtml,
        $client,
        $report,
        (string) $period['period_label'],
    );

    expect($previewHtml)->toContain('report-objective-methodology-page--visit-table-continued')
        ->and(substr_count($previewHtml, 'class="report-pdf-preview-sheet"'))->toBe(8)
        ->and(substr_count($previewHtml, 'class="report-pdf-preview-sheet__header"'))->toBe(6);
});

it('keeps a single visit register table when there are at most four dynamic quantity columns', function (): void {
    $html = renderMultiSectorMultiBirdTemplateWithVisitGrid(
        locationCount: 2,
        birdTypeCount: 2,
    );

    $registroHtml = objectiveMethodologyVisitRegisterHtml($html);

    expect(substr_count($registroHtml, 'report-objective-methodology-page__table-wrap'))->toBe(1)
        ->and($registroHtml)->not->toContain('report-objective-methodology-page--visit-table-continued')
        ->and(substr_count($registroHtml, '<th>Observacion</th>'))->toBe(1);
});

function objectiveMethodologyVisitRegisterHtml(string $html): string
{
    if (! preg_match(
        '/Registro del control de fauna(.*?)<section class="report-fauna-evolution-page/s',
        $html,
        $matches,
    )) {
        return '';
    }

    return $matches[1];
}

/**
 * @return list<Location>
 */
function createActiveLocationsForClient(Client $client, int $count): array
{
    $locations = [];

    for ($index = 1; $index <= $count; $index++) {
        $locations[] = Location::query()->create([
            'client_id' => $client->id,
            'name' => "Sector {$index}",
            'active' => true,
        ]);
    }

    return $locations;
}

/**
 * @return list<BirdType>
 */
function createActiveBirdTypes(int $count): array
{
    $canonical = [
        BirdType::query()->where('slug', 'palomas')->firstOrFail(),
        BirdType::query()->where('slug', 'cotorras')->firstOrFail(),
        BirdType::query()->where('slug', 'tordos')->firstOrFail(),
    ];

    $extras = [
        ['slug' => 'gaviota', 'name' => 'Gaviota', 'common_name' => 'Gaviota', 'common_name_plural' => 'Gaviotas', 'scientific_name' => 'Larus dominicanus'],
        ['slug' => 'zorzal', 'name' => 'Zorzal', 'common_name' => 'Zorzal', 'common_name_plural' => 'Zorzales', 'scientific_name' => 'Turdus rufiventris'],
        ['slug' => 'chingolo', 'name' => 'Chingolo', 'common_name' => 'Chingolo', 'common_name_plural' => 'Chingolos', 'scientific_name' => 'Zonotrichia capensis'],
    ];

    $birdTypes = array_slice($canonical, 0, min($count, count($canonical)));

    foreach (array_slice($extras, 0, max(0, $count - count($canonical))) as $attributes) {
        $birdTypes[] = BirdType::factory()->create($attributes);
    }

    return $birdTypes;
}

function renderMultiSectorMultiBirdTemplateWithVisitGrid(int $locationCount, int $birdTypeCount): string
{
    $client = Client::query()->create([
        'name' => 'Cliente Multi Sector Multi Ave Tabla',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorMultiBird,
    ]);

    $locations = createActiveLocationsForClient($client, $locationCount);
    $birdTypes = createActiveBirdTypes($birdTypeCount);

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-10 09:00:00',
        'date_end' => '2026-05-10 10:00:00',
        'observation' => 'Recorrido con conteos por sector y ave',
    ]);

    foreach ($locations as $location) {
        foreach ($birdTypes as $birdType) {
            VisitReport::query()->create([
                'visit_id' => $visit->id,
                'location_id' => $location->id,
                'bird_type_id' => $birdType->id,
                'quantity' => 1,
            ]);
        }
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

    return app(ReportBladeStringRenderer::class)->renderDocument(
        ReportPdfTemplateDefaults::bladeSourceForClient($client),
        $client,
        $report,
        $period,
    );
}
