<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Referencia legible de las variables disponibles en plantillas Blade del informe PDF.
 */
final class ReportBladeVariableReference
{
    public function __construct(
        private readonly ReportPdfTemplateVariables $templateVariables,
    ) {}

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
     * @return list<array{name: string, type: string, summary: string, categoria: string}>
     */
    public function rows(array $bladeData): array
    {
        $definiciones = $this->templateVariables->definiciones();
        $rows = [];

        uasort($definiciones, fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);

        foreach ($definiciones as $key => $definicion) {
            if (! array_key_exists($key, $bladeData)) {
                continue;
            }

            $rows[] = [
                'name' => '$'.$key,
                'type' => $definicion['tipo'],
                'summary' => $this->resumenConValor($key, $bladeData[$key], $definicion['resumen']),
                'categoria' => $definicion['categoria'],
            ];
        }

        foreach (array_keys($bladeData) as $key) {
            if (array_key_exists($key, $definiciones)) {
                continue;
            }

            if (str_starts_with($key, 'report_') || in_array($key, [
                'client', 'report', 'period_label', 'visits', 'visit_columns', 'visit_reports',
                'visits_count', 'total_quantity', 'totals_by_bird_type', 'totals_by_location',
                'dateFrom', 'dateUntil', 'aggregations', 'coverHeaderImageUrl',
            ], true)) {
                continue;
            }

            $rows[] = [
                'name' => '$'.$key,
                'type' => $this->tipoLegible($bladeData[$key]),
                'summary' => $this->resumenGenerico($bladeData[$key]),
                'categoria' => 'Otras',
            ];
        }

        return $rows;
    }

    private function resumenConValor(string $key, mixed $value, string $descripcion): string
    {
        $muestra = match ($key) {
            'cliente' => is_object($value) && property_exists($value, 'name')
                ? 'Valor actual: '.(string) $value->name
                : null,
            'nombre_cliente', 'direccion_cliente', 'etiqueta_periodo',
            'fecha_desde_texto', 'fecha_hasta_texto', 'fecha_generacion_texto',
            'texto_especies_identificadas' => is_string($value) && $value !== ''
                ? 'Valor actual: '.$this->truncar($value)
                : null,
            'informe' => is_object($value) && method_exists($value, 'getAttribute')
                ? 'Valor actual: '.$value->getAttribute('date_from').' — '.$value->getAttribute('date_until')
                : null,
            'cantidad_visitas', 'total_observaciones', 'total_cantidad' => is_numeric($value)
                ? 'Valor actual: '.$value
                : null,
            'visitas' => is_array($value)
                ? 'Valor actual: '.count($value).' fila(s) en la tabla de visitas.'
                : null,
            'columnas_visitas' => is_array($value)
                ? 'Valor actual: '.count($value).' columna(s).'
                : null,
            'registros_visita' => $value instanceof Collection
                ? 'Valor actual: '.$value->count().' registro(s) de detalle.'
                : null,
            'totales_por_tipo_ave', 'totales_por_ubicacion', 'agregaciones' => is_array($value)
                ? 'Valor actual: '.count($value).' entrada(s).'
                : null,
            'graficos_lineas_periodo', 'graficos_evolucion_fauna' => is_array($value) && isset($value['charts'])
                ? 'Valor actual: '.count($value['charts']).' gráfico(s).'
                : null,
            'detalle_servicio_por_ubicacion' => is_array($value) && isset($value['sections'])
                ? 'Valor actual: '.count($value['sections']).' sección(es) por ubicación.'
                : null,
            'especies_identificadas_tabla' => is_array($value)
                ? 'Valor actual: '.count($value).' especie(s) en la tabla.'
                : null,
            'tabla_poblacion_inicial_por_ave' => is_array($value)
                ? 'Valor actual: '.count($value).' fila(s) de población inicial.'
                : null,
            'poblacion_inicial' => is_numeric($value)
                ? 'Valor actual: '.$value
                : null,
            'nombre_sector', 'abundancia_ultimo_dia_sector' => is_string($value) && $value !== ''
                ? 'Valor actual: '.$value
                : null,
            default => is_string($value) && $value !== '' && strlen($value) <= 80
                ? 'Valor actual: '.$value
                : null,
        };

        if ($muestra === null) {
            return $descripcion;
        }

        return $descripcion.' '.$muestra;
    }

    private function tipoLegible(mixed $value): string
    {
        if ($value instanceof Collection) {
            return 'Lista de datos';
        }

        if (is_array($value)) {
            $first = $value[array_key_first($value) ?? ''] ?? null;

            if (is_array($first) && array_key_exists('visit_date_init', $first)) {
                return 'Tabla de visitas';
            }

            return 'Lista';
        }

        if ($value instanceof \DateTimeInterface) {
            return 'Fecha';
        }

        if (is_int($value) || is_float($value)) {
            return 'Número';
        }

        if (is_string($value)) {
            return 'Texto';
        }

        if (is_object($value)) {
            return 'Datos del sistema';
        }

        return 'Valor';
    }

    private function resumenGenerico(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return 'Valor actual: '.$value->format('d/m/Y H:i');
        }

        if (is_string($value)) {
            return $this->truncar($value);
        }

        if (is_array($value) || $value instanceof Collection) {
            $count = is_array($value) ? count($value) : $value->count();

            return 'Contiene '.$count.' elemento(s).';
        }

        return 'Variable disponible en la plantilla.';
    }

    private function truncar(string $value): string
    {
        return Str::length($value) > 120 ? Str::limit($value, 120) : $value;
    }
}
