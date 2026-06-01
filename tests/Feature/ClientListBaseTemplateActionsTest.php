<?php

use App\ClientImportMode;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\EditBasePdfTemplate;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
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

it('loads editable base template source through the service', function (): void {
    $mode = ClientImportMode::SingleSectorSingleBird;
    $source = app(BasePdfTemplateService::class)->readEditableSource($mode);

    expect($source)->not->toBeEmpty()
        ->and($source)->toContain('<!DOCTYPE html>');
});
