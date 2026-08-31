<?php

use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Visit;
use App\Models\VisitReport;
use App\VisitCountStatus;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);

    $client = Client::query()->create(['name' => 'Cliente conteos', 'active' => true]);
    $employee = Employee::query()->create(['name' => 'Tecnico', 'active' => true]);

    $this->location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Sector A',
        'active' => true,
    ]);

    $this->birdType = BirdType::query()->where('name', 'Palomas')->firstOrFail();

    $this->visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-10 09:00:00',
        'date_end' => '2026-05-10 10:00:00',
    ]);
});

function makeVisitReport(array $attributes = []): VisitReport
{
    return VisitReport::query()->create([
        'visit_id' => test()->visit->id,
        'location_id' => test()->location->id,
        'bird_type_id' => test()->birdType->id,
        ...$attributes,
    ]);
}

it('keeps a real zero count apart from a count that could not be performed', function (): void {
    $zero = makeVisitReport(['quantity' => 0]);
    $skipped = makeVisitReport(['quantity' => null, 'count_status' => VisitCountStatus::NotPerformed]);

    expect($zero->count_status)->toBe(VisitCountStatus::Counted)
        ->and($zero->wasCounted())->toBeTrue()
        ->and($skipped->quantity)->toBeNull()
        ->and($skipped->wasCounted())->toBeFalse();
});

it('nulls the quantity when a count is marked as not performed', function (): void {
    $report = makeVisitReport(['quantity' => 12]);

    $report->count_status = VisitCountStatus::NotPerformed;
    $report->save();

    expect($report->refresh()->quantity)->toBeNull();
});

it('reopens a skipped count when a quantity is written again', function (): void {
    $report = makeVisitReport(['quantity' => null, 'count_status' => VisitCountStatus::NotPerformed]);

    $report->quantity = 4;
    $report->save();

    expect($report->refresh()->count_status)->toBe(VisitCountStatus::Counted)
        ->and($report->quantity)->toBe(4);
});

it('marks a report without quantity as not performed', function (): void {
    expect(makeVisitReport()->count_status)->toBe(VisitCountStatus::NotPerformed);
});

it('scopes counted and not performed reports', function (): void {
    makeVisitReport(['quantity' => 3]);
    makeVisitReport(['quantity' => null, 'count_status' => VisitCountStatus::NotPerformed]);

    expect(VisitReport::query()->counted()->count())->toBe(1)
        ->and(VisitReport::query()->notPerformed()->count())->toBe(1);
});
