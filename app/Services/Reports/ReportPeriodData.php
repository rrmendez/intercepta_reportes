<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Filament\Tables\VisitSpreadsheetTable;
use App\Models\Client;
use App\Models\Report;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Services\TemplateRichContent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class ReportPeriodData
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function normalizeRange(CarbonInterface|string $dateFrom, CarbonInterface|string $dateUntil): array
    {
        $from = CarbonImmutable::parse((string) $dateFrom)->startOfDay();
        $until = CarbonImmutable::parse((string) $dateUntil)->endOfDay();

        if ($from->toDateString() > $until->toDateString()) {
            throw new InvalidArgumentException('La fecha de inicio no puede ser posterior a la fecha de fin.');
        }

        return [$from, $until];
    }

    public function periodLabel(CarbonImmutable $from, CarbonImmutable $until): string
    {
        if ($this->isFullCalendarMonth($from, $until)) {
            return $this->spanishMonthYear($from->month, $from->year);
        }

        return $from->format('d/m/Y').' - '.$until->format('d/m/Y');
    }

    /**
     * @return array{
     *     client: Client,
     *     date_from: CarbonImmutable,
     *     date_until: CarbonImmutable,
     *     period_label: string,
     *     visits: Collection<int, Visit>,
     *     visit_reports: Collection<int, VisitReport>,
     *     aggregations: array{totals_by_bird_type: array<string, int>, totals_by_location: array<string, int>},
     *     rich_content_data: array<string, mixed>,
     *     snapshot: array<string, mixed>
     * }
     */
    public function load(Client $client, CarbonInterface|string $dateFrom, CarbonInterface|string $dateUntil, ?Report $report = null): array
    {
        [$from, $until] = $this->normalizeRange($dateFrom, $dateUntil);
        $periodLabel = $this->periodLabel($from, $until);

        $visits = Visit::query()
            ->whereBelongsTo($client)
            ->whereBetween('date_init', [$from, $until])
            ->with(['employee', 'visitReports.location', 'visitReports.birdType'])
            ->orderByDesc('date_init')
            ->orderByDesc('id')
            ->get();

        $visitReports = $visits->flatMap->visitReports;
        $aggregations = TemplateRichContent::aggregations($visitReports);

        $richContentData = TemplateRichContent::reportData(
            client: $client,
            periodLabel: $periodLabel,
            visits: $visits,
            visitReports: $visitReports,
            aggregations: $aggregations,
            report: $report,
        );

        return [
            'client' => $client,
            'date_from' => $from,
            'date_until' => $until,
            'period_label' => $periodLabel,
            'visits' => $visits,
            'visit_reports' => $visitReports,
            'aggregations' => $aggregations,
            'rich_content_data' => $richContentData,
            'snapshot' => [
                'period' => $periodLabel,
                'visits_count' => $visits->count(),
            ],
        ];
    }

    /**
     * Loads period data using the same spreadsheet filter row as {@see VisitSpreadsheetTable} on the
     * compose-report visits preview (client + rango / modo de periodo).
     *
     * @param  array<string, mixed>  $spreadsheetRow  Misma forma que tableFilters.spreadsheet en la tabla de visitas.
     * @return array{
     *     client: Client,
     *     date_from: CarbonImmutable,
     *     date_until: CarbonImmutable,
     *     period_label: string,
     *     visits: Collection<int, Visit>,
     *     visit_reports: Collection<int, VisitReport>,
     *     aggregations: array{totals_by_bird_type: array<string, int>, totals_by_location: array<string, int>},
     *     rich_content_data: array<string, mixed>,
     *     snapshot: array<string, mixed>
     * }
     */
    public function loadForSpreadsheetRow(Client $client, array $spreadsheetRow, ?Report $report = null): array
    {
        $spreadsheet = app(VisitSpreadsheetTable::class);
        $range = $spreadsheet->resolveSpreadsheetFilterDateRange($spreadsheetRow);

        if ($range === null) {
            throw new InvalidArgumentException('No se pudo resolver el rango de fechas del filtro de visitas.');
        }

        $from = CarbonImmutable::parse($range['date_from'])->startOfDay();
        $until = CarbonImmutable::parse($range['date_until'])->endOfDay();
        $periodLabel = $this->periodLabel($from, $until);

        $query = Visit::query()
            ->with(['employee', 'visitReports.location', 'visitReports.birdType'])
            ->orderByDesc('date_init')
            ->orderByDesc('id');

        $visits = $spreadsheet->applySpreadsheetFilterQuery(
            $query,
            $spreadsheetRow,
            (int) $client->getKey(),
        )->get();

        $visitReports = $visits->flatMap->visitReports;
        $aggregations = TemplateRichContent::aggregations($visitReports);

        $richContentData = TemplateRichContent::reportData(
            client: $client,
            periodLabel: $periodLabel,
            visits: $visits,
            visitReports: $visitReports,
            aggregations: $aggregations,
            report: $report,
        );

        return [
            'client' => $client,
            'date_from' => $from,
            'date_until' => $until,
            'period_label' => $periodLabel,
            'visits' => $visits,
            'visit_reports' => $visitReports,
            'aggregations' => $aggregations,
            'rich_content_data' => $richContentData,
            'snapshot' => [
                'period' => $periodLabel,
                'visits_count' => $visits->count(),
            ],
        ];
    }

    private function isFullCalendarMonth(CarbonImmutable $from, CarbonImmutable $until): bool
    {
        if (! $from->isStartOfDay() || ! $until->isEndOfDay()) {
            return false;
        }

        if ($from->day !== 1 || ! $from->isSameMonth($until)) {
            return false;
        }

        $lastDay = $from->daysInMonth;

        return $until->day === $lastDay;
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
            default => throw new InvalidArgumentException('Mes invalido.'),
        };

        return $monthName.' '.$year;
    }
}
