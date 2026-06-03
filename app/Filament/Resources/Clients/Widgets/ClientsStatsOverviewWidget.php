<?php

namespace App\Filament\Resources\Clients\Widgets;

use App\Models\Client;
use App\Services\Clients\ClientListingMetricsService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientsStatsOverviewWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('viewAny', Client::class);
    }

    protected function getStats(): array
    {
        $metrics = app(ClientListingMetricsService::class);
        $trends = $metrics->monthlyTrends();

        return [
            Stat::make('Visitas promedio por cliente', number_format($metrics->averageVisitsPerClient(), 1, ',', '.'))
                ->description("Basado en {$metrics->clientCount()} clientes y {$metrics->visitCount()} visitas totales")
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentList)
                ->chart($trends['visitsAverage'])
                ->chartColor('primary')
                ->icon(Heroicon::OutlinedClipboardDocumentList),
            Stat::make('Reportes enviados', number_format($metrics->sentReportsCount(), 0, ',', '.'))
                ->description('Histórico acumulado')
                ->descriptionIcon(Heroicon::OutlinedPaperAirplane)
                ->chart($trends['sentReports'])
                ->chartColor('primary')
                ->icon(Heroicon::OutlinedPaperAirplane),
            Stat::make('Reportes enviados (mes anterior)', number_format($metrics->sentReportsLastMonthCount(), 0, ',', '.'))
                ->description($metrics->lastMonthLabel())
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->chart($trends['sentReports'])
                ->chartColor('primary')
                ->icon(Heroicon::OutlinedCalendarDays),
        ];
    }
}
