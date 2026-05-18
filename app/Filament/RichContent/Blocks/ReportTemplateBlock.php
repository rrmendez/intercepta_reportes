<?php

namespace App\Filament\RichContent\Blocks;

use Carbon\CarbonInterface;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Illuminate\Support\Collection;

abstract class ReportTemplateBlock extends RichContentCustomBlock
{
    public static function toPreviewHtml(array $config): ?string
    {
        return '<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">'.e(static::getLabel()).'</div>';
    }

    protected static function emptyState(string $message = 'Sin datos para mostrar.'): string
    {
        return '<p class="muted">'.e($message).'</p>';
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, scalar|null>>  $rows
     */
    protected static function table(array $headings, array $rows): string
    {
        if ($rows === []) {
            return static::emptyState();
        }

        $html = '<table><thead><tr>';

        foreach ($headings as $heading) {
            $html .= '<th>'.e($heading).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach ($row as $cell) {
                $html .= '<td>'.e((string) ($cell ?? '-')).'</td>';
            }

            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    protected static function date(mixed $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->format('Y-m-d');
        }

        return filled($date) ? (string) $date : '-';
    }

    /**
     * @return Collection<int, mixed>
     */
    protected static function collection(array $data, string $key): Collection
    {
        return collect($data[$key] ?? []);
    }
}
