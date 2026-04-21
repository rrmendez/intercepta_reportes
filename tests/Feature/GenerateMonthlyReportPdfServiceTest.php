<?php

use App\Models\Client;
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
