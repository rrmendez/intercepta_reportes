<?php

declare(strict_types=1);

use App\Filament\RichContent\Blocks\VisitsDetailBlock;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Visit;
use App\Models\VisitReport;
use App\VisitCountStatus;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

/**
 * @param  list<int|null>  $quantities
 */
function renderVisitsDetailBlockFor(array $quantities): string
{
    $client = Client::query()->create(['name' => 'Cliente Detalle', 'active' => true]);
    $employee = Employee::query()->create(['name' => 'Tecnico', 'active' => true]);
    $palomas = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-10 09:00:00',
        'date_end' => '2026-05-10 10:00:00',
        'observation' => 'Control mensual',
    ]);

    foreach ($quantities as $index => $quantity) {
        $location = Location::query()->create([
            'client_id' => $client->id,
            'name' => 'Sector '.$index,
            'active' => true,
        ]);

        VisitReport::query()->create([
            'visit_id' => $visit->id,
            'location_id' => $location->id,
            'bird_type_id' => $palomas->id,
            'quantity' => $quantity,
            'count_status' => $quantity === null
                ? VisitCountStatus::NotPerformed
                : VisitCountStatus::Counted,
        ]);
    }

    $visits = Visit::query()->with(['employee', 'visitReports'])->get();

    return (string) VisitsDetailBlock::toHtml([], ['visits' => $visits]);
}

it('shows the plain total when every sector was counted', function (): void {
    expect(renderVisitsDetailBlockFor([7, 5]))->toContain('<td>12</td>');
});

it('flags how many sectors were skipped in a partial total', function (): void {
    expect(renderVisitsDetailBlockFor([7, null, null]))->toContain('<td>7 (2 sin conteo)</td>');
});

it('shows no count at all when every sector was skipped', function (): void {
    expect(renderVisitsDetailBlockFor([null, null]))->toContain('<td>Sin conteo</td>');
});
