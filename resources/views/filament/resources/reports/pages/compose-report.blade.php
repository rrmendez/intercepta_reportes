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
                key('report-visits-compose-'.($this->data['client_id'] ?? '0').'-'.($this->report_id ?? '0'))
            )
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Editor</h2>
            {{ $this->content }}
        </section>
    </div>

    <script>
        (function () {
            var previewChartsObserver = null;
            var previewVisibilityObserver = null;

            function getPreviewRoot() {
                return document.querySelector('.report-html-preview');
            }

            function initReportPreviewCharts() {
                var root = getPreviewRoot();

                if (!root || typeof window.ReportPdfCharts === 'undefined') {
                    return;
                }

                var configElement = root.querySelector('#report-charts-config');

                if (!configElement) {
                    return;
                }

                try {
                    window.ReportPdfCharts.render(JSON.parse(configElement.textContent || '{}'));
                } catch (error) {
                    console.error('Report preview charts failed', error);
                }
            }

            function schedulePreviewCharts() {
                window.requestAnimationFrame(function () {
                    window.setTimeout(initReportPreviewCharts, 80);
                });
            }

            function attachPreviewRevisionObserver() {
                var root = getPreviewRoot();

                if (!root || root.dataset.revisionWatcher === '1') {
                    return;
                }

                root.dataset.revisionWatcher = '1';

                new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        if (
                            mutation.type === 'attributes'
                            && mutation.attributeName === 'data-report-preview-revision'
                        ) {
                            schedulePreviewCharts();
                        }
                    });
                }).observe(root, {
                    attributes: true,
                    attributeFilter: ['data-report-preview-revision'],
                });
            }

            function attachPreviewDomObserver() {
                var root = getPreviewRoot();

                if (!root || !root.parentElement || root.parentElement.dataset.reportChartsObserver === '1') {
                    return;
                }

                root.parentElement.dataset.reportChartsObserver = '1';

                previewChartsObserver = new MutationObserver(function () {
                    attachPreviewRevisionObserver();
                    attachPreviewVisibilityObserver();
                    schedulePreviewCharts();
                });

                previewChartsObserver.observe(root.parentElement, {
                    childList: true,
                    subtree: true,
                });
            }

            function attachPreviewVisibilityObserver() {
                var root = getPreviewRoot();

                if (!root) {
                    return;
                }

                if (previewVisibilityObserver) {
                    previewVisibilityObserver.disconnect();
                    previewVisibilityObserver = null;
                }

                previewVisibilityObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            schedulePreviewCharts();
                        }
                    });
                }, { threshold: 0.12 });

                previewVisibilityObserver.observe(root);
            }

            function attachTabActivationHandler() {
                document.addEventListener('click', function (event) {
                    var tab = event.target.closest('[role="tab"]');

                    if (!tab) {
                        return;
                    }

                    var label = (tab.textContent || '').toLowerCase();

                    if (label.includes('vista previa') || label.includes('preview')) {
                        schedulePreviewCharts();
                    }
                });
            }

            function bootPreviewCharts() {
                attachPreviewDomObserver();
                attachPreviewRevisionObserver();
                attachPreviewVisibilityObserver();
                attachTabActivationHandler();
                schedulePreviewCharts();
            }

            document.addEventListener('DOMContentLoaded', bootPreviewCharts);
            document.addEventListener('livewire:navigated', bootPreviewCharts);

            document.addEventListener('livewire:init', function () {
                Livewire.hook('commit', function (_ref) {
                    var succeed = _ref.succeed;

                    succeed(function () {
                        schedulePreviewCharts();
                    });
                });

                Livewire.hook('morph.updated', schedulePreviewCharts);
            });
        })();
    </script>
</x-filament-panels::page>
