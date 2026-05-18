<?php

use App\Models\Client;
use App\Models\Template;
use App\ReportStatus;
use App\Services\GenerateMonthlyReportPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('generates a monthly pdf in local storage with the expected filename format', function () {
    Storage::fake('local');

    $client = Client::query()->create([
        'name' => 'Conaprole',
        'active' => true,
    ]);

    $report = app(GenerateMonthlyReportPdfService::class)->generate(
        clientId: $client->id,
        month: 2,
        year: 2026,
    );

    expect($report->generated_file_path)->toBe('reports/Conaprole febrero 2026.pdf')
        ->and($report->status)->toBe(ReportStatus::Generated);

    Storage::disk('local')->assertExists('reports/Conaprole febrero 2026.pdf');
});

it('generates a monthly pdf using the active blade template', function () {
    Storage::fake('local');

    $client = Client::query()->create([
        'name' => 'Conaprole',
        'active' => true,
    ]);

    $template = Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla mensual',
        'pdf_template' => <<<'BLADE'
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"></head><body><p>{{ $client->name }}</p>
@include('pdf.partials.report-pdf-fixed-footer', [
    'client' => $client,
    'report' => $report,
    'period_label' => $period_label,
])
</body></html>
BLADE,
        'active' => true,
    ]);

    $report = app(GenerateMonthlyReportPdfService::class)->generate(
        clientId: $client->id,
        month: 5,
        year: 2026,
    );

    expect($report->template_id)->toBe($template->id)
        ->and($report->generated_file_path)->toBe('reports/Conaprole mayo 2026.pdf')
        ->and($report->status)->toBe(ReportStatus::Generated);

    Storage::disk('local')->assertExists('reports/Conaprole mayo 2026.pdf');
});

it('generates a pdf for an arbitrary date range', function () {
    Storage::fake('local');

    $client = Client::query()->create([
        'name' => 'Conaprole',
        'active' => true,
    ]);

    $report = app(GenerateMonthlyReportPdfService::class)->generateForRange(
        clientId: $client->id,
        dateFrom: '2026-05-10',
        dateUntil: '2026-06-05',
    );

    expect($report->date_from?->toDateString())->toBe('2026-05-10')
        ->and($report->date_until?->toDateString())->toBe('2026-06-05')
        ->and($report->generated_file_path)->toBe('reports/Conaprole 10-05-2026 al 05-06-2026.pdf')
        ->and($report->data['period'])->toBe('10/05/2026 - 05/06/2026');

    Storage::disk('local')->assertExists('reports/Conaprole 10-05-2026 al 05-06-2026.pdf');
});
