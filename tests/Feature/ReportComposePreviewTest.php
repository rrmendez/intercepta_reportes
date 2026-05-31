<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Report;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportHtmlPreview;
use App\Services\ReportPdfDocumentHtml;
use App\Services\ReportPdfTemplateDefaults;
use App\Services\Reports\ReportPeriodData;
use Carbon\CarbonImmutable;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('aligns compose preview with the pdf print document pipeline', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Preview',
        'active' => true,
        'import_mode' => ClientImportMode::SingleSectorSingleBird,
    ]);

    $employee = Employee::query()->create([
        'name' => 'Tecnico',
        'active' => true,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Planta',
        'active' => true,
    ]);

    $birdType = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => '2026-05-10 10:00:00',
        'date_end' => '2026-05-10 11:00:00',
    ]);

    VisitReport::query()->create([
        'visit_id' => $visit->id,
        'location_id' => $location->id,
        'bird_type_id' => $birdType->id,
        'quantity' => 3,
    ]);

    $period = app(ReportPeriodData::class)->load(
        $client,
        CarbonImmutable::parse('2026-05-01'),
        CarbonImmutable::parse('2026-05-31'),
        null,
    );

    $report = Report::make([
        'client_id' => $client->id,
        'date_from' => '2026-05-01',
        'date_until' => '2026-05-31',
        'generated_at' => now(),
    ])->setRelation('client', $client);

    $documentHtml = app(ReportBladeStringRenderer::class)->renderDocument(
        ReportPdfTemplateDefaults::bladeSourceForClient($client),
        $client,
        $report,
        $period,
    );

    $headerHtml = view('pdf.partials.report-pdf-default-header')->render();
    $pdfPrepared = ReportPdfDocumentHtml::preparePrintDocument($documentHtml, $headerHtml);

    $previewHtml = app(ReportHtmlPreview::class)->build(
        $documentHtml,
        $client,
        $report,
        (string) $period['period_label'],
    );

    $wrapped = app(ReportHtmlPreview::class)->wrap($previewHtml, 3)->toHtml();

    expect($pdfPrepared)->toContain('report-pdf-default-header-root')
        ->and($pdfPrepared)->not->toContain('report-pdf-fixed-footer-root')
        ->and($previewHtml)->toContain('report-pdf-preview-sheet')
        ->and(substr_count($previewHtml, 'class="report-pdf-preview-sheet__body"'))->toBe(7)
        ->and(substr_count($previewHtml, 'class="report-pdf-preview-sheet__chrome-footer"'))->toBe(7)
        ->and(substr_count($previewHtml, 'class="report-pdf-preview-sheet__header"'))->toBe(5)
        ->and($previewHtml)->toContain('report-contact-page__center')
        ->and($previewHtml)->not->toContain('report-contact-page__gold-line')
        ->and($previewHtml)->toContain('report-fauna-evolution-page')
        ->and($previewHtml)->toContain('id="report-charts-config"')
        ->and($previewHtml)->toContain('Informe Nº')
        ->and($previewHtml)->toContain('Período')
        ->and($previewHtml)->not->toContain('report-pdf-fixed-footer-root')
        ->and($previewHtml)->not->toContain('Salto de pagina')
        ->and($previewHtml)->not->toContain('window.ReportPdfCharts =')
        ->and($previewHtml)->toContain('data-report-preview-scoped="1"')
        ->and($previewHtml)->toContain('report-service-details-page__table')
        ->and($previewHtml)->toContain('Método')
        ->and($previewHtml)->toContain('Abundancia al último día de servicio')
        ->and($previewHtml)->not->toMatch('/<style(?![^>]*data-report-preview-scoped)[^>]*>[\s\S]*?\bbody\s*\{/i')
        ->and($wrapped)->toContain('data-report-preview-revision="3"');
});
