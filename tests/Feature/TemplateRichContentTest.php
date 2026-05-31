<?php

use App\ClientImportMode;
use App\Filament\Resources\Clients\Pages\EditClientTemplate;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Report;
use App\Models\Template;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportPdfTemplateDefaults;
use App\Services\TemplateRichContent;
use Database\Seeders\BirdTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('aggregates visit reports by bird type and location', function (): void {
    [$client, $visitReports, $visits] = createClientVisitReportFixture();

    $aggregations = TemplateRichContent::aggregations($visitReports);

    expect($aggregations['totals_by_bird_type'])->toHaveKey('Palomas')
        ->and($aggregations['totals_by_bird_type']['Palomas'])->toBe(3)
        ->and($aggregations['totals_by_location'])->toHaveKey('Recepcion')
        ->and($aggregations['totals_by_location']['Recepcion'])->toBe(3);
});

it('creates and saves the active client pdf blade template from the Filament page', function (): void {
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Demo',
        'active' => true,
    ]);

    $blade = '<p>{{ $client->name }}</p>';

    Livewire::actingAs($user)
        ->test(EditClientTemplate::class, ['record' => $client->id])
        ->set('data.name', 'Plantilla operativa')
        ->set('data.pdf_template', $blade)
        ->call('save')
        ->assertHasNoErrors();

    $template = Template::query()->whereBelongsTo($client)->first();

    expect($template)->not->toBeNull()
        ->and($template->name)->toBe('Plantilla operativa')
        ->and($template->active)->toBeTrue()
        ->and($template->pdf_template)->toBe($blade);
});

it('ships default blade sources per import mode on disk', function (): void {
    foreach (ClientImportMode::cases() as $mode) {
        $path = resource_path("pdf-report-templates/{$mode->value}.blade.php");
        expect(is_readable($path))->toBeTrue("Missing {$mode->value}.blade.php");
    }
});

it('loads default blade text for each client import mode', function (): void {
    foreach (ClientImportMode::cases() as $mode) {
        $client = Client::query()->create([
            'name' => 'Cliente Demo',
            'active' => true,
            'import_mode' => $mode,
        ]);

        $source = ReportPdfTemplateDefaults::bladeSourceForClient($client);
        expect($source)->not->toBeEmpty()
            ->and($source)->toContain('<!DOCTYPE html>');
    }
});

it('renders a blade string with report period data', function (): void {
    [$client, $visitReports, $visits] = createClientVisitReportFixture();
    $periodLabel = 'Mayo 2026';
    $aggregations = TemplateRichContent::aggregations($visitReports);
    $rich = TemplateRichContent::reportData(
        client: $client,
        periodLabel: $periodLabel,
        visits: $visits,
        visitReports: $visitReports,
        aggregations: $aggregations,
    );

    expect($rich['visits'])->toBeArray()->toHaveCount(1);
    expect($rich['visit_columns'])->toBeArray()
        ->and(array_column($rich['visit_columns'], 'key'))->toContain('visit_date_init', 'visit_date_end', 'employee.name', 'visit_observation');
    foreach ($rich['visit_columns'] as $column) {
        expect(mb_substr((string) $column['label'], 0, 1))->toBe(mb_strtoupper(mb_substr((string) $column['label'], 0, 1)));
    }
    $row = $rich['visits'][0];
    expect($row)->toHaveKeys(['visit_date_init', 'visit_date_end', 'employee.name', 'visit_observation'])
        ->and($row['visit_observation'])->toBe('Recorrido mensual')
        ->and($row['employee.name'])->toBe('Tecnico Uno')
        ->and($row['visit_date_init'])->toBe('12/05/2026 09:00')
        ->and($row['visit_date_end'])->toBe('12/05/2026 10:00');
    $qtyKeys = array_values(array_filter(array_keys($row), fn (string $k): bool => str_starts_with($k, 'qty_')));
    expect($qtyKeys)->toHaveCount(1)
        ->and($row[$qtyKeys[0]])->toBe('3');

    $period = [
        'client' => $client,
        'date_from' => now()->startOfMonth()->toImmutable(),
        'date_until' => now()->endOfMonth()->toImmutable(),
        'period_label' => $periodLabel,
        'visits' => $visits,
        'visit_reports' => $visitReports,
        'aggregations' => $aggregations,
        'rich_content_data' => $rich,
        'snapshot' => ['period' => $periodLabel],
    ];

    $report = Report::make([
        'client_id' => $client->id,
        'generated_at' => now(),
    ])->setRelation('client', $client);

    $html = app(ReportBladeStringRenderer::class)->renderDocument(
        '<p>{{ $client->name }} — {{ $period_label }}</p>',
        $client,
        $report,
        $period,
    );

    expect($html)->toContain('Cliente Demo')
        ->toContain('Mayo 2026');
});

it('strips the document shell for admin previews so the panel DOM stays valid', function (): void {
    $renderer = app(ReportBladeStringRenderer::class);

    $full = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>T</title></head><body><p class="x">Hello</p></body></html>';

    $inner = $renderer->htmlForAdminPreview($full);

    expect($inner)->toContain('Hello')
        ->and($inner)->toContain('class="x"')
        ->not->toContain('<html')
        ->not->toContain('<!DOCTYPE');

    expect($renderer->htmlForAdminPreview('<p>Fragment</p>'))->toContain('Fragment')
        ->not->toContain('<html');
});

/**
 * @return array{Client, Collection<int, VisitReport>, Collection<int, Visit>}
 */
function createClientVisitReportFixture(): array
{
    $client = Client::query()->create([
        'name' => 'Cliente Demo',
        'active' => true,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Tecnico Uno',
        'active' => true,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Recepcion',
        'active' => true,
    ]);

    $birdType = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-12 09:00:00',
        'date_end' => '2026-05-12 10:00:00',
        'observation' => 'Recorrido mensual',
    ]);

    VisitReport::query()->create([
        'visit_id' => $visit->id,
        'location_id' => $location->id,
        'bird_type_id' => $birdType->id,
        'quantity' => 3,
        'observation' => 'Nido detectado',
    ]);

    $visits = Visit::query()
        ->whereKey($visit->id)
        ->with(['employee', 'visitReports.location', 'visitReports.birdType'])
        ->get();

    return [$client, $visits->flatMap->visitReports, $visits];
}
