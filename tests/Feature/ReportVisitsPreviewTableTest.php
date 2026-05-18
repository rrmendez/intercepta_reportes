<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Livewire\ReportVisitsPreviewTable;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\VisitSpreadsheet\VisitSpreadsheetQuantityColumns;
use App\VisitStatus;
use Database\Seeders\BirdTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(BirdTypeSeeder::class);
});

it('lists visits for the client and date range using the shared spreadsheet table', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $palomas = BirdType::query()->where('name', 'Palomas')->firstOrFail();

    $client = Client::query()->create([
        'name' => 'Cliente Modal',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Almacen',
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Operario Modal',
        'active' => true,
    ]);

    $inRange = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-10 08:00:00',
        'date_end' => null,
        'observation' => 'Visita en rango',
        'status' => VisitStatus::Completed,
    ]);

    $location = Location::query()->where('client_id', $client->id)->where('name', 'Almacen')->firstOrFail();

    VisitReport::query()->create([
        'visit_id' => $inRange->id,
        'location_id' => $location->id,
        'bird_type_id' => $palomas->id,
        'quantity' => 2,
    ]);

    $outOfRange = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-06-10 08:00:00',
        'date_end' => null,
        'observation' => 'Fuera de rango',
        'status' => VisitStatus::Completed,
    ]);

    Livewire::actingAs($user)
        ->test(ReportVisitsPreviewTable::class, [
            'clientId' => $client->id,
            'dateFrom' => '2026-05-01',
            'dateUntil' => '2026-05-31',
        ])
        ->assertSee('Periodo')
        ->assertCanSeeTableRecords([$inRange])
        ->assertCanNotSeeTableRecords([$outOfRange])
        ->assertSee('Visita en rango');
});

it('updates a quantity cell like the spreadsheet view', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $palomas = BirdType::query()->where('name', 'Palomas')->firstOrFail();

    $client = Client::query()->create([
        'name' => 'Cliente Modal Editable',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Bodega',
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Operario Editable',
        'active' => true,
    ]);

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-10 08:00:00',
        'date_end' => null,
        'observation' => null,
        'status' => VisitStatus::Completed,
    ]);

    $location = Location::query()->where('client_id', $client->id)->where('name', 'Bodega')->firstOrFail();

    $visitReport = VisitReport::query()->create([
        'visit_id' => $visit->id,
        'location_id' => $location->id,
        'bird_type_id' => $palomas->id,
        'quantity' => 4,
    ]);

    $quantityColumnKey = collect(app(VisitSpreadsheetQuantityColumns::class)->forClient($client))
        ->firstWhere(fn (array $spec): bool => (int) $spec['location_id'] === (int) $location->id
            && (int) $spec['bird_type_id'] === (int) $palomas->id)['key'] ?? null;

    expect($quantityColumnKey)->not->toBeNull();

    Livewire::actingAs($user)
        ->test(ReportVisitsPreviewTable::class, [
            'clientId' => $client->id,
            'dateFrom' => '2026-05-01',
            'dateUntil' => '2026-05-31',
        ])
        ->call('updateTableColumnState', $quantityColumnKey, (string) $visit->getKey(), '9');

    expect((int) $visitReport->fresh()->quantity)->toBe(9);
});
