<!DOCTYPE html>
<html lang="es">
<head>
    @include('pdf.partials.single-sector-multi-bird-head')
</head>
<body>
    <section class="report-cover report-cover--page-break" lang="es">
    <div class="report-cover__header-strip">
        @if ($headerImageUrl !== null)
            <img
                class="report-cover__header-img"
                src="{{ $headerImageUrl }}"
                alt=""
            >
        @endif
    </div>

    <div class="report-cover__main">
        <h1 class="report-cover__title">Informe del servicio de control de fauna</h1>

        <div class="report-cover__brand-row">
            <div class="report-cover__brand-inner">
                <img
                    class="report-cover__logo-img"
                    src="{{ $logoPortadaUrl }}"
                    alt="Intercepta Uruguay"
                >
                <hr class="report-cover__gold-line">
            </div>
        </div>

        <p class="report-cover__tagline">CONTROL BIOLÓGICO DE FAUNA</p>
    </div>

    <hr class="report-cover__divider">
</section>

<section class="report-initial-situation-page">
    <h1 class="report-page-title">Situación inicial del predio</h1>

    <div class="report-initial-situation-page__content">
        <p>En el estudio del predio a controlar, se constató la presencia de una población significativa de paloma doméstica (Columba livia).</p>

        <p>Asimismo, se registró la presencia de otras especies de aves en el predio.</p>

        <p>La permanencia de la paloma doméstica en el predio se encuentra asociada principalmente a la disponibilidad de refugio, áreas de descanso (dormideros), sitios de nidificación y fuentes de alimento.</p>

        <p class="report-initial-situation-page__species">Especies identificadas: {{ $especies_identificadas_linea }}</p>

        <table class="report-initial-situation-page__table">
            <thead>
                <tr>
                    <th>Tipo de ave</th>
                    <th>Población Inicial</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tablaPoblacionInicialPorAve as $filaPoblacionInicial)
                    <tr>
                        <td>
                            {{ $filaPoblacionInicial['nombre_comun'] ?? '' }}@if (($filaPoblacionInicial['descripcion'] ?? '') !== '' && ($filaPoblacionInicial['descripcion'] ?? '') !== '—') (<em>{{ $filaPoblacionInicial['descripcion'] }}</em>)@endif
                        </td>
                        <td>{{ (int) ($filaPoblacionInicial['poblacion_inicial'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

    
<section class="report-objective-methodology-page">
    <h1 class="report-page-title">Objetivo y metodología</h1>

    <div class="report-objective-methodology-page__content">
        <p>El principal objetivo es disminuir la población inicial entre un 80% a un 90% en un período máximo de 3 meses.</p>

        <p>En una gran cantidad de casos se logra un control del 100% en este mismo período. De lo contrario, esta cifra puede alcanzarse en los meses siguientes.</p>

        <p>La metodología a usar es la cetrería, generalmente con esto ya es suficiente. Eventualmente se evaluará si es conveniente complementar con otros métodos (trampas específicas para palomas, drones, palos telescópicos para nidos, entre otros)</p>

        <h2 class="report-objective-methodology-page__subtitle">Registro del control de fauna</h2>

        @if ($visitRows->isNotEmpty() && $visitColumns->isNotEmpty())
            <div class="report-objective-methodology-page__table-wrap">
                <table class="report-objective-methodology-page__table">
                    <thead>
                        <tr>
                            @foreach ($visitColumns as $column)
                                <th>{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($visitRows as $row)
                            <tr>
                                @foreach ($visitColumns as $column)
                                    <td>{{ is_array($row) && array_key_exists($column['key'], $row) ? $row[$column['key']] : data_get($row, $column['key'], '') }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="report-objective-methodology-page__empty">No hay visitas para mostrar en el período seleccionado.</p>
        @endif
    </div>
</section>

    
<section class="report-fauna-evolution-page" data-report-charts="1" aria-label="Evolucion del control de fauna">
    <h1 class="report-page-title">Evolución del control de fauna</h1>

    @if (! $hasData)
        <p class="report-fauna-evolution-page__empty">Sin datos de visitas para graficar en este período.</p>
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
</section>

    
<section class="report-service-details-page" aria-label="Detalles del servicio por lugar de control">
    <h1 class="report-page-title">Detalles del servicio por lugar de control</h1>

    <table class="report-service-details-page__table">
        
        <tbody>
            <tr>
                <td>Método</td>
                <td>Cetrería y Trampa de captura viva.</td>
            </tr>
            <tr>
                <td>Especie</td>
                <td>Gavilán Mixto (Parabuteo unicinctus)</td>
            </tr>
            <tr>
                <td>Modalidad</td>
                <td>Vuelos disuasorios y eventuales capturas. Quita de nidos.</td>
            </tr>
            <tr>
                <td>Capturas</td>
                <td>0</td>
            </tr>
            <tr>
                <td>Nidos retirados</td>
                <td>0</td>
            </tr>
            <tr>
                <td>Abundancia al último día de servicio</td>
                <td>{{ $abundancia_ultimo_dia_sector }}</td>
            </tr>
        </tbody>
    </table>
</section>

    
@php
    $situacionConclusiones = $situacion_actual_y_conclusiones ?? $report_current_situation_and_conclusions ?? [];
    $populationEntriesConclusiones = $situacionConclusiones['population_entries'] ?? [];
    $reductionPercentageConclusiones = $situacionConclusiones['reduction_percentage'] ?? null;

    $partesPoblacionUltimoDiaConclusiones = collect($populationEntriesConclusiones)
        ->map(function (array $entry): string {
            $cantidad = (int) ($entry['quantity'] ?? 0);
            $nombre = trim((string) ($entry['name'] ?? ''));
            $nombreCientifico = trim((string) ($entry['scientific_name'] ?? ''));

            if ($nombre === '') {
                return '';
            }

            if ($nombreCientifico !== '' && $nombreCientifico !== '—') {
                return "{$cantidad} {$nombre} (<em>{$nombreCientifico}</em>)";
            }

            return "{$cantidad} {$nombre}";
        })
        ->filter(fn (string $parte): bool => $parte !== '')
        ->values();

    $poblacionUltimoDiaLineaConclusiones = match (true) {
        $partesPoblacionUltimoDiaConclusiones->isEmpty() => '—',
        $partesPoblacionUltimoDiaConclusiones->count() === 1 => $partesPoblacionUltimoDiaConclusiones->first().'.',
        default => $partesPoblacionUltimoDiaConclusiones->slice(0, -1)->implode(', ').' y '.$partesPoblacionUltimoDiaConclusiones->last().'.',
    };
@endphp

<section class="report-current-situation-and-conclusions-page" aria-label="Situacion actual y conclusiones">
    <h1 class="report-page-title">Situación actual y conclusiones</h1>

    <div class="report-current-situation-and-conclusions-page__content">
        <p>
            <span class="report-current-situation-and-conclusions-page__highlight">Población al último día de servicio:</span>
            {!! $poblacionUltimoDiaLineaConclusiones !!}
        </p>

        <p>Se realizó un control biológico en la totalidad del predio, utilizando la cetrería como método principal.</p>

        <p>Asimismo, se trabajó sobre los factores que podrían estar favoreciendo la presencia de las especies problema, con el objetivo de generar un entorno inseguro para las mismas y promover su migración hacia otros sectores.</p>

        @if ($reductionPercentageConclusiones !== null)
            <p>La población de aves ha disminuido en un {{ $reductionPercentageConclusiones }}%.</p>
        @endif

        <p>Capturas con cetrería o métodos alternativos: 0 Palomas domésticas (<em>Columba livia</em>)</p>

        <p>Capturas con trampas: 0 Palomas domésticas (<em>Columba livia</em>).</p>

        <p>Se retiraron 0 nidos.</p>

        <div class="report-current-situation-and-conclusions-page__footer">
            <div class="report-current-situation-and-conclusions-page__conclusion">
                <p>En vista de los resultados alcanzados, creemos que el plan de trabajo implantado está siendo exitoso.</p>
            </div>

            <div class="report-current-situation-and-conclusions-page__signature-wrap">
                <img
                    class="report-current-situation-and-conclusions-page__signature-image"
                    src="{{ $signatureSrc }}"
                    alt="Firma de Manuel Maier"
                >
                <p class="report-current-situation-and-conclusions-page__technician-name">Manuel Maier</p>
            </div>
        </div>
    </div>
</section>

    
<section class="report-contact-page" lang="es" aria-label="Contacto">
    <div class="report-contact-page__chrome-mask" aria-hidden="true"></div>

    <div class="report-contact-page__layout">
        <div class="report-contact-page__center">
            <div class="report-contact-page__center-inner">
                <img
                    class="report-contact-page__logo"
                    src="{{ $logoSrc }}"
                    alt="Intercepta Uruguay"
                >

                <div class="report-contact-page__details">
                    <h2 class="report-contact-page__heading">CONTACTO</h2>
                    <p class="report-contact-page__link">interceptauruguay.com.uy</p>
                    <p class="report-contact-page__link">mmaier@interceptauruguay.com.uy</p>
                    <p class="report-contact-page__phone">Cel.: 094 421 287</p>
                    <p class="report-contact-page__phone">Ext.: (+598) 94 421 287</p>
                </div>
            </div>
        </div>
    </div>
</section>

    <div id="report-pdf-fixed-footer-root" class="report-pdf-fixed-footer__bar" lang="es" role="contentinfo" aria-label="Pie de informe">
    <div class="report-pdf-fixed-footer__inner">
        <div class="report-pdf-fixed-footer__meta">
            <dl>
                <dt>Cliente</dt>
                <dd>
                    <span class="report-pdf-fixed-footer__client-name">{{ $client->name }}</span>@if ($clientAddressLine !== '')
                        <span class="report-pdf-fixed-footer__client-address">, {{ $clientAddressLine }}</span>
                    @endif
                </dd>
                <dt>Período</dt>
                <dd>{{ $period_label }}</dd>
                <dt>Informe Nº</dt>
                <dd>{{ $reportNumberLabel }}</dd>
            </dl>
        </div>
        <div class="report-pdf-fixed-footer__logos">
            @if ($birdlifeLogoUrl !== null)
                <div class="report-pdf-fixed-footer__birdlife-slot">
                    <img
                        src="{{ $birdlifeLogoUrl }}"
                        alt="BirdLife International"
                        class="report-pdf-fixed-footer__footer-logo"
                    >
                </div>
            @else
                <div class="report-pdf-fixed-footer__birdlife-slot">
                    <span class="report-pdf-fixed-footer__birdlife-missing" role="img" aria-label="BirdLife International">BirdLife</span>
                </div>
            @endif

            @if ($aucLogoUrl !== null)
                <div class="report-pdf-fixed-footer__auc-slot">
                    <img
                        src="{{ $aucLogoUrl }}"
                        alt="Asociación Uruguaya de Cetrería y Derecho Ambiental"
                        class="report-pdf-fixed-footer__footer-logo"
                    >
                </div>
            @else
                <div class="report-pdf-fixed-footer__auc-slot">
                    <span class="report-pdf-fixed-footer__auc-missing" title="public/images/auc.svg">AUC</span>
                </div>
            @endif
        </div>
    </div>
</div>
</body>
</html>