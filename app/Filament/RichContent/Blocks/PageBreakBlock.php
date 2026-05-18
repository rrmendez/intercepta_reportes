<?php

namespace App\Filament\RichContent\Blocks;

class PageBreakBlock extends ReportTemplateBlock
{
    public static function getId(): string
    {
        return 'page-break';
    }

    public static function getLabel(): string
    {
        return 'Salto de pagina';
    }

    public static function toPreviewHtml(array $config): ?string
    {
        return '<div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-2 text-center text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Salto de pagina</div>';
    }

    public static function toHtml(array $config, array $data): ?string
    {
        return '<div class="page-break"></div>';
    }
}
