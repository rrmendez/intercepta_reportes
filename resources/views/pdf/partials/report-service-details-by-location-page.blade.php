{{--
    Detalles del servicio por lugar de control: una tabla por seccion (Location).
    Requiere: $detalle_servicio_por_ubicacion (alias: $report_service_details_by_location).
    Los textos fijos van en el HTML para poder editarlos directamente en la plantilla.
--}}
@php
    $detalle = $detalle_servicio_por_ubicacion ?? $report_service_details_by_location ?? [];
    $sections = $detalle['sections'] ?? [];
@endphp
<style>
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

    .report-service-details-page__section {
        margin-top: 8mm;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .report-service-details-page__section:first-of-type {
        margin-top: 0;
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

    .report-service-details-page__empty {
        margin: 0;
        padding: 4mm;
        border: 1px dashed #d1d5db;
        color: #6b7280;
        font-size: 10pt;
    }
</style>

<section class="report-service-details-page" aria-label="Detalles del servicio por lugar de control">
    <h1 class="report-page-title">Detalles del servicio por lugar de control</h1>

    @if ($sections === [])
        <p class="report-service-details-page__empty">Sin secciones configuradas para este cliente.</p>
    @else
        @foreach ($sections as $section)
            <div class="report-service-details-page__section">
                <table class="report-service-details-page__table">
                    <thead>
                        <tr>
                            <th colspan="2">{{ $section['title'] ?? '—' }}</th>
                        </tr>
                    </thead>
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
                            <td>{{ $section['capturas'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td>Nidos retirados</td>
                            <td>{{ $section['nidos_retirados'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td>Abundancia al último día de servicio</td>
                            <td>{{ $section['abundancia'] ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
</section>
