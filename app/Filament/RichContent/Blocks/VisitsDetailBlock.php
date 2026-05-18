<?php

namespace App\Filament\RichContent\Blocks;

use App\Models\Visit;

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
                    (int) $visit->visitReports->sum('quantity'),
                    $visit->observation ?: '-',
                ];
            })
            ->values()
            ->all();

        return '<section class="section"><h2>Detalle de visitas</h2>'.static::table(['Fecha', 'Empleado', 'Observaciones', 'Cantidad', 'Nota'], $rows).'</section>';
    }
}
