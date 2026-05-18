<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\Report;
use App\Models\Visit;
use App\Models\VisitReport;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Human-readable reference for variables passed to PDF Blade templates.
 */
final class ReportBladeVariableReference
{
    /**
     * @param  array<string, mixed>  $bladeData
     */
    public function toHtml(array $bladeData, ?string $visitsContextNote = null): Htmlable
    {
        return new HtmlString(view('components.report-blade-variables-table', [
            'rows' => $this->rows($bladeData),
            'visitsContextNote' => $visitsContextNote,
        ])->render());
    }

    /**
     * @param  array<string, mixed>  $bladeData
     * @return list<array{name: string, type: string, summary: string}>
     */
    public function rows(array $bladeData): array
    {
        $ordered = [
            'client',
            'report',
            'period_label',
            'visits',
            'visit_columns',
            'visit_reports',
            'visits_count',
            'total_observations',
            'total_quantity',
            'totals_by_bird_type',
            'totals_by_location',
            'dateFrom',
            'dateUntil',
            'aggregations',
            'report_line_charts',
        ];

        $rows = [];

        foreach ($ordered as $key) {
            if (! array_key_exists($key, $bladeData)) {
                continue;
            }

            $rows[] = [
                'name' => '$'.$key,
                'type' => $this->typeLabel($bladeData[$key]),
                'summary' => $this->summarize($key, $bladeData[$key]),
            ];
        }

        foreach (array_keys($bladeData) as $key) {
            if (in_array($key, $ordered, true)) {
                continue;
            }

            $rows[] = [
                'name' => '$'.$key,
                'type' => $this->typeLabel($bladeData[$key]),
                'summary' => $this->summarize($key, $bladeData[$key]),
            ];
        }

        return $rows;
    }

    private function typeLabel(mixed $value): string
    {
        if ($value instanceof Collection) {
            $class = $value->isEmpty()
                ? 'mixed'
                : ($value->first() instanceof Model ? $value->first()::class : 'mixed');

            return 'Collection ('.$class.')';
        }

        if ($value instanceof Model) {
            return $value::class;
        }

        if (is_array($value)) {
            $first = $value[array_key_first($value) ?? ''] ?? null;

            if (is_array($first) && array_key_exists('visit_date_init', $first)) {
                return 'array (filas tabla visitas)';
            }

            return 'array';
        }

        if (is_bool($value)) {
            return 'bool';
        }

        if (is_int($value)) {
            return 'int';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_object($value)) {
            return $value::class;
        }

        if ($value === null) {
            return 'null';
        }

        return get_debug_type($value);
    }

    private function summarize(string $key, mixed $value): string
    {
        return match ($key) {
            'client' => $value instanceof Client ? (string) $value->name : $this->genericSummary($value),
            'report' => $value instanceof Report
                ? 'Rango '.$value->date_from?->toDateString().' — '.$value->date_until?->toDateString()
                : $this->genericSummary($value),
            'visits' => is_array($value)
                ? count($value).' fila(s); mismas claves que la tabla (p. ej. visit_date_init, visit_observation, qty_{ubicacion}_{ave}, employee.name).'
                : ($value instanceof Collection
                    ? $value->count().' visita(s).'
                    : $this->genericSummary($value)),
            'visit_columns' => is_array($value)
                ? count($value).' columna(s); mismas etiquetas y orden que la tabla de visitas.'
                : $this->genericSummary($value),
            'visit_reports' => $value instanceof Collection
                ? $value->count().' linea(s) de detalle (VisitReport).'
                : $this->genericSummary($value),
            'totals_by_bird_type', 'totals_by_location', 'aggregations' => is_array($value)
                ? count($value).' clave(s).'
                : $this->genericSummary($value),
            'report_line_charts' => is_array($value) && isset($value['charts'])
                ? count($value['charts']).' grafico(s) de lineas (Chart.js); clave charts con id, title, labels y datasets.'
                : $this->genericSummary($value),
            default => $this->genericSummary($value),
        };
    }

    private function genericSummary(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof Collection) {
            $sample = $value->first();

            if ($sample instanceof Visit) {
                return $value->count().' modelo(s) Visit.';
            }

            if ($sample instanceof VisitReport) {
                return $value->count().' modelo(s) VisitReport.';
            }

            return $value->count().' elemento(s).';
        }

        if (is_string($value)) {
            return Str::length($value) > 120
                ? Str::limit($value, 120)
                : $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?: '';
    }
}
