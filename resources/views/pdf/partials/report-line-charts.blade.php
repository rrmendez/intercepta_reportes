{{--
    Dos graficos de lineas (Chart.js) para informe PDF y vista previa.
    Requiere: $report_line_charts (array con clave charts).
--}}
@php
    $charts = $report_line_charts['charts'] ?? [];
    $hasData = collect($charts)->contains(
        fn (array $chart): bool => ! empty($chart['datasets'] ?? []),
    );
@endphp
<section
    class="section report-line-charts"
    data-report-charts="1"
    aria-label="Graficos del periodo"
>
    <h2>Evolucion de cantidades</h2>

    @if (! $hasData)
        <p class="muted">Sin datos de visitas para graficar en este periodo.</p>
    @else
        @foreach ($charts as $chart)
            <div class="report-line-charts__chart" style="margin-top: 16px;">
                <canvas
                    id="{{ $chart['id'] }}"
                    width="700"
                    height="280"
                    style="display: block; width: 100%; max-width: 700px; height: auto;"
                    aria-label="{{ $chart['title'] ?? 'Grafico' }}"
                ></canvas>
            </div>
        @endforeach
    @endif

    <script type="application/json" id="report-charts-config">@json($report_line_charts)</script>

    {!! app(\App\Services\ReportChartScriptInjector::class)->inlineBundle() !!}

    <script>
        (function () {
            window.__reportChartsReady = false;

            if (typeof window.ReportPdfCharts === 'undefined') {
                window.__reportChartsReady = true;

                return;
            }

            var configElement = document.getElementById('report-charts-config');

            if (!configElement) {
                window.__reportChartsReady = true;

                return;
            }

            try {
                window.ReportPdfCharts.render(JSON.parse(configElement.textContent || '{}'));
            } catch (error) {
                window.__reportChartsReady = true;
            }
        })();
    </script>
</section>
