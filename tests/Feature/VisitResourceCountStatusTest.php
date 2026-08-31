<?php

declare(strict_types=1);

use App\Filament\Resources\Visits\Pages\CreateVisit;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReport;
use App\VisitCountStatus;
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

it('creates a visit with a count that could not be performed', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create(['name' => 'Cliente Form', 'active' => true]);
    $employee = Employee::query()->create(['name' => 'Operario Form', 'active' => true]);
    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Deposito',
        'active' => true,
    ]);
    $palomas = BirdType::query()->where('name', 'Palomas')->firstOrFail();

    Livewire::actingAs($user)
        ->test(CreateVisit::class)
        ->fillForm([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'date_init' => '2026-05-10 08:00:00',
            'date_end' => '2026-05-10 09:00:00',
            'status' => VisitStatus::Completed->value,
            'observation' => 'Local cerrado al llegar',
            'visitReports' => [
                [
                    'location_id' => $location->id,
                    'bird_type_id' => $palomas->id,
                    'count_status' => VisitCountStatus::NotPerformed->value,
                    'observation' => 'Sin acceso',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $visitReport = VisitReport::query()->firstOrFail();

    expect(Visit::query()->count())->toBe(1)
        ->and($visitReport->count_status)->toBe(VisitCountStatus::NotPerformed)
        ->and($visitReport->quantity)->toBeNull();
});
