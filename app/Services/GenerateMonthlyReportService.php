<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Report;
use App\Models\Template;
use App\Models\Visit;
use App\ReportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateMonthlyReportService
{
    public function generate(int $clientId, int $month, int $year, ?int $templateId = null): Report
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Month must be between 1 and 12.');
        }

        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Year is out of range.');
        }

        $client = Client::query()->findOrFail($clientId);
        $template = $templateId ? Template::query()->whereBelongsTo($client)->findOrFail($templateId) : null;

        $startDate = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->endOfMonth()->endOfDay();

        $visits = Visit::query()
            ->whereBelongsTo($client)
            ->whereBetween('date_init', [$startDate, $endDate])
            ->with(['employee', 'visitReports.location', 'visitReports.birdType'])
            ->get();

        $visitReports = $visits->flatMap->visitReports;

        $totalsByBirdType = $visitReports
            ->groupBy('bird_type_id')
            ->mapWithKeys(fn ($group): array => [
                (string) $group->first()->birdType?->name => (int) $group->sum('quantity'),
            ])
            ->all();

        $totalsByLocation = $visitReports
            ->groupBy('location_id')
            ->mapWithKeys(fn ($group): array => [
                (string) $group->first()->location?->name => (int) $group->sum('quantity'),
            ])
            ->all();

        return DB::transaction(function () use ($client, $template, $month, $year, $visits, $totalsByBirdType, $totalsByLocation): Report {
            return Report::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'month' => $month,
                    'year' => $year,
                ],
                [
                    'template_id' => $template?->id,
                    'status' => ReportStatus::Generated,
                    'generated_at' => now(),
                    'data' => [
                        'client' => $client->name,
                        'period' => sprintf('%04d-%02d', $year, $month),
                        'visits_count' => $visits->count(),
                        'totals_by_bird_type' => $totalsByBirdType,
                        'totals_by_location' => $totalsByLocation,
                    ],
                ],
            );
        });
    }
}
