<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Filament\Resources\Visits\Pages\ListVisitsSpreadsheet;
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

it('lists visits for client and month and updates a quantity cell', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $palomas = BirdType::query()->where('name', 'Palomas')->firstOrFail();

    $client = Client::query()->create([
        'name' => 'Cliente Hoja',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Deposito',
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Operario Hoja',
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

    $location = Location::query()->where('client_id', $client->id)->where('name', 'Deposito')->firstOrFail();

    VisitReport::query()->create([
        'visit_id' => $visit->id,
        'location_id' => $location->id,
        'bird_type_id' => $palomas->id,
        'quantity' => 5,
    ]);

    $visitReport = VisitReport::query()
        ->where('visit_id', $visit->id)
        ->where('location_id', $location->id)
        ->where('bird_type_id', $palomas->id)
        ->firstOrFail();

    $quantityColumnKey = collect(app(VisitSpreadsheetQuantityColumns::class)->forClient($client))
        ->firstWhere(fn (array $spec): bool => (int) $spec['location_id'] === (int) $location->id
            && (int) $spec['bird_type_id'] === (int) $palomas->id)['key'] ?? null;

    expect($quantityColumnKey)->not->toBeNull();

    Livewire::actingAs($user)
        ->test(ListVisitsSpreadsheet::class)
        ->set('tableFilters', [
            'spreadsheet' => [
                'client_id' => (string) $client->id,
                'mode' => 'custom_range',
                'date_from' => '2026-05-01',
                'date_until' => '2026-05-31',
            ],
        ])
        ->assertCanSeeTableRecords([$visit])
        ->call('updateTableColumnState', $quantityColumnKey, (string) $visit->getKey(), '12');

    $visitReport = $visitReport->fresh();

    expect($visitReport)->not->toBeNull()
        ->and((int) $visitReport->quantity)->toBe(12);
});

it('builds quantity columns from deferred filter client when tableFilters is still empty', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $palomas = BirdType::query()->where('name', 'Palomas')->firstOrFail();

    $client = Client::query()->create([
        'name' => 'Cliente Diferido',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Silo Norte',
        'active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(ListVisitsSpreadsheet::class)
        ->set('tableDeferredFilters', [
            'spreadsheet' => [
                'client_id' => (string) $client->id,
                'mode' => 'custom_range',
                'date_from' => '2026-05-01',
                'date_until' => '2026-05-31',
            ],
        ])
        ->set('tableFilters', null)
        ->assertSee('Silo Norte');
});
