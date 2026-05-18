<?php

namespace App\Filament\RichContent\Blocks;

use App\Models\VisitReport;

class VisitObservationsBlock extends ReportTemplateBlock
{
    public static function getId(): string
    {
        return 'visit-observations';
    }

    public static function getLabel(): string
    {
        return 'Observaciones de visitas';
    }

    public static function toHtml(array $config, array $data): ?string
    {
        $rows = static::collection($data, 'visit_reports')
            ->map(function (VisitReport $visitReport): array {
                return [
                    static::date($visitReport->visit?->date_init),
                    $visitReport->location?->name ?? '-',
                    $visitReport->birdType?->name ?? '-',
                    $visitReport->quantity,
                    $visitReport->observation ?: '-',
                ];
            })
            ->values()
            ->all();

        return '<section class="section"><h2>Observaciones de visitas</h2>'.static::table(['Fecha', 'Seccion', 'Tipo de ave', 'Cantidad', 'Observacion'], $rows).'</section>';
    }
}
