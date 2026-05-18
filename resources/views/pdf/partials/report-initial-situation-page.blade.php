{{--
    Primera pagina normal del informe: situacion inicial del predio.
--}}
<style>
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
</style>

<section class="report-initial-situation-page">
    <h1 class="report-page-title">Situación inicial del predio</h1>

    <div class="report-initial-situation-page__content">
        <p>
            En el estudio del predio a controlar, se constató la presencia de una población significativa de
            paloma doméstica (Columba livia).
        </p>

        <p>
            La permanencia de la paloma doméstica en el predio se encuentra asociada principalmente a la
            disponibilidad de refugio, áreas de descanso (dormideros), sitios de nidificación y fuentes de alimento.
        </p>

        <p class="report-initial-situation-page__species">
            Especies identificadas: Paloma doméstica (Columba livia).
        </p>

        <table class="report-initial-situation-page__table">
            <thead>
                <tr>
                    <th>Especie identificada</th>
                    <th>Nombre científico</th>
                    <th>Situación observada</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Paloma doméstica</td>
                    <td><em>Columba livia</em></td>
                    <td>
                        Población significativa asociada a refugio, dormideros, sitios de nidificación y fuentes de alimento.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
