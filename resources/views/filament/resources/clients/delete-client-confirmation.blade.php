@php
    /** @var \App\Models\Client $client */
    /** @var array{locations: int, templates: int, sections: int, reports: int, report_pdfs: int, visit_imports: int, visits: int, visit_reports: int} $counts */

    $items = [
        [
            'count' => $counts['locations'],
            'singular' => 'sección del cliente',
            'plural' => 'secciones del cliente',
        ],
        [
            'count' => $counts['templates'],
            'singular' => 'plantilla PDF personalizada',
            'plural' => 'plantillas PDF personalizadas',
        ],
        [
            'count' => $counts['sections'],
            'singular' => 'bloque de contenido en plantillas',
            'plural' => 'bloques de contenido en plantillas',
        ],
        [
            'count' => $counts['reports'],
            'singular' => 'reporte mensual',
            'plural' => 'reportes mensuales',
        ],
        [
            'count' => $counts['visit_imports'],
            'singular' => 'importación de visitas',
            'plural' => 'importaciones de visitas',
        ],
        [
            'count' => $counts['visits'],
            'singular' => 'visita registrada',
            'plural' => 'visitas registradas',
        ],
        [
            'count' => $counts['visit_reports'],
            'singular' => 'registro de captura (cantidades por ave y sección)',
            'plural' => 'registros de captura (cantidades por ave y sección)',
        ],
    ];
@endphp

<div class="space-y-4 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
    <p>
        Al confirmar, se eliminará permanentemente el cliente
        <strong class="font-semibold text-gray-950 dark:text-white">{{ $client->name }}</strong>
        y todos los datos asociados listados a continuación.
    </p>

    <ul class="list-disc space-y-1.5 pl-5">
        @foreach ($items as $item)
            <li>
                <span class="font-medium text-gray-950 dark:text-white">{{ number_format($item['count']) }}</span>
                {{ $item['count'] === 1 ? $item['singular'] : $item['plural'] }}
            </li>
        @endforeach
    </ul>

    @if ($counts['report_pdfs'] > 0)
        <p>
            También se eliminarán
            <strong class="font-semibold text-gray-950 dark:text-white">{{ number_format($counts['report_pdfs']) }}</strong>
            {{ $counts['report_pdfs'] === 1 ? 'archivo PDF generado' : 'archivos PDF generados' }}
            almacenados en el sistema.
        </p>
    @endif

    <p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-100">
        Esta acción no se puede deshacer. Los operarios y tipos de ave no se verán afectados.
    </p>
</div>
