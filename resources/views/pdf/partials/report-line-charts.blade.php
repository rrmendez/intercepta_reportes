{{--
    Dos graficos de lineas (Chart.js) para informe PDF y vista previa.
    Requiere: $graficos_lineas_periodo (alias: $report_line_charts).
--}}
@php
    $chartsData = $graficos_lineas_periodo ?? $report_line_charts ?? [];
    $charts = $chartsData['charts'] ?? [];
    $texto_graficos_sin_datos = $texto_graficos_sin_datos ?? 'Sin datos de visitas para graficar en este período.';
    $hasData = collect($charts)->contains(
        fn (array $chart): bool => ! empty($chart['datasets'] ?? []),
    );
@endphp
<section
    class="section report-line-charts"
    data-report-charts="1"
    aria-label="Graficos del periodo"
>
    <h2>Evolución de cantidades</h2>

    @if (! $hasData)
        <p class="muted">{{ $texto_graficos_sin_datos }}</p>
    @else
        @foreach ($charts as $chart)
            <div
                class="report-line-charts__chart"
                data-report-chart-canvas-wrap
                data-chart-height="260"
                style="margin-top: 16px; position: relative; width: 100%; height: 260px; min-height: 260px;"
            >
                <canvas
                    id="{{ $chart['id'] }}"
                    aria-label="{{ $chart['title'] ?? 'Grafico' }}"
                ></canvas>
            </div>
        @endforeach
    @endif

    <script type="application/json" id="report-charts-config">@json($chartsData)</script>

    @include('pdf.partials.report-charts-init')
</section>
