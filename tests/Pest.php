<?php

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
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function renderSingleSectorSingleBirdTemplateHtml(
    Client $client,
    string $dateFrom = '2026-03-01',
    string $dateUntil = '2026-03-31',
): string {
    $report = Report::make([
        'client_id' => $client->id,
        'date_from' => $dateFrom,
        'date_until' => $dateUntil,
        'generated_at' => now(),
    ])->setRelation('client', $client);

    $period = app(ReportPeriodData::class)->load(
        $client,
        CarbonImmutable::parse($dateFrom),
        CarbonImmutable::parse($dateUntil),
        null,
    );

    return app(ReportBladeStringRenderer::class)->renderDocument(
        ReportPdfTemplateDefaults::bladeSourceForClient($client),
        $client,
        $report,
        $period,
    );
}

/**
 * @return Collection<int, VisitReport>
 */
function seedCurrentSituationVisitReports(
    Client $client,
    Location $location,
    BirdType $birdType,
    Employee $employee,
    string $date,
    int $quantity,
    string $observation,
): Collection {
    $visit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'date_init' => "{$date} 17:00:00",
        'date_end' => "{$date} 18:00:00",
        'observation' => $observation,
    ]);

    return VisitReport::query()
        ->whereKey(VisitReport::query()->create([
            'visit_id' => $visit->id,
            'location_id' => $location->id,
            'bird_type_id' => $birdType->id,
            'quantity' => $quantity,
        ])->id)
        ->with(['visit.employee', 'birdType'])
        ->get();
}
