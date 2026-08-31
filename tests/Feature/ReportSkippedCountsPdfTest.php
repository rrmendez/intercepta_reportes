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
use App\Services\ReportPdfTemplateDefaults;
use App\Services\Reports\ReportPeriodData;
use App\VisitCountStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

function renderReportForModeWithSkippedCount(ClientImportMode $mode, bool $withSkippedCount): string
{
    $client = Client::query()->create([
        'name' => 'Cliente '.$mode->value,
        'active' => true,
        'import_mode' => $mode,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Deposito',
        'active' => true,
    ]);

    $employee = Employee::query()->create(['name' => 'Tecnico', 'active' => true]);
    $palomas = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $days = [
        ['2026-05-05', 10, VisitCountStatus::Counted],
        ['2026-05-12', $withSkippedCount ? null : 9, $withSkippedCount ? VisitCountStatus::NotPerformed : VisitCountStatus::Counted],
        ['2026-05-19', 8, VisitCountStatus::Counted],
    ];

    foreach ($days as [$date, $quantity, $status]) {
        $visit = Visit::query()->create([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'date_init' => "{$date} 09:00:00",
            'date_end' => "{$date} 10:00:00",
            'observation' => 'Control mensual',
        ]);

        VisitReport::query()->create([
            'visit_id' => $visit->id,
            'location_id' => $location->id,
            'bird_type_id' => $palomas->id,
            'quantity' => $quantity,
            'count_status' => $status,
        ]);
    }

    $report = Report::query()->create([
        'client_id' => $client->id,
        'month' => 5,
        'year' => 2026,
        'date_from' => '2026-05-01',
        'date_until' => '2026-05-31',
        'generated_at' => now(),
    ])->setRelation('client', $client);

    $period = app(ReportPeriodData::class)->load(
        $client,
        CarbonImmutable::parse('2026-05-01'),
        CarbonImmutable::parse('2026-05-31'),
        null,
    );

    return app(ReportBladeStringRenderer::class)->renderDocument(
        ReportPdfTemplateDefaults::bladeSourceForClient($client),
        $client,
        $report,
        $period,
    );
}

it('explains the chart gaps in the pdf template of every import mode', function (ClientImportMode $mode): void {
    $html = renderReportForModeWithSkippedCount($mode, withSkippedCount: true);

    expect($html)->toContain('visitas sin conteo posible');
})->with([
    ClientImportMode::SingleSectorSingleBird,
    ClientImportMode::SingleSectorMultiBird,
    ClientImportMode::MultiSectorSingleBird,
    ClientImportMode::MultiSectorMultiBird,
]);

it('omits the gap explanation when every count was performed', function (ClientImportMode $mode): void {
    $html = renderReportForModeWithSkippedCount($mode, withSkippedCount: false);

    expect($html)->not->toContain('visitas sin conteo posible');
})->with([
    ClientImportMode::SingleSectorSingleBird,
    ClientImportMode::SingleSectorMultiBird,
    ClientImportMode::MultiSectorSingleBird,
    ClientImportMode::MultiSectorMultiBird,
]);
