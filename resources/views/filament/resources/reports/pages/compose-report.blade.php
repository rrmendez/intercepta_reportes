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

            var inlineEditMenu = null;
            var inlineEditPayload = null;

            function getInlineEditMenu() {
                if (inlineEditMenu) {
                    return inlineEditMenu;
                }

                inlineEditMenu = document.createElement('button');
                inlineEditMenu.type = 'button';
                inlineEditMenu.className = 'report-inline-edit-menu';
                inlineEditMenu.textContent = '✏️ Editar';
                inlineEditMenu.style.cssText = [
                    'position:fixed',
                    'z-index:9999',
                    'display:none',
                    'padding:4px 10px',
                    'font-size:12px',
                    'font-weight:600',
                    'color:#fff',
                    'background:#d4a012',
                    'border:0',
                    'border-radius:6px',
                    'box-shadow:0 2px 8px rgba(0,0,0,.25)',
                    'cursor:pointer',
                ].join(';');

                inlineEditMenu.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });

                inlineEditMenu.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    openInlineEditor();
                });

                document.body.appendChild(inlineEditMenu);

                return inlineEditMenu;
            }

            function hideInlineEditMenu() {
                if (inlineEditMenu) {
                    inlineEditMenu.style.display = 'none';
                }

                inlineEditPayload = null;
            }

            function closestPreviewRoot(node) {
                var el = node && node.nodeType === 1 ? node : (node ? node.parentElement : null);

                return el && typeof el.closest === 'function'
                    ? el.closest('.report-html-preview')
                    : null;
            }

            function selectionContext(range, root) {
                try {
                    var beforeRange = document.createRange();
                    beforeRange.selectNodeContents(root);
                    beforeRange.setEnd(range.startContainer, range.startOffset);

                    var afterRange = document.createRange();
                    afterRange.selectNodeContents(root);
                    afterRange.setStart(range.endContainer, range.endOffset);

                    return {
                        before: beforeRange.toString().slice(-400),
                        after: afterRange.toString().slice(0, 400),
                    };
                } catch (error) {
                    return { before: '', after: '' };
                }
            }

            function maybeShowInlineEditMenu() {
                var selection = window.getSelection();

                if (!selection || selection.isCollapsed || selection.rangeCount === 0) {
                    hideInlineEditMenu();

                    return;
                }

                var selectedText = selection.toString().replace(/\s+/g, ' ').trim();
                var range = selection.getRangeAt(0);
                var root = closestPreviewRoot(range.commonAncestorContainer)
                    || closestPreviewRoot(range.startContainer);

                if (!root || selectedText.length < 1) {
                    hideInlineEditMenu();

                    return;
                }

                var context = selectionContext(range, root);

                inlineEditPayload = {
                    originalText: selectedText,
                    before: context.before,
                    after: context.after,
                };

                var rect = range.getBoundingClientRect();
                var menu = getInlineEditMenu();

                menu.style.display = 'block';

                var top = rect.top > 44 ? rect.top - 36 : rect.bottom + 8;
                menu.style.top = Math.max(8, top) + 'px';
                menu.style.left = Math.max(8, rect.left) + 'px';
            }

            function openInlineEditor() {
                if (!inlineEditPayload || typeof window.Livewire === 'undefined') {
                    return;
                }

                window.Livewire.dispatch('compose-edit-inline', inlineEditPayload);
                hideInlineEditMenu();

                var selection = window.getSelection();

                if (selection) {
                    selection.removeAllRanges();
                }
            }

            function attachInlineEditHandlers() {
                if (document.body.dataset.inlineEditHandlers === '1') {
                    return;
                }

                document.body.dataset.inlineEditHandlers = '1';

                document.addEventListener('mouseup', function (event) {
                    if (inlineEditMenu && inlineEditMenu.contains(event.target)) {
                        return;
                    }

                    window.setTimeout(maybeShowInlineEditMenu, 0);
                });

                document.addEventListener('mousedown', function (event) {
                    if (inlineEditMenu && inlineEditMenu.contains(event.target)) {
                        return;
                    }

                    hideInlineEditMenu();
                });

                document.addEventListener('scroll', hideInlineEditMenu, true);
            }

            function bootPreviewCharts() {
                attachPreviewDomObserver();
                attachPreviewRevisionObserver();
                attachPreviewVisibilityObserver();
                attachTabActivationHandler();
                attachInlineEditHandlers();
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
