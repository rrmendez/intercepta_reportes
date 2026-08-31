<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\VisitSpreadsheet\VisitSpreadsheetQuantityColumns;
use App\Services\VisitSpreadsheet\VisitSpreadsheetTableRowArrays;
use App\VisitCountStatus;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('writes skipped counts as text and leaves untouched slots empty', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Planilla',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    $deposito = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Deposito',
        'active' => true,
    ]);

    $planta = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Planta',
        'active' => true,
    ]);

    $employee = Employee::query()->create(['name' => 'Operario', 'active' => true]);
    $palomas = BirdType::query()->where('name', 'Palomas')->firstOrFail();

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-10 08:00:00',
        'date_end' => '2026-05-10 09:00:00',
    ]);

    VisitReport::query()->create([
        'visit_id' => $visit->id,
        'location_id' => $deposito->id,
        'bird_type_id' => $palomas->id,
        'quantity' => null,
        'count_status' => VisitCountStatus::NotPerformed,
    ]);

    $specs = collect(app(VisitSpreadsheetQuantityColumns::class)->forClient($client));
    $depositoKey = $specs->firstWhere('location_id', (int) $deposito->id)['key'];
    $plantaKey = $specs->firstWhere('location_id', (int) $planta->id)['key'];

    $visits = Visit::query()->with(['employee', 'visitReports'])->get();
    $rows = app(VisitSpreadsheetTableRowArrays::class)->forClient($client, $visits);

    expect($rows[0][$depositoKey])->toBe('Sin conteo')
        ->and($rows[0][$plantaKey])->toBe('');
});
