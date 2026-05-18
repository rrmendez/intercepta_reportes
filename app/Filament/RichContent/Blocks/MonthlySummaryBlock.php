<?php

namespace App\Filament\RichContent\Blocks;

class MonthlySummaryBlock extends ReportTemplateBlock
{
    public static function getId(): string
    {
        return 'monthly-summary';
    }

    public static function getLabel(): string
    {
        return 'Resumen mensual';
    }

    public static function toHtml(array $config, array $data): ?string
    {
        return sprintf(
            '<section class="section"><h2>Resumen</h2><p><strong>Total de visitas:</strong> %d</p><p><strong>Total de observaciones:</strong> %d</p><p><strong>Cantidad total:</strong> %d</p></section>',
            (int) ($data['visits_count'] ?? 0),
            (int) ($data['total_observations'] ?? 0),
            (int) ($data['total_quantity'] ?? 0),
        );
    }
}
