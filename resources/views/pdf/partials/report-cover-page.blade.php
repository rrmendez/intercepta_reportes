{{--
    Portada del informe PDF.

    Cabecera a sangre (sin margen superior ni lateral):
    - public/images/header_reporte.png — franja que ocupa 1/3 del alto de la hoja A4 (297mm).

    Luego: titulo, fila de marca Intercepta + linea dorada, subtitulo.

    Logos BirdLife / AUC: pie fijo `report-pdf-fixed-footer` en la plantilla PDF (no en este partial).

    Opcional desde Blade: $coverTitle, $coverSubtitle, $coverHeaderImageUrl (URL absoluta o asset).
    Compatibilidad: $coverHeroUrl se usa como cabecera si no hay $coverHeaderImageUrl ni header_reporte.png.
--}}
@php
    $coverTitle = $coverTitle ?? 'Informe del servicio de control de fauna';
    $coverSubtitle = $coverSubtitle ?? 'CONTROL BIOLÓGICO DE FAUNA';

    $resolvePublicAsset = static function (string $relativePublicPath): ?string {
        $path = public_path($relativePublicPath);

        return is_string($path) && $path !== '' && is_file($path) ? asset($relativePublicPath) : null;
    };

    $headerImageUrl = ($coverHeaderImageUrl ?? null)
        ?? $resolvePublicAsset('images/header_reporte.png')
        ?? ($coverHeroUrl ?? null);
    $coverPageHeightMm = 297
        - max(0, min(40, (int) config('services.report_pdf.margins_mm', 12)))
        - max(18, min(55, (int) config('services.report_pdf.chrome_footer_slot_mm', 28)));
@endphp
<style>
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
</style>

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
        <h1 class="report-cover__title">{{ $coverTitle }}</h1>

        <div class="report-cover__brand-row">
            <div class="report-cover__brand-inner">
                <img
                    class="report-cover__logo-img"
                    src="{{ asset('images/intercepta-logo.svg') }}"
                    alt="Intercepta Uruguay"
                >
                <hr class="report-cover__gold-line">
            </div>
        </div>

        <p class="report-cover__tagline">{{ $coverSubtitle }}</p>
    </div>

    <hr class="report-cover__divider">
</section>
