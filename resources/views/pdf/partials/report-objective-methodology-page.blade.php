{{--
    Segunda pagina normal del informe: objetivo, metodologia y registro del control de fauna.
    La tabla toma los datos de $visitas / $columnas_visitas.
--}}
@php
    $texto_objetivo = $texto_objetivo ?? 'El principal objetivo es disminuir la población inicial entre un 80% a un 90% en un período máximo de 3 meses. En una gran cantidad de casos se logra un control del 100% en este mismo período. De lo contrario, esta cifra puede alcanzarse en los meses siguientes.';
    $texto_metodologia = $texto_metodologia ?? 'La metodología a usar es la cetrería, generalmente con esto ya es suficiente. Eventualmente se evaluará si es conveniente complementar con otros métodos (trampas específicas para palomas, drones, palos telescópicos para nidos, entre otros)';
    $texto_sin_visitas_periodo = $texto_sin_visitas_periodo ?? 'No hay visitas para mostrar en el período seleccionado.';

    $visitRows = collect($visitas ?? $visits ?? [])->values();
    $visitColumns = collect($columnas_visitas ?? $visit_columns ?? [])->map(fn (array $column): array => [
        'key' => (string) ($column['key'] ?? ''),
        'label' => \Illuminate\Support\Str::ucfirst((string) ($column['label'] ?? ($column['key'] ?? ''))),
    ])->filter(fn (array $column): bool => $column['key'] !== '')->values();

    if ($visitColumns->isEmpty() && $visitRows->first() !== null) {
        $visitColumns = collect(array_keys((array) $visitRows->first()))
            ->map(fn (string $key): array => ['key' => $key, 'label' => \Illuminate\Support\Str::ucfirst($key)]);
    }
@endphp
<style>
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
</style>

<section class="report-objective-methodology-page">
    <h1 class="report-page-title">Objetivo y metodología</h1>

    <div class="report-objective-methodology-page__content">
        @if ($texto_objetivo !== '')
            <p>{{ $texto_objetivo }}</p>
        @endif

        @if ($texto_metodologia !== '')
            <p>{{ $texto_metodologia }}</p>
        @endif

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
            <p class="report-objective-methodology-page__empty">{{ $texto_sin_visitas_periodo }}</p>
        @endif
    </div>
</section>
