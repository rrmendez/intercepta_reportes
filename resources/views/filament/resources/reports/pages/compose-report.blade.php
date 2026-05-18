<x-filament-panels::page>
    @vite('resources/js/report-pdf-charts.js')

    <div class="space-y-8">
        <section class="space-y-3">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Visitas del periodo</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Ajusta filtros o edita celdas del periodo seleccionado.
            </p>
            @livewire(
                \App\Livewire\ReportVisitsPreviewTable::class,
                [
                    'clientId' => (int) ($this->data['client_id'] ?? 0),
                    'dateFrom' => (string) ($this->data['date_from'] ?? ''),
                    'dateUntil' => (string) ($this->data['date_until'] ?? ''),
                    'dispatchComposeRange' => true,
                    'dispatchComposeSpreadsheetFilters' => true,
                    'dispatchVisitDataStale' => true,
                ],
                key('report-visits-compose-'.($this->data['client_id'] ?? '0').'-'.($this->data['date_from'] ?? '').'-'.($this->data['date_until'] ?? '').'-'.($this->report_id ?? '0'))
            )
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Editor</h2>
            {{ $this->content }}
        </section>
    </div>

    <script>
        (function () {
            function initReportPreviewCharts() {
                var root = document.querySelector('.report-html-preview');

                if (!root || typeof window.ReportPdfCharts === 'undefined') {
                    return;
                }

                window.ReportPdfCharts.renderFromDom(root);
            }

            function attachPreviewObserver() {
                var preview = document.querySelector('.report-html-preview');

                if (!preview || !preview.parentElement || preview.parentElement.dataset.reportChartsObserver === '1') {
                    return;
                }

                preview.parentElement.dataset.reportChartsObserver = '1';

                new MutationObserver(initReportPreviewCharts).observe(preview.parentElement, {
                    childList: true,
                    subtree: true,
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                initReportPreviewCharts();
                attachPreviewObserver();
            });

            document.addEventListener('livewire:navigated', function () {
                initReportPreviewCharts();
                attachPreviewObserver();
            });

            if (typeof Livewire !== 'undefined') {
                Livewire.hook('morph.updated', initReportPreviewCharts);
            }
        })();
    </script>
</x-filament-panels::page>
