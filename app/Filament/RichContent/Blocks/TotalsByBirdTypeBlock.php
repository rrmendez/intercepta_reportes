<?php

namespace App\Filament\RichContent\Blocks;

class TotalsByBirdTypeBlock extends ReportTemplateBlock
{
    public static function getId(): string
    {
        return 'totals-by-bird-type';
    }

    public static function getLabel(): string
    {
        return 'Totales por tipo de ave';
    }

    public static function toHtml(array $config, array $data): ?string
    {
        $rows = collect($data['totals_by_bird_type'] ?? [])
            ->map(fn (int $total, string $name): array => [$name !== '' ? $name : 'No aplica', $total])
            ->values()
            ->all();

        return '<section class="section"><h2>Cantidad por tipo de ave</h2>'.static::table(['Tipo de ave', 'Cantidad'], $rows).'</section>';
    }
}
