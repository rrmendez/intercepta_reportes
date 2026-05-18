{{--
    Header por defecto para paginas normales del PDF.
    Se inyecta como `position: fixed`; la portada lo cubre con su propio fondo/z-index.
--}}
@php
    $interceptaLogoSrc = \App\Services\ReportPdfPublicImageDataUri::fromRelativePublicPath('images/intercepta-logo.svg')
        ?? asset('images/intercepta-logo.svg');
@endphp
<style id="report-pdf-default-header-styles">
    @media print {
        .report-pdf-default-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            width: 100%;
            height: 18mm;
            margin: 0;
            padding: 0;
            background: transparent;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .report-pdf-default-header__logo {
            display: block;
            flex: 0 0 auto;
            width: auto;
            height: 17mm;
            margin: 0;
            padding: 0;
            border: 0;
            object-fit: contain;
            object-position: left center;
        }

        .report-pdf-default-header__line {
            flex: 1 1 auto;
            height: 0;
            margin: 0 0 0 4mm;
            padding: 0;
            border: 0;
            border-top: 2px solid #d4a012;
        }
    }
</style>
<div id="report-pdf-default-header-root" class="report-pdf-default-header" aria-hidden="true">
    <img class="report-pdf-default-header__logo" src="{{ $interceptaLogoSrc }}" alt="">
    <hr class="report-pdf-default-header__line">
</div>
