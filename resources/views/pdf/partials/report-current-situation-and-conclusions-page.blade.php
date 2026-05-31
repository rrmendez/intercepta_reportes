{{--
    Situacion actual y conclusiones del informe (pagina unica).
    Requiere: $situacion_actual_y_conclusiones (alias: $report_current_situation_and_conclusions).
--}}
@php
    $data = $situacion_actual_y_conclusiones ?? $report_current_situation_and_conclusions ?? [];
    $populationEntries = $data['population_entries'] ?? [];
    $reductionPercentage = $data['reduction_percentage'] ?? null;
    $falconryCaptures = $data['falconry_captures'] ?? [];
    $trapCaptures = $data['trap_captures'] ?? [];
    $nestsRemoved = (int) ($data['nests_removed'] ?? 0);

    $texto_etiqueta_poblacion_ultimo_dia = $texto_etiqueta_poblacion_ultimo_dia ?? 'Población al último día de servicio:';
    $texto_control_biologico_metodo = $texto_control_biologico_metodo ?? 'Se realizó un control biológico en la totalidad del predio, utilizando la cetrería como método principal.';
    $texto_trabajo_factores_presencia = $texto_trabajo_factores_presencia ?? 'Asimismo, se trabajó sobre los factores que podrían estar favoreciendo la presencia de las especies problema, con el objetivo de generar un entorno inseguro para las mismas y promover su migración hacia otros sectores.';
    $texto_plantilla_reduccion_poblacion = $texto_plantilla_reduccion_poblacion ?? 'La población de aves ha disminuido en un :porcentaje%.';
    $texto_etiqueta_capturas_cetreria = $texto_etiqueta_capturas_cetreria ?? 'Capturas con cetrería o métodos alternativos:';
    $texto_etiqueta_capturas_trampas = $texto_etiqueta_capturas_trampas ?? 'Capturas con trampas:';
    $texto_plantilla_nidos_retirados = $texto_plantilla_nidos_retirados ?? 'Se retiraron :cantidad nidos.';
    $texto_conclusion = $texto_conclusion ?? '';

    $signatureRelativePath = 'images/firma_manuel.jpeg';
    $signatureSrc = \App\Services\ReportPdfPublicImageDataUri::fromRelativePublicPath($signatureRelativePath)
        ?? ($url_imagen_firma ?? asset($signatureRelativePath));

    $textoReduccion = $reductionPercentage !== null
        ? str_replace(':porcentaje', (string) $reductionPercentage, $texto_plantilla_reduccion_poblacion)
        : null;

    $textoNidosRetirados = str_replace(':cantidad', (string) $nestsRemoved, $texto_plantilla_nidos_retirados);
@endphp
<style>
    .report-current-situation-and-conclusions-page {
        box-sizing: border-box;
        width: 100%;
        min-height: 257mm;
        margin: 0;
        padding: 24mm 0 10mm;
        page-break-after: always;
        break-after: page;
        color: #374151;
        font-family: Arial, Helvetica, sans-serif;
    }

    .report-current-situation-and-conclusions-page .report-page-title {
        margin: 0 0 9mm;
        color: rgb(232, 177, 76);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 24pt;
        font-weight: 700;
        line-height: 1.15;
    }

    .report-current-situation-and-conclusions-page__content {
        max-width: 100%;
        font-size: 11pt;
        line-height: 1.55;
    }

    .report-current-situation-and-conclusions-page__content p {
        margin: 0 0 5mm;
    }

    .report-current-situation-and-conclusions-page__highlight {
        font-weight: 700;
    }

    .report-current-situation-and-conclusions-page__footer {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 10mm;
        margin-top: 8mm;
    }

    .report-current-situation-and-conclusions-page__conclusion {
        flex: 1 1 auto;
        max-width: 62%;
    }

    .report-current-situation-and-conclusions-page__signature-wrap {
        flex: 0 0 auto;
        text-align: right;
    }

    .report-current-situation-and-conclusions-page__signature-image {
        display: block;
        width: auto;
        max-width: 52mm;
        max-height: 24mm;
        margin-left: auto;
    }

    .report-current-situation-and-conclusions-page__technician-name {
        margin: 2mm 0 0;
        color: #6b7280;
        font-size: 10pt;
        font-weight: 400;
        text-align: right;
    }
</style>

<section class="report-current-situation-and-conclusions-page" aria-label="Situacion actual y conclusiones">
    <h1 class="report-page-title">Situación actual y conclusiones</h1>

    <div class="report-current-situation-and-conclusions-page__content">
        <p>
            <span class="report-current-situation-and-conclusions-page__highlight">{{ $texto_etiqueta_poblacion_ultimo_dia }}</span>
            @forelse ($populationEntries as $entry)
                {{ $entry['quantity'] ?? 0 }} {{ $entry['name'] ?? '' }}@if ($loop->last).@else @endif
            @empty
                —
            @endforelse
        </p>

        @if ($texto_control_biologico_metodo !== '')
            <p>{{ $texto_control_biologico_metodo }}</p>
        @endif

        @if ($texto_trabajo_factores_presencia !== '')
            <p>{{ $texto_trabajo_factores_presencia }}</p>
        @endif

        @if ($textoReduccion !== null)
            <p>{{ $textoReduccion }}</p>
        @endif

        <p>
            {{ $texto_etiqueta_capturas_cetreria }}
            @forelse ($falconryCaptures as $capture)
                {{ $capture['quantity'] ?? 0 }} {{ $capture['name'] ?? '' }}
                @if (! empty($capture['scientific_name']))
                    (<em>{{ $capture['scientific_name'] }}</em>)
                @endif
            @empty
                —
            @endforelse
        </p>

        <p>
            {{ $texto_etiqueta_capturas_trampas }}
            @forelse ($trapCaptures as $capture)
                {{ $capture['quantity'] ?? 0 }} {{ $capture['name'] ?? '' }}
                @if (! empty($capture['scientific_name']))
                    (<em>{{ $capture['scientific_name'] }}</em>)
                @endif
                @if ($loop->last).@endif
            @empty
                —
            @endforelse
        </p>

        <p>{{ $textoNidosRetirados }}</p>

        <div class="report-current-situation-and-conclusions-page__footer">
            <div class="report-current-situation-and-conclusions-page__conclusion">
                @if ($texto_conclusion !== '')
                    <p>{{ $texto_conclusion }}</p>
                @endif
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
