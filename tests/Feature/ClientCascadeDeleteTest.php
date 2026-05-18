<?php

use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Report;
use App\Models\Section;
use App\Models\Template;
use App\Models\Visit;
use App\Models\VisitImport;
use App\Models\VisitReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes all records owned by a client', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Cascada',
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Operario',
        'active' => true,
    ]);

    $birdType = BirdType::query()->create([
        'name' => 'Palomas',
        'active' => true,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Deposito',
        'active' => true,
    ]);

    $template = Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla mensual',
        'active' => true,
    ]);

    $section = Section::query()->create([
        'template_id' => $template->id,
        'title' => 'Resumen',
        'order' => 1,
    ]);

    $report = Report::query()->create([
        'client_id' => $client->id,
        'template_id' => $template->id,
        'month' => 5,
        'year' => 2026,
    ]);

    $visitImport = VisitImport::query()->create([
        'client_id' => $client->id,
        'original_filename' => 'visitas.csv',
        'total_rows' => 1,
        'persisted_rows' => 1,
    ]);

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'visit_import_id' => $visitImport->id,
        'employee_id' => $employee->id,
        'date_init' => now(),
    ]);

    $visitReport = VisitReport::query()->create([
        'visit_id' => $visit->id,
        'location_id' => $location->id,
        'bird_type_id' => $birdType->id,
        'quantity' => 3,
    ]);

    $client->delete();

    expect(Client::query()->whereKey($client->id)->exists())->toBeFalse()
        ->and(Location::query()->whereKey($location->id)->exists())->toBeFalse()
        ->and(Template::query()->whereKey($template->id)->exists())->toBeFalse()
        ->and(Section::query()->whereKey($section->id)->exists())->toBeFalse()
        ->and(Report::query()->whereKey($report->id)->exists())->toBeFalse()
        ->and(VisitImport::query()->whereKey($visitImport->id)->exists())->toBeFalse()
        ->and(Visit::query()->whereKey($visit->id)->exists())->toBeFalse()
        ->and(VisitReport::query()->whereKey($visitReport->id)->exists())->toBeFalse()
        ->and(Employee::query()->whereKey($employee->id)->exists())->toBeTrue()
        ->and(BirdType::query()->whereKey($birdType->id)->exists())->toBeTrue();
});
