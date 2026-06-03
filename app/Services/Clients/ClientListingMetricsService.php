<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\Report;
use App\Models\Visit;
use App\ReportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ClientListingMetricsService
{
    public function clientCount(): int
    {
        return Client::query()->count();
    }

    public function visitCount(): int
    {
        return Visit::query()->count();
    }

    public function averageVisitsPerClient(): float
    {
        $clientCount = $this->clientCount();

        if ($clientCount === 0) {
            return 0.0;
        }

        return round($this->visitCount() / $clientCount, 1);
    }

    public function sentReportsCount(): int
    {
        return Report::query()
            ->where('status', ReportStatus::Sent)
            ->count();
    }

    public function sentReportsLastMonthCount(): int
    {
        $start = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $end = CarbonImmutable::now()->subMonthNoOverflow()->endOfMonth();

        return Report::query()
            ->where('status', ReportStatus::Sent)
            ->whereBetween('email_sent_at', [$start, $end])
            ->count();
    }

    public function lastMonthLabel(): string
    {
        $lastMonth = CarbonImmutable::now()->subMonthNoOverflow();

        return $this->spanishMonthYear($lastMonth->month, $lastMonth->year);
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     visitsAverage: list<float>,
     *     sentReports: list<int>
     * }
     */
    public function monthlyTrends(int $months = 6): array
    {
        $clientCount = max($this->clientCount(), 1);
        $rangeStart = CarbonImmutable::now()->subMonths($months - 1)->startOfMonth();
        $rangeEnd = CarbonImmutable::now()->endOfMonth();

        $visitsByMonth = $this->visitsGroupedByMonth($rangeStart, $rangeEnd);
        $sentReportsByMonth = $this->sentReportsGroupedByMonth($rangeStart, $rangeEnd);

        $labels = [];
        $visitsAverage = [];
        $sentReports = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $month = CarbonImmutable::now()->subMonths($offset);
            $key = $month->format('Y-m');

            $labels[] = $this->shortMonthLabel($month);
            $visitsAverage[] = round(($visitsByMonth[$key] ?? 0) / $clientCount, 1);
            $sentReports[] = $sentReportsByMonth[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'visitsAverage' => $visitsAverage,
            'sentReports' => $sentReports,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function visitsGroupedByMonth(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
        $monthExpression = $this->monthKeyExpression('date_init');

        return Visit::query()
            ->whereBetween('date_init', [$rangeStart, $rangeEnd])
            ->selectRaw("{$monthExpression} as month_key, COUNT(*) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function sentReportsGroupedByMonth(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
        $monthExpression = $this->monthKeyExpression('email_sent_at');

        return Report::query()
            ->where('status', ReportStatus::Sent)
            ->whereNotNull('email_sent_at')
            ->whereBetween('email_sent_at', [$rangeStart, $rangeEnd])
            ->selectRaw("{$monthExpression} as month_key, COUNT(*) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    private function monthKeyExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private function shortMonthLabel(CarbonImmutable $month): string
    {
        return match ($month->month) {
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',
            default => $month->format('M'),
        };
    }

    private function spanishMonthYear(int $month, int $year): string
    {
        $monthName = match ($month) {
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
            default => (string) $month,
        };

        return "{$monthName} {$year}";
    }
}
