<?php

namespace App\Filament\RichContent\Blocks;

class TotalsByLocationBlock extends ReportTemplateBlock
{
    public static function getId(): string
    {
        return 'totals-by-location';
    }

    public static function getLabel(): string
    {
        return 'Totales por seccion';
    }

    public static function toHtml(array $config, array $data): ?string
    {
        $rows = collect($data['totals_by_location'] ?? [])
            ->map(fn (int $total, string $name): array => [$name !== '' ? $name : 'No aplica', $total])
            ->values()
            ->all();

        return '<section class="section"><h2>Cantidad por seccion</h2>'.static::table(['Seccion', 'Cantidad'], $rows).'</section>';
    }
}
