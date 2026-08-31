<?php

namespace App\Filament\RichContent\Blocks;

use App\Models\Visit;
use App\Models\VisitReport;

class VisitsDetailBlock extends ReportTemplateBlock
{
    public static function getId(): string
    {
        return 'visits-detail';
    }

    public static function getLabel(): string
    {
        return 'Detalle de visitas';
    }

    public static function toHtml(array $config, array $data): ?string
    {
        $rows = static::collection($data, 'visits')
            ->map(function (Visit $visit): array {
                return [
                    static::date($visit->date_init),
                    $visit->employee?->name ?? '-',
                    $visit->visitReports->count(),
                    static::quantityCell($visit),
                    $visit->observation ?: '-',
                ];
            })
            ->values()
            ->all();

        return '<section class="section"><h2>Detalle de visitas</h2>'.static::table(['Fecha', 'Empleado', 'Observaciones', 'Cantidad', 'Nota'], $rows).'</section>';
    }

    /**
     * Una visita sin ningun conteo posible no se informa como cero, y un total
     * parcial aclara cuantos sectores quedaron sin contar para no leerse como
     * un conteo completo.
     */
    private static function quantityCell(Visit $visit): string
    {
        $counted = $visit->visitReports->filter(fn (VisitReport $report): bool => $report->wasCounted());
        $skipped = $visit->visitReports->count() - $counted->count();

        if ($counted->isEmpty()) {
            return $visit->visitReports->isEmpty() ? '0' : 'Sin conteo';
        }

        $total = (string) (int) $counted->sum('quantity');

        return $skipped > 0
            ? $total.' ('.$skipped.' sin conteo)'
            : $total;
    }
}
