{{--
    Pie fijo en cada hoja del PDF.

    Vista previa HTML / panel: estilos + bloque con `position: fixed` en @media print.

    PDF generado (Chromium): el pie se pinta con el `footerTemplate` del motor (Browsershot::footerHtml),
    ancho completo de pagina — ver `pdf.partials.report-pdf-chrome-footer-template` y
    `ReportPdfDocumentHtml::withoutEmbeddedFixedFooter()`.

    Incluir desde la plantilla Blade del informe, antes de </body>, por ejemplo:
    @include('pdf.partials.report-pdf-fixed-footer', ['client' => $client, 'report' => $report, 'period_label' => $period_label])

    Requiere: $client (Client), $report (?Report), $period_label (string).
    Opcional: $footerPartnerBirdlifeUrl, $footerPartnerAucUrl — URLs absolutas; si no, public/images/birdlife.png y public/images/auc.svg.
--}}
@php
    $resolvePublicAsset = static function (string $relativePublicPath): ?string {
        $path = public_path($relativePublicPath);

        return is_string($path) && $path !== '' && is_file($path) ? asset($relativePublicPath) : null;
    };

    $birdlifeLogoUrl = $footerPartnerBirdlifeUrl ?? $resolvePublicAsset('images/birdlife.png');
    $aucLogoUrl = $footerPartnerAucUrl ?? $resolvePublicAsset('images/auc.svg');
    $reportNumberLabel = ($report !== null && $report->getKey() !== null)
        ? (string) $report->getKey()
        : '—';
    $clientAddressLine = trim((string) ($client->address ?? ''));
    $reportPdfMarginsMm = max(0, min(40, (int) config('services.report_pdf.margins_mm', 12)));
@endphp
<style id="report-pdf-fixed-footer-styles">
    {{--
        En pantalla (vista previa Filament/Livewire) NO usar position:fixed en body:
        afecta todo el documento del panel. En @media print (PDF Chromium) el pie va fijo al pie de cada hoja.
        Fondo blanco del documento y franja gris del pie: forzar en print con print-color-adjust (Chromium/PDF).
    --}}
    :root {
        --report-pdf-footer-height: 34mm;
        --report-pdf-footer-logo-max-height: 26mm;
        --report-pdf-footer-logo-max-width: 58mm;
        --report-pdf-outer-margin: {{ $reportPdfMarginsMm }}mm;
    }

    .report-pdf-fixed-footer__bar {
        position: relative;
        left: auto;
        right: auto;
        bottom: auto;
        z-index: auto;
        box-sizing: border-box;
        width: 100%;
        margin-top: 4mm;
        min-height: auto;
        padding: 1.75mm 5mm 2mm;
        background: #cfd4de;
        background-color: #cfd4de;
        border: none !important;
        border-top: 1px solid #b8c0cc !important;
        outline: none !important;
        box-shadow: none !important;
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        font-size: 8pt;
        line-height: 1.35;
        color: #374151;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    @media print {
        html {
            background: #ffffff !important;
            overflow-x: visible !important;
        }

        body {
            background: #ffffff !important;
            padding-bottom: var(--report-pdf-footer-height) !important;
            box-sizing: border-box;
            overflow-x: visible !important;
        }

        /*
            Chromium PDF: a izquierda/derecha el pie coincide con el borde del area util; el margen
            blanco del PDF se ve como marco. Sangrar solo en horizontal (mismo mm que margenes
            laterales en `PdfHtmlToBinaryConverter`). Abajo no se sangra: margen inferior del PDF
            en 0 (`bottom_margin_mm`) evita franja y no desplaza el bloque hacia la siguiente hoja.
        */
        .report-pdf-fixed-footer__bar {
            position: fixed;
            left: calc(0mm - var(--report-pdf-outer-margin)) !important;
            right: calc(0mm - var(--report-pdf-outer-margin)) !important;
            bottom: 0 !important;
            z-index: 1000;
            width: auto !important;
            margin-top: 0;
            min-height: var(--report-pdf-footer-height);
            padding-top: 1.75mm !important;
            padding-left: calc(5mm + var(--report-pdf-outer-margin)) !important;
            padding-right: calc(5mm + var(--report-pdf-outer-margin)) !important;
            padding-bottom: 2mm !important;
            background: #cfd4de !important;
            background-color: #cfd4de !important;
            border: none !important;
            border-top: 1px solid #b8c0cc !important;
            outline: none !important;
            box-shadow: none !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }

    .report-pdf-fixed-footer__inner {
        display: flex;
        flex-wrap: nowrap;
        align-items: stretch;
        justify-content: flex-start;
        gap: 3mm 4mm;
        max-width: 100%;
        min-height: calc(var(--report-pdf-footer-logo-max-height) + 2mm);
    }

    .report-pdf-fixed-footer__meta {
        flex: 1 1 0;
        min-width: 0;
        align-self: center;
    }

    .report-pdf-fixed-footer__meta dl {
        margin: 0;
        display: grid;
        grid-template-columns: auto 1fr;
        column-gap: 2mm;
        row-gap: 0.8mm;
        align-items: baseline;
    }

    .report-pdf-fixed-footer__meta dt {
        margin: 0;
        font-weight: 700;
        color: #1f2937;
        white-space: nowrap;
    }

    .report-pdf-fixed-footer__meta dd {
        margin: 0;
        overflow-wrap: normal;
        word-break: normal;
    }

    .report-pdf-fixed-footer__client-name {
        font-size: 9.5pt;
        font-weight: 700;
        color: #1f2937;
    }

    .report-pdf-fixed-footer__client-address {
        font-size: 6.5pt;
        font-weight: 400;
        color: #4b5563;
    }

    .report-pdf-fixed-footer__logos {
        flex: 1 1 0;
        min-width: 0;
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        align-self: center;
        justify-content: flex-end;
        gap: 3mm 3.5mm;
        min-height: var(--report-pdf-footer-logo-max-height);
    }

    .report-pdf-fixed-footer__birdlife-slot,
    .report-pdf-fixed-footer__auc-slot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        line-height: 0;
        flex: 1 1 0;
        min-width: 0;
        max-height: calc(var(--report-pdf-footer-logo-max-height) + 1mm);
    }

    .report-pdf-fixed-footer__footer-logo {
        display: block;
        max-height: var(--report-pdf-footer-logo-max-height);
        max-width: 100%;
        width: auto;
        height: auto;
        margin: 0;
        object-fit: contain;
        object-position: center;
    }

    .report-pdf-fixed-footer__birdlife-missing {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        min-width: 0;
        max-width: 100%;
        min-height: 12mm;
        max-height: var(--report-pdf-footer-logo-max-height);
        padding: 0 1.5mm;
        border: 1px dashed #9ca3af;
        border-radius: 2px;
        font-size: 6pt;
        font-weight: 600;
        color: #6b7280;
    }

    .report-pdf-fixed-footer__auc-missing {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        min-width: 0;
        max-width: 100%;
        min-height: 14mm;
        max-height: var(--report-pdf-footer-logo-max-height);
        padding: 0 1.5mm;
        border: 1px dashed #9ca3af;
        border-radius: 2px;
        font-size: 6pt;
        font-weight: 600;
        color: #6b7280;
    }
</style>
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
