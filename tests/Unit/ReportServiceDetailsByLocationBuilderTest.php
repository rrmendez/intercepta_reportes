<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\Reports\ReportServiceDetailsByLocationBuilder;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('uses the location name as the table title', function (): void {
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

    $visitReports = seedVisitReports($client, $location, $birdType, quantity: 2);

    $result = app(ReportServiceDetailsByLocationBuilder::class)->build($client, $visitReports);

    expect($result['sections'])->toHaveCount(1)
        ->and($result['sections'][0]['title'])->toBe('Planta central');
});

it('uses each location name as the table title when there are multiple sections', function (): void {
    $client = Client::query()->create([
        'name' => 'Empresa Multi Zona',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    $locationA = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Planta central',
        'active' => true,
    ]);

    $locationB = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Depósito norte',
        'active' => true,
    ]);

    $birdType = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $visitReports = seedVisitReports($client, $locationA, $birdType, quantity: 1)
        ->merge(seedVisitReports($client, $locationB, $birdType, quantity: 4));

    $result = app(ReportServiceDetailsByLocationBuilder::class)->build($client, $visitReports);

    expect($result['sections'])->toHaveCount(2)
        ->and(collect($result['sections'])->pluck('title')->all())->toBe([
            'Depósito norte',
            'Planta central',
        ]);
});

it('uses quantities from the last visit day for abundance with name and description', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Demo',
        'active' => true,
        'import_mode' => ClientImportMode::SingleSectorMultiBird,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Planta central',
        'active' => true,
    ]);

    $palomas = BirdType::query()->where('slug', 'palomas')->firstOrFail();
    $cotorras = BirdType::query()->where('slug', 'cotorras')->firstOrFail();

    $visitReports = seedVisitReports($client, $location, $palomas, quantity: 3)
        ->merge(seedVisitReports($client, $location, $cotorras, quantity: 2));

    $result = app(ReportServiceDetailsByLocationBuilder::class)->build($client, $visitReports);

    $abundance = $result['sections'][0]['abundancia'];

    expect($abundance)->toBe("3 Palomas domésticas (Columba livia)\n2 Cotorras (Myiopsitta monachus)");
});

it('uses only the last visit day when calculating abundance', function (): void {
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

    $visitReports = seedVisitReports($client, $location, $birdType, quantity: 10, date: '2026-03-10')
        ->merge(seedVisitReports($client, $location, $birdType, quantity: 3, date: '2026-03-24'));

    $result = app(ReportServiceDetailsByLocationBuilder::class)->build($client, $visitReports);

    expect($result['sections'][0]['abundancia'])->toBe('3 Palomas domésticas (Columba livia)');
});

it('counts captures and removed nests from visit observations', function (): void {
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
        'name' => 'Técnico',
        'active' => true,
    ]);

    $captureVisit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-03-24 17:00:00',
        'date_end' => '2026-03-24 18:00:00',
        'observation' => 'Se captura 1 paloma',
    ]);

    $nestVisit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-03-25 16:00:00',
        'date_end' => '2026-03-25 17:00:00',
        'observation' => 'Se retira 2 nidos',
    ]);

    $visitReports = VisitReport::query()
        ->whereIn('id', [
            VisitReport::query()->create([
                'visit_id' => $captureVisit->id,
                'location_id' => $location->id,
                'bird_type_id' => $birdType->id,
                'quantity' => 2,
            ])->id,
            VisitReport::query()->create([
                'visit_id' => $nestVisit->id,
                'location_id' => $location->id,
                'bird_type_id' => $birdType->id,
                'quantity' => 0,
            ])->id,
        ])
        ->with(['visit', 'location', 'birdType'])
        ->get();

    $result = app(ReportServiceDetailsByLocationBuilder::class)->build($client, $visitReports);
    $section = $result['sections'][0];

    expect($section['capturas'])->toBe(1)
        ->and($section['nidos_retirados'])->toBe(2);
});

/**
 * @return Collection<int, VisitReport>
 */
function seedVisitReports(
    Client $client,
    Location $location,
    BirdType $birdType,
    int $quantity,
    string $date = '2026-03-10',
): Collection {
    $employee = Employee::query()->firstOrCreate(
        ['name' => 'Técnico Demo'],
        ['active' => true],
    );

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => "{$date} 16:30:00",
        'date_end' => "{$date} 17:30:00",
        'observation' => 'Control realizado con normalidad',
    ]);

    return VisitReport::query()
        ->whereKey(VisitReport::query()->create([
            'visit_id' => $visit->id,
            'location_id' => $location->id,
            'bird_type_id' => $birdType->id,
            'quantity' => $quantity,
        ])->id)
        ->with(['visit', 'location', 'birdType'])
        ->get();
}
