<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->content }}

        @php
            $period = $this->getPeriodPreview();
        @endphp

        @if ($period !== null)
            <section class="space-y-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Variables de la plantilla</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Nombres y tipos de las variables disponibles en Blade para este cliente y rango.
                    </p>
                </div>

                <div class="fi-prose max-w-none rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    {!! $this->getTemplateVariablesHtml() !!}
                </div>
            </section>

            <section class="space-y-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Visitas del reporte</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $period['period_label'] }} · {{ $period['visits']->count() }} visitas
                    </p>
                </div>

                @include('filament.resources.reports.pages.visits-preview-table', [
                    'clientId' => (int) $period['client']->getKey(),
                    'dateFrom' => $period['date_from']->toDateString(),
                    'dateUntil' => $period['date_until']->toDateString(),
                ])
            </section>
        @endif

        <section class="space-y-3">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Previsualizacion de plantilla</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Los bloques se renderizan con las visitas filtradas para este cliente y rango.
                </p>
            </div>

            <div class="fi-prose max-w-none rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                {!! $this->getTemplatePreviewHtml() !!}
            </div>
        </section>

        <div class="flex justify-end">
            <x-filament::button
                type="submit"
                form-id="report-create-form"
            >
                Generar PDF
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
