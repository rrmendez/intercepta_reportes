{{--
    Pagina de evolucion del control de fauna.
    Requiere: $graficos_evolucion_fauna (alias: $report_fauna_evolution_charts).
--}}
@php
    $chartsData = $graficos_evolucion_fauna ?? $report_fauna_evolution_charts ?? [];
    $charts = $chartsData['charts'] ?? [];
    $texto_graficos_sin_datos = $texto_graficos_sin_datos ?? 'Sin datos de visitas para graficar en este período.';
    $hasData = collect($charts)->contains(
        fn (array $chart): bool => ! empty($chart['datasets'] ?? []),
    );
@endphp
<style>
    .report-fauna-evolution-page {
        box-sizing: border-box;
        width: 100%;
        margin: 0;
        padding: 24mm 0 10mm;
        page-break-after: always;
        break-after: page;
        color: #374151;
        font-family: Arial, Helvetica, sans-serif;
    }

    .report-fauna-evolution-page .report-page-title {
        margin: 0 0 9mm;
        padding: 0;
        color: rgb(232, 177, 76);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 24pt;
        font-weight: 700;
        line-height: 1.15;
    }

    .report-fauna-evolution-page__chart {
        margin-top: 8mm;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .report-fauna-evolution-page__chart:first-of-type {
        margin-top: 0;
    }

    .report-fauna-evolution-page__chart-title {
        margin: 0 0 3mm;
        color: #374151;
        font-size: 11pt;
        font-weight: 700;
    }

    .report-fauna-evolution-page__chart-canvas-wrap {
        box-sizing: border-box;
        position: relative;
        width: 100%;
        max-width: 100%;
        height: 260px;
        min-height: 260px;
        overflow: visible;
    }

    .report-fauna-evolution-page__chart-canvas-wrap canvas {
        display: block;
        width: 100%;
        max-width: 100%;
        height: auto;
    }

    .report-fauna-evolution-page__empty {
        margin: 0;
        padding: 4mm;
        border: 1px dashed #d1d5db;
        color: #6b7280;
        font-size: 10pt;
    }
</style>

<section class="report-fauna-evolution-page" data-report-charts="1" aria-label="Evolucion del control de fauna">
    <h1 class="report-page-title">Evolución del control de fauna</h1>

    @if (! $hasData)
        <p class="report-fauna-evolution-page__empty">{{ $texto_graficos_sin_datos }}</p>
    @else
        @foreach ($charts as $chart)
            <div class="report-fauna-evolution-page__chart">
                <p class="report-fauna-evolution-page__chart-title">{{ $chart['title'] ?? 'Conteos de aves' }}</p>
                <div
                    class="report-fauna-evolution-page__chart-canvas-wrap"
                    data-report-chart-canvas-wrap
                    data-chart-height="260"
                >
                    <canvas
                        id="{{ $chart['id'] }}"
                        aria-label="{{ $chart['title'] ?? 'Grafico de conteos por tipo de ave' }}"
                    ></canvas>
                </div>
            </div>
        @endforeach
    @endif

    <script type="application/json" id="report-charts-config">@json($chartsData)</script>

    @include('pdf.partials.report-charts-init')
</section>
