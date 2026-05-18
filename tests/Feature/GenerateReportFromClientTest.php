<?php

use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Reports\Pages\CreateReport;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Report;
use App\Models\Template;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReport;
use App\ReportStatus;
use Database\Seeders\BirdTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(BirdTypeSeeder::class);
});

it('opens the client report action, creates a draft report, and redirects to compose with that report', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Reporte',
        'active' => true,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test(ListClients::class)
        ->assertTableActionExists('generateReport', null, $client);

    $livewire->callTableAction('generateReport', $client, [
        'date_from' => '2026-05-10',
        'date_until' => '2026-06-05',
    ]);

    $report = Report::query()->whereBelongsTo($client)->sole();

    expect($report->status)->toBe(ReportStatus::Draft)
        ->and($report->date_from?->toDateString())->toBe('2026-05-10')
        ->and($report->date_until?->toDateString())->toBe('2026-06-05')
        ->and($report->generated_by_user_id)->toBe($user->id);

    $livewire->assertRedirect(ReportResource::getUrl('compose', [
        'report_id' => $report->id,
        'client_id' => $client->id,
        'date_from' => '2026-05-10',
        'date_until' => '2026-06-05',
    ]));
});

it('creates a separate draft each time the client generate report action is used for the same range', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Reporte Dup',
        'active' => true,
    ]);

    $payload = [
        'date_from' => '2026-05-01',
        'date_until' => '2026-05-31',
    ];

    Livewire::actingAs($user)
        ->test(ListClients::class)
        ->callTableAction('generateReport', $client, $payload)
        ->assertRedirect();

    expect(Report::query()->whereBelongsTo($client)->count())->toBe(1);

    Livewire::actingAs($user)
        ->test(ListClients::class)
        ->callTableAction('generateReport', $client, $payload)
        ->assertRedirect();

    expect(Report::query()->whereBelongsTo($client)->count())->toBe(2);
});

it('previews template blocks with visits filtered by client and date range before generating the pdf', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $user->assignRole('Admin');

    [$client, $template] = createReportFlowFixture();

    Livewire::actingAs($user)
        ->test(CreateReport::class)
        ->set('data.client_id', (string) $client->id)
        ->set('data.template_id', (string) $template->id)
        ->set('data.date_from', '2026-05-01')
        ->set('data.date_until', '2026-05-31')
        ->assertSee('Visitas del reporte')
        ->assertSee('Recorrido incluido')
        ->assertSee('Nido incluido')
        ->assertDontSee('Recorrido excluido')
        ->call('generate')
        ->assertHasNoErrors()
        ->assertRedirect(ReportResource::getUrl('index'));

    $report = Report::query()->whereBelongsTo($client)->firstOrFail();

    expect($report->date_from?->toDateString())->toBe('2026-05-01')
        ->and($report->date_until?->toDateString())->toBe('2026-05-31')
        ->and($report->template_id)->toBe($template->id)
        ->and($report->data['visits_count'])->toBe(1);

    Storage::disk('local')->assertExists('reports/Cliente Reporte mayo 2026.pdf');
});

/**
 * @return array{Client, Template}
 */
function createReportFlowFixture(): array
{
    $client = Client::query()->create([
        'name' => 'Cliente Reporte',
        'active' => true,
    ]);

    $template = Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla reporte',
        'pdf_template' => <<<'BLADE'
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"></head><body>
@foreach ($visits as $row)
<p>{{ $row['visit_observation'] }}</p>
@endforeach
@foreach ($visit_reports as $vr)
<p>{{ $vr->observation }}</p>
@endforeach
@include('pdf.partials.report-pdf-fixed-footer', [
    'client' => $client,
    'report' => $report,
    'period_label' => $period_label,
])
</body></html>
BLADE,
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Tecnico Reporte',
        'active' => true,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Recepcion',
        'active' => true,
    ]);

    $birdType = BirdType::query()->where('name', 'Palomas')->firstOrFail();

    $includedVisit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-12 09:00:00',
        'date_end' => '2026-05-12 10:00:00',
        'observation' => 'Recorrido incluido',
    ]);

    VisitReport::query()->create([
        'visit_id' => $includedVisit->id,
        'location_id' => $location->id,
        'bird_type_id' => $birdType->id,
        'quantity' => 3,
        'observation' => 'Nido incluido',
    ]);

    $excludedVisit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-06-12 09:00:00',
        'date_end' => '2026-06-12 10:00:00',
        'observation' => 'Recorrido excluido',
    ]);

    VisitReport::query()->create([
        'visit_id' => $excludedVisit->id,
        'location_id' => $location->id,
        'bird_type_id' => $birdType->id,
        'quantity' => 5,
        'observation' => 'Nido excluido',
    ]);

    return [$client, $template];
}
