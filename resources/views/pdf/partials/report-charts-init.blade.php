{{-- Requiere: elemento #report-charts-config con JSON de graficos. --}}
{!! app(\App\Services\ReportChartScriptInjector::class)->inlineBundle() !!}

<script>
    (function () {
        function renderReportCharts() {
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
                console.error('ReportPdfCharts render failed', error);
                window.__reportChartsReady = true;
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                requestAnimationFrame(renderReportCharts);
            });
        } else {
            requestAnimationFrame(renderReportCharts);
        }
    })();
</script>
