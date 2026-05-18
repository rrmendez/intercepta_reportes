{{--
    Cinco hojas en blanco (solo el pie fijo global de la plantilla, position: fixed en print).
    Incluir despues de la portada y antes del @include del footer si el footer ya esta en el body.
--}}
<style>
    .report-pdf-blank-page {
        box-sizing: border-box;
        width: 100%;
        min-height: 297mm;
        margin: 0;
        padding: 0;
        page-break-after: always;
        break-after: page;
    }

    .report-pdf-blank-page--last {
        page-break-after: auto;
        break-after: auto;
    }
</style>
@foreach (range(1, 5) as $blankPageIndex)
    <div
        class="report-pdf-blank-page @if ($blankPageIndex === 5) report-pdf-blank-page--last @endif"
        aria-hidden="true"
    ></div>
@endforeach
