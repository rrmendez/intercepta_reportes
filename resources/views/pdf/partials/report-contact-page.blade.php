{{--
    Pagina final de contacto del informe PDF.
    Variables editables: contacto_*, url_logo_contacto, texto_alt_logo_contacto.
--}}
@php
    $contacto_sitio_web = $contacto_sitio_web ?? 'interceptauruguay.com.uy';
    $contacto_email = $contacto_email ?? 'mmaier@interceptauruguay.com.uy';
    $contacto_celular = $contacto_celular ?? 'Cel.: 094 421 287';
    $contacto_telefono_internacional = $contacto_telefono_internacional ?? 'Ext.: (+598) 94 421 287';
    $texto_alt_logo_contacto = $texto_alt_logo_contacto ?? 'Intercepta Uruguay';

    $logoSrc = \App\Services\ReportPdfPublicImageDataUri::fromRelativePublicPath('images/intercepta-logo.svg')
        ?? ($url_logo_contacto ?? asset('images/intercepta-logo.svg'));

    $contactPageHeightMm = 297
        - max(0, min(40, (int) config('services.report_pdf.margins_mm', 12)))
        - max(18, min(55, (int) config('services.report_pdf.chrome_footer_slot_mm', 28)))
        - max(0, min(20, (int) config('services.report_pdf.contact_page_height_trim_mm', 8)));
@endphp
<style>
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
</style>

<section class="report-contact-page" lang="es" aria-label="Contacto">
    <div class="report-contact-page__chrome-mask" aria-hidden="true"></div>

    <div class="report-contact-page__layout">
        <div class="report-contact-page__center">
            <div class="report-contact-page__center-inner">
            <img
                class="report-contact-page__logo"
                src="{{ $logoSrc }}"
                alt="{{ $texto_alt_logo_contacto }}"
            >

            <div class="report-contact-page__details">
                <h2 class="report-contact-page__heading">CONTACTO</h2>
                @if ($contacto_sitio_web !== '')
                    <p class="report-contact-page__link">{{ $contacto_sitio_web }}</p>
                @endif
                @if ($contacto_email !== '')
                    <p class="report-contact-page__link">{{ $contacto_email }}</p>
                @endif
                @if ($contacto_celular !== '')
                    <p class="report-contact-page__phone">{{ $contacto_celular }}</p>
                @endif
                @if ($contacto_telefono_internacional !== '')
                    <p class="report-contact-page__phone">{{ $contacto_telefono_internacional }}</p>
                @endif
            </div>
            </div>
        </div>
    </div>
</section>
