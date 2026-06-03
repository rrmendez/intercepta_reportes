<?php

use App\ClientImportMode;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\EditBasePdfTemplate;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Widgets\ClientsStatsOverviewWidget;
use App\Models\Client;
use App\Models\Location;
use App\Models\Report;
use App\Models\Template;
use App\Models\User;
use App\Services\BasePdfTemplateService;
use App\Services\DevPdfReportSample;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportHtmlPreview;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('shows missing email placeholder and import mode in the email column', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $importModeLabel = ClientImportMode::MultiSectorMultiBird->filamentLabel();

    Client::query()->create([
        'name' => 'Cliente Sin Correo',
        'email' => null,
        'import_mode' => ClientImportMode::MultiSectorMultiBird,
        'active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(ListClients::class)
        ->assertSee('Sin correo asignado')
        ->assertSeeHtml('fi-ta-text-description')
        ->assertSeeHtml(e($importModeLabel));
});

it('shows import mode below the client email when an address is set', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $importModeLabel = ClientImportMode::MultiSectorSingleBird->filamentLabel();

    $client = Client::query()->create([
        'name' => 'Cliente Tipo Import',
        'email' => 'contacto@ejemplo.test',
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
        'active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(ListClients::class)
        ->assertSee($client->email)
        ->assertSeeHtml('fi-ta-text-description')
        ->assertSeeHtml(e($importModeLabel));
});

it('lists base pdf template header actions and removes the per-row pdf template action', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Plantilla',
        'active' => true,
    ]);

    $component = Livewire::actingAs($user)
        ->test(ListClients::class);

    foreach (ClientImportMode::cases() as $mode) {
        $component->assertActionExists('basePdfTemplate_'.$mode->value);
    }

    $component->assertTableActionDoesNotExist('editPdfTemplate', null, $client);
});

it('opens the base template editor and saves changes to the template file', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $mode = ClientImportMode::MultiSectorMultiBird;
    $path = resource_path("pdf-report-templates/{$mode->value}.blade.php");
    $original = (string) file_get_contents($path);
    $marker = '<!-- pest-base-template-marker -->';

    Livewire::actingAs($user)
        ->test(EditBasePdfTemplate::class, ['importMode' => $mode->value])
        ->assertSee('Plantilla base: '.$mode->filamentLabel())
        ->set('data.pdf_template', $original.$marker)
        ->call('save')
        ->assertNotified();

    expect(file_get_contents($path))->toContain($marker);

    file_put_contents($path, $original);
});

it('resolves base template urls for each import mode', function (): void {
    foreach (ClientImportMode::cases() as $mode) {
        expect(ClientResource::baseTemplateUrl($mode))
            ->toContain('/base-template/'.$mode->value);
    }
});

it('renders base template preview with the same html pipeline as compose', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $mode = ClientImportMode::SingleSectorSingleBird;
    $sample = DevPdfReportSample::build($mode);
    $client = $sample['client'];
    $report = $sample['report'];
    $period = $sample['period'];

    $source = app(BasePdfTemplateService::class)->readEditableSource($mode);
    $result = app(ReportBladeStringRenderer::class)->tryRenderDocument($source, $client, $report, $period);

    expect($result['ok'])->toBeTrue();

    $previewHtml = app(ReportHtmlPreview::class)->build(
        (string) $result['html'],
        $client,
        $report,
        (string) $period['period_label'],
    );

    $wrapped = app(ReportHtmlPreview::class)->wrap($previewHtml)->toHtml();

    expect($previewHtml)->toContain('report-pdf-preview-sheet')
        ->and($previewHtml)->toContain('data-report-preview-scoped="1"')
        ->and($wrapped)->toContain('class="report-html-preview');

    Livewire::actingAs($user)
        ->test(EditBasePdfTemplate::class, ['importMode' => $mode->value])
        ->assertSeeHtml('class="report-html-preview');
});

it('shows deletion impact summary in the delete client modal', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Con Datos',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Deposito',
        'active' => true,
    ]);

    Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla mensual',
        'active' => true,
    ]);

    Report::query()->create([
        'client_id' => $client->id,
        'month' => 5,
        'year' => 2026,
    ]);

    $counts = $client->deletionImpactCounts();

    expect($counts['locations'])->toBe(1)
        ->and($counts['templates'])->toBe(1)
        ->and($counts['reports'])->toBe(1);

    $html = view('filament.resources.clients.delete-client-confirmation', [
        'client' => $client,
        'counts' => $counts,
    ])->render();

    expect($html)
        ->toContain('Cliente Con Datos')
        ->toContain('sección del cliente')
        ->toContain('plantilla PDF personalizada')
        ->toContain('reporte mensual')
        ->toContain('Esta acción no se puede deshacer');
});

it('loads editable base template source through the service', function (): void {
    $mode = ClientImportMode::SingleSectorSingleBird;
    $source = app(BasePdfTemplateService::class)->readEditableSource($mode);

    expect($source)->not->toBeEmpty()
        ->and($source)->toContain('<!DOCTYPE html>');
});

it('shows client listing metrics widgets for admins', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    Livewire::actingAs($user)
        ->test(ClientsStatsOverviewWidget::class)
        ->assertSee('Visitas promedio por cliente')
        ->assertSee('Reportes enviados')
        ->assertSee('Reportes enviados (mes anterior)');
});
