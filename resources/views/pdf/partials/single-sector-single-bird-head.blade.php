@php
        $poblacion_inicial = (int) ($poblacion_inicial ?? 0);
        $visitRows = collect($visitas ?? $visits ?? [])->values();
        $visitColumns = collect($columnas_visitas ?? $visit_columns ?? [])->map(fn (array $column): array => [
            'key' => (string) ($column['key'] ?? ''),
            'label' => \Illuminate\Support\Str::ucfirst((string) ($column['label'] ?? ($column['key'] ?? ''))),
        ])->filter(fn (array $column): bool => $column['key'] !== '')->values();

        if ($visitColumns->isEmpty() && $visitRows->first() !== null) {
            $visitColumns = collect(array_keys((array) $visitRows->first()))
                ->map(fn (string $key): array => ['key' => $key, 'label' => \Illuminate\Support\Str::ucfirst($key)]);
        }

        $chartsData = $graficos_evolucion_fauna ?? $report_fauna_evolution_charts ?? [];
        $charts = $chartsData['charts'] ?? [];
        $hasData = collect($charts)->contains(
            fn (array $chart): bool => ! empty($chart['datasets'] ?? []),
        );

        $abundancia_ultimo_dia_sector = $abundancia_ultimo_dia_sector ?? '—';

        $data = $situacion_actual_y_conclusiones ?? $report_current_situation_and_conclusions ?? [];
        $populationEntries = $data['population_entries'] ?? [];
        $reductionPercentage = $data['reduction_percentage'] ?? null;

        $signatureRelativePath = 'images/firma_manuel.jpeg';
        $signatureSrc = \App\Services\ReportPdfPublicImageDataUri::fromRelativePublicPath($signatureRelativePath)
            ?? ($url_imagen_firma ?? asset($signatureRelativePath));

        $logoSrc = \App\Services\ReportPdfPublicImageDataUri::fromRelativePublicPath('images/intercepta-logo.svg')
            ?? ($url_logo_contacto ?? asset('images/intercepta-logo.svg'));

        $contactPageHeightMm = 297
            - max(0, min(40, (int) config('services.report_pdf.margins_mm', 12)))
            - max(18, min(55, (int) config('services.report_pdf.chrome_footer_slot_mm', 28)))
            - max(0, min(20, (int) config('services.report_pdf.contact_page_height_trim_mm', 8)));

        $resolveCoverAssetUrl = static function (?string $url): ?string {
            if ($url !== null && $url !== '') {
                return $url;
            }

            return null;
        };

        $headerImageUrl = $resolveCoverAssetUrl($url_imagen_cabecera_portada ?? null)
            ?? $resolveCoverAssetUrl($coverHeaderImageUrl ?? null)
            ?? (is_file(public_path('images/header_reporte.png')) ? asset('images/header_reporte.png') : null)
            ?? ($coverHeroUrl ?? null);

        $logoPortadaUrl = $url_logo_portada ?? asset('images/intercepta-logo.svg');

        $coverPageHeightMm = 297
            - max(0, min(40, (int) config('services.report_pdf.margins_mm', 12)))
            - max(18, min(55, (int) config('services.report_pdf.chrome_footer_slot_mm', 28)));

        $resolveFooterAssetPath = static function (string $relativePublicPath): ?string {
            $path = public_path($relativePublicPath);

            return is_string($path) && $path !== '' && is_file($path) ? asset($relativePublicPath) : null;
        };

        $birdlifeLogoUrl = $footerPartnerBirdlifeUrl ?? $resolveFooterAssetPath('images/birdlife.png');
        $aucLogoUrl = $footerPartnerAucUrl ?? $resolveFooterAssetPath('images/auc.svg');
        $reportNumberLabel = ($report !== null && $report->getKey() !== null)
            ? (string) $report->getKey()
            : '—';
        $clientAddressLine = trim((string) ($client->address ?? ''));
        $reportPdfMarginsMm = max(0, min(40, (int) config('services.report_pdf.margins_mm', 12)));
    @endphp
    <meta charset="UTF-8">
    <title>Reporte mensual</title>
    <style>
        /* Base */
html, body { margin: 0; background: #ffffff; }
        body { font-family: DejaVu Sans, sans-serif, system-ui, sans-serif; font-size: 12px; color: #111827; }
        h1, h2, h3 { margin: 0 0 8px 0; }
        h1 { font-size: 20px; }
        h2 { font-size: 16px; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        .muted { color: #6b7280; }
        .section { margin-top: 14px; }

        /* Situación inicial */
        .report-initial-situation-page {
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

    .report-page-title {
        margin: 0 0 9mm;
        color: rgb(232, 177, 76);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 24pt;
        font-weight: 700;
        line-height: 1.15;
    }

    .report-initial-situation-page__content {
        max-width: 100%;
        font-size: 11pt;
        line-height: 1.55;
    }

    .report-initial-situation-page__content p {
        margin: 0 0 5mm;
    }

    .report-initial-situation-page__species {
        margin: 1mm 0 8mm;
        font-weight: 700;
    }

    .report-initial-situation-page__table {
        width: 100%;
        margin-top: 6mm;
        border-collapse: collapse;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10pt;
    }

    .report-initial-situation-page__table th {
        padding: 3mm;
        border: 1px solid rgb(232, 177, 76);
        background: rgb(232, 177, 76);
        color: #ffffff;
        font-weight: 700;
        text-align: left;
    }

    .report-initial-situation-page__table td {
        padding: 3mm;
        border: 1px solid #d1d5db;
        color: #374151;
        vertical-align: top;
    }

        /* Objetivo y metodología */
        .report-objective-methodology-page {
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

    .report-objective-methodology-page .report-page-title {
        margin: 0 0 9mm;
        color: rgb(232, 177, 76);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 24pt;
        font-weight: 700;
        line-height: 1.15;
    }

    .report-objective-methodology-page__content {
        max-width: 100%;
        font-size: 11pt;
        line-height: 1.55;
    }

    .report-objective-methodology-page__content p {
        margin: 0 0 5mm;
    }

    .report-objective-methodology-page__subtitle {
        margin: 9mm 0 5mm;
        color: rgb(232, 177, 76);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 15pt;
        font-weight: 700;
        line-height: 1.2;
    }

    .report-objective-methodology-page__table-wrap {
        width: 100%;
        overflow: hidden;
    }

    .report-objective-methodology-page__table {
        width: 100%;
        border-collapse: collapse;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 8pt;
        table-layout: fixed;
    }

    .report-objective-methodology-page__table th {
        padding: 2mm;
        border: 1px solid rgb(232, 177, 76);
        background: rgb(232, 177, 76);
        color: #ffffff;
        font-weight: 700;
        text-align: left;
    }

    .report-objective-methodology-page__table td {
        padding: 2mm;
        border: 1px solid #d1d5db;
        color: #374151;
        overflow-wrap: anywhere;
        vertical-align: top;
    }

    .report-objective-methodology-page__empty {
        margin: 0;
        padding: 4mm;
        border: 1px dashed #d1d5db;
        color: #6b7280;
        font-size: 10pt;
    }

        /* Evolución de fauna */
        .report-fauna-evolution-page {
        box-sizing: border-box;
        width: 100%;
        margin: 0;
        padding: 24mm 0 10mm;
        page-break-after: always;
        break-after: page;
        color: #374151;
        font-family: Arial, Helvetica, sans-serif;
    }

    .report-fauna-evolution-page .report-page-title {
        margin: 0 0 9mm;
        padding: 0;
        color: rgb(232, 177, 76);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 24pt;
        font-weight: 700;
        line-height: 1.15;
    }

    .report-fauna-evolution-page__chart {
        margin-top: 8mm;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .report-fauna-evolution-page__chart:first-of-type {
        margin-top: 0;
    }

    .report-fauna-evolution-page__chart-title {
        margin: 0 0 3mm;
        color: #374151;
        font-size: 11pt;
        font-weight: 700;
    }

    .report-fauna-evolution-page__chart-canvas-wrap {
        box-sizing: border-box;
        position: relative;
        width: 100%;
        max-width: 100%;
        height: 260px;
        min-height: 260px;
        overflow: visible;
    }

    .report-fauna-evolution-page__chart-canvas-wrap canvas {
        display: block;
        width: 100%;
        max-width: 100%;
        height: auto;
    }

    .report-fauna-evolution-page__empty {
        margin: 0;
        padding: 4mm;
        border: 1px dashed #d1d5db;
        color: #6b7280;
        font-size: 10pt;
    }

        /* Detalles del servicio */
        .report-service-details-page {
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

    .report-service-details-page .report-page-title {
        margin: 0 0 9mm;
        color: rgb(232, 177, 76);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 24pt;
        font-weight: 700;
        line-height: 1.15;
    }

    .report-service-details-page__table {
        width: 100%;
        border-collapse: collapse;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10pt;
        table-layout: fixed;
    }

    .report-service-details-page__table th,
    .report-service-details-page__table td {
        padding: 3mm;
        border: 1px solid #d1d5db;
        color: #374151;
        vertical-align: top;
        text-align: left;
    }

    .report-service-details-page__table th {
        background: #f3f4f6;
        font-weight: 700;
    }

    .report-service-details-page__table td:first-child {
        width: 42%;
        font-weight: 700;
    }

    .report-service-details-page__table td:last-child {
        white-space: pre-line;
    }

        /* Situación actual y conclusiones */
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

        /* Contacto */
        .report-contact-page {
        box-sizing: border-box;
        position: relative;
        z-index: 6;
        width: 100%;
        min-height: {{ $contactPageHeightMm }}mm;
        margin: 0;
        padding: 0;
        page-break-after: avoid;
        break-after: avoid;
        background: #ffffff;
        color: #374151;
        font-family: Arial, Helvetica, sans-serif;
    }

    .report-contact-page__chrome-mask {
        display: none;
    }

    @media print {
        .report-contact-page__chrome-mask {
            display: block;
            position: absolute;
            top: -24mm;
            left: -20mm;
            right: -20mm;
            height: 24mm;
            background: #ffffff;
            z-index: 5;
            pointer-events: none;
        }
    }

    .report-contact-page__layout {
        box-sizing: border-box;
        display: table;
        width: 100%;
        height: {{ $contactPageHeightMm }}mm;
        min-height: {{ $contactPageHeightMm }}mm;
        padding: 14mm;
        table-layout: fixed;
    }

    .report-contact-page__center {
        display: table-cell;
        vertical-align: middle;
        text-align: left;
        width: 100%;
        max-width: 175mm;
    }

    .report-contact-page__center-inner {
        display: block;
        width: 100%;
        max-width: 175mm;
    }

    .report-contact-page__center-inner .report-contact-page__logo {
        margin-bottom: 10mm;
    }

    .report-contact-page__logo {
        display: block;
        width: auto;
        height: 24mm;
    }

    .report-contact-page__details {
        max-width: 95mm;
        text-align: left;
    }

    .report-contact-page__heading {
        margin: 0 0 5mm;
        color: rgb(232, 177, 76);
        font-size: 15pt;
        font-weight: 700;
        letter-spacing: 0.04em;
        line-height: 1.2;
    }

    .report-contact-page__link,
    .report-contact-page__phone {
        margin: 0 0 2.5mm;
        font-size: 11pt;
        line-height: 1.45;
    }

    .report-contact-page__link {
        color: #2563eb;
        text-decoration: underline;
    }

    .report-contact-page__phone {
        color: #6b7280;
    }

/* Portada */
.report-cover {
        box-sizing: border-box;
        width: 100%;
        min-height: {{ $coverPageHeightMm }}mm;
        margin: 0;
        padding: 0;
        position: relative;
        z-index: 2;
        background: #ffffff;
        color: #374151;
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
    }

    .report-cover *,
    .report-cover *::before,
    .report-cover *::after {
        box-sizing: border-box;
    }

    .report-cover__header-strip {
        width: 100%;
        height: calc(297mm / 3);
        margin: 0;
        padding: 0;
        overflow: hidden;
        background: #e5e7eb;
        line-height: 0;
    }

    .report-cover__header-img {
        display: block;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        border: 0;
        object-fit: cover;
        object-position: center center;
    }

    .report-cover__main {
        padding: 10mm 14mm 8mm;
        text-align: center;
    }

    .report-cover__title {
        margin: 0 0 10mm;
        font-size: 42pt;
        font-weight: 700;
        line-height: 1.2;
        color: #1f2937;
        letter-spacing: 0.01em;
        overflow-wrap: normal;
        word-break: normal;
        hyphens: none;
        -webkit-hyphens: none;
    }

    .report-cover__brand-row {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        max-width: 100%;
        margin: 0 auto 4mm;
        padding: 0 8mm;
    }

    .report-cover__brand-inner {
        display: flex;
        align-items: center;
        justify-content: center;
        max-width: 175mm;
        width: 100%;
        margin: 0 auto;
    }

    .report-cover__logo-img {
        display: block;
        height: 24mm;
        width: auto;
        flex: 0 0 auto;
    }

    .report-cover__gold-line {
        flex: 1 1 auto;
        height: 0;
        border: 0;
        border-bottom: 2px solid #d4a012;
        margin-left: 5mm;
        min-width: 18mm;
        max-width: 120mm;
    }

    .report-cover__tagline {
        margin: 0 0 10mm;
        font-size: 8pt;
        font-weight: 600;
        letter-spacing: 0.08em;
        color: #6b7280;
    }

    .report-cover__divider {
        height: 1px;
        margin: 0 14mm 10mm;
        background: #e5e7eb;
        border: 0;
    }

    .report-cover--page-break {
        page-break-after: always;
    }

/* Pie fijo */
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
