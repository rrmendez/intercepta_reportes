<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Report;
use App\Models\VisitReport;
use App\Services\BirdTypes\BirdTypeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Variables en español para plantillas Blade del informe PDF: textos editables, datos del cliente
 * y valores calculados a partir de visitas.
 */
final class ReportPdfTemplateVariables
{
    /**
     * Metadatos para la tabla de referencia (tipo y resumen en lenguaje claro).
     *
     * @return array<string, array{categoria: string, tipo: string, resumen: string, orden: int}>
     */
    public function definiciones(): array
    {
        return [
            'cliente' => [
                'categoria' => 'Cliente e informe',
                'tipo' => 'Datos del cliente',
                'resumen' => 'Ficha completa del cliente (nombre, dirección, correo, etc.). Use $cliente->name para el nombre.',
                'orden' => 10,
            ],
            'nombre_cliente' => [
                'categoria' => 'Cliente e informe',
                'tipo' => 'Texto',
                'resumen' => 'Nombre comercial o razón social del cliente tal como figura en el sistema.',
                'orden' => 11,
            ],
            'direccion_cliente' => [
                'categoria' => 'Cliente e informe',
                'tipo' => 'Texto',
                'resumen' => 'Dirección del predio o sede del cliente, si está cargada.',
                'orden' => 12,
            ],
            'informe' => [
                'categoria' => 'Cliente e informe',
                'tipo' => 'Datos del informe',
                'resumen' => 'Registro del informe con las fechas de inicio y fin del período analizado.',
                'orden' => 20,
            ],
            'etiqueta_periodo' => [
                'categoria' => 'Cliente e informe',
                'tipo' => 'Texto',
                'resumen' => 'Etiqueta legible del período (por ejemplo «Mayo 2026» o «01/05/2026 - 31/05/2026»).',
                'orden' => 21,
            ],
            'fecha_desde' => [
                'categoria' => 'Fechas',
                'tipo' => 'Fecha',
                'resumen' => 'Primer día del período del informe (objeto fecha; use $fecha_desde_texto para mostrarlo).',
                'orden' => 100,
            ],
            'fecha_hasta' => [
                'categoria' => 'Fechas',
                'tipo' => 'Fecha',
                'resumen' => 'Último día del período del informe (objeto fecha; use $fecha_hasta_texto para mostrarlo).',
                'orden' => 101,
            ],
            'fecha_desde_texto' => [
                'categoria' => 'Fechas',
                'tipo' => 'Texto',
                'resumen' => 'Fecha de inicio del período en formato dd/mm/aaaa.',
                'orden' => 102,
            ],
            'fecha_hasta_texto' => [
                'categoria' => 'Fechas',
                'tipo' => 'Texto',
                'resumen' => 'Fecha de fin del período en formato dd/mm/aaaa.',
                'orden' => 103,
            ],
            'fecha_generacion_texto' => [
                'categoria' => 'Fechas',
                'tipo' => 'Texto',
                'resumen' => 'Fecha y hora en que se generó el informe (dd/mm/aaaa HH:MM).',
                'orden' => 104,
            ],
            'visitas' => [
                'categoria' => 'Visitas y totales',
                'tipo' => 'Tabla de visitas',
                'resumen' => 'Filas de la tabla de visitas del período (fechas, empleado, cantidades por ubicación/ave y observaciones).',
                'orden' => 200,
            ],
            'columnas_visitas' => [
                'categoria' => 'Visitas y totales',
                'tipo' => 'Columnas de tabla',
                'resumen' => 'Encabezados y claves de las columnas de la tabla de visitas, en el mismo orden que la vista previa.',
                'orden' => 201,
            ],
            'registros_visita' => [
                'categoria' => 'Visitas y totales',
                'tipo' => 'Detalle técnico',
                'resumen' => 'Líneas de detalle de cada visita (cantidades por ubicación y tipo de ave). Uso avanzado.',
                'orden' => 202,
            ],
            'cantidad_visitas' => [
                'categoria' => 'Visitas y totales',
                'tipo' => 'Número',
                'resumen' => 'Cantidad de visitas realizadas en el período.',
                'orden' => 203,
            ],
            'total_observaciones' => [
                'categoria' => 'Visitas y totales',
                'tipo' => 'Número',
                'resumen' => 'Total de registros de observación en las visitas del período.',
                'orden' => 204,
            ],
            'total_cantidad' => [
                'categoria' => 'Visitas y totales',
                'tipo' => 'Número',
                'resumen' => 'Suma de todas las cantidades registradas en las visitas del período.',
                'orden' => 205,
            ],
            'totales_por_tipo_ave' => [
                'categoria' => 'Visitas y totales',
                'tipo' => 'Totales',
                'resumen' => 'Cantidades agrupadas por tipo de ave (nombre del ave => cantidad).',
                'orden' => 206,
            ],
            'totales_por_ubicacion' => [
                'categoria' => 'Visitas y totales',
                'tipo' => 'Totales',
                'resumen' => 'Cantidades agrupadas por lugar de control o sector.',
                'orden' => 207,
            ],
            'agregaciones' => [
                'categoria' => 'Visitas y totales',
                'tipo' => 'Totales',
                'resumen' => 'Resumen combinado con totales por tipo de ave y por ubicación.',
                'orden' => 208,
            ],
            'graficos_lineas_periodo' => [
                'categoria' => 'Gráficos',
                'tipo' => 'Gráficos',
                'resumen' => 'Datos para los gráficos de líneas del período (Chart.js). Incluye la clave charts con títulos y series.',
                'orden' => 300,
            ],
            'graficos_evolucion_fauna' => [
                'categoria' => 'Gráficos',
                'tipo' => 'Gráficos',
                'resumen' => 'Datos para los gráficos de evolución histórica del control de fauna.',
                'orden' => 301,
            ],
            'detalle_servicio_por_ubicacion' => [
                'categoria' => 'Detalle del servicio',
                'tipo' => 'Datos calculados por sector',
                'resumen' => 'Listado de sectores con capturas, nidos retirados y abundancia al cierre. Los textos fijos (método, especie, modalidad) están en el HTML de la plantilla.',
                'orden' => 310,
            ],
            'nombre_sector' => [
                'categoria' => 'Detalle del servicio',
                'tipo' => 'Texto',
                'resumen' => 'Nombre del sector o ubicación de control (encabezado de la tabla en plantillas de sector único).',
                'orden' => 311,
            ],
            'abundancia_ultimo_dia_sector' => [
                'categoria' => 'Detalle del servicio',
                'tipo' => 'Texto',
                'resumen' => 'Cantidad de aves por tipo en la última visita del período, con nombre y descripción (p. ej. «3 Paloma doméstica (Columba livia)»).',
                'orden' => 312,
            ],
            'situacion_actual_y_conclusiones' => [
                'categoria' => 'Situación actual',
                'tipo' => 'Datos calculados',
                'resumen' => 'Población al cierre, porcentaje de reducción, capturas, nidos retirados y nombre del técnico (calculados desde visitas).',
                'orden' => 320,
            ],
            'especies_identificadas_tabla' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Tabla de especies',
                'resumen' => 'Filas de la tabla de especies identificadas (nombre común, científico y situación). Se infiere de los tipos de ave del cliente.',
                'orden' => 330,
            ],
            'texto_especies_identificadas' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Texto',
                'resumen' => 'Listado en prosa de las especies identificadas, generado a partir de los tipos de ave registrados.',
                'orden' => 331,
            ],
            'url_imagen_cabecera_portada' => [
                'categoria' => 'Portada',
                'tipo' => 'URL de imagen',
                'resumen' => 'Imagen decorativa de la franja superior de la portada.',
                'orden' => 402,
            ],
            'url_logo_portada' => [
                'categoria' => 'Portada',
                'tipo' => 'URL de imagen',
                'resumen' => 'Logo de la empresa que se muestra en la portada.',
                'orden' => 403,
            ],
            'texto_alt_logo_portada' => [
                'categoria' => 'Portada',
                'tipo' => 'Texto editable',
                'resumen' => 'Texto alternativo del logo en la portada (accesibilidad).',
                'orden' => 404,
            ],
            'texto_situacion_inicial_parrafo_1' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Texto editable',
                'resumen' => 'Primer párrafo que describe la situación encontrada al inicio del servicio.',
                'orden' => 501,
            ],
            'texto_situacion_inicial_parrafo_2' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Texto editable',
                'resumen' => 'Segundo párrafo sobre factores asociados a la presencia de la fauna.',
                'orden' => 502,
            ],
            'encabezado_tabla_especie' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Texto editable',
                'resumen' => 'Título de la columna «Especie identificada» en la tabla de situación inicial.',
                'orden' => 503,
            ],
            'encabezado_tabla_nombre_cientifico' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Texto editable',
                'resumen' => 'Título de la columna «Nombre científico» en la tabla de situación inicial.',
                'orden' => 504,
            ],
            'encabezado_tabla_situacion_observada' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Texto editable',
                'resumen' => 'Título de la columna «Situación observada» en la tabla de situación inicial.',
                'orden' => 505,
            ],
            'encabezado_tabla_tipo_ave' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Texto editable',
                'resumen' => 'Título de la columna «Tipo de ave» en la tabla de población inicial (sector único / ave única).',
                'orden' => 506,
            ],
            'encabezado_tabla_poblacion_inicial' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Texto editable',
                'resumen' => 'Título de la columna «Población Inicial» en la tabla de situación inicial (sector único / ave única).',
                'orden' => 507,
            ],
            'tabla_poblacion_inicial_por_ave' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Tabla de población inicial',
                'resumen' => 'Filas con el nombre común, descripción científica y cantidad observada en el primer día de visita registrado.',
                'orden' => 508,
            ],
            'poblacion_inicial' => [
                'categoria' => 'Situación inicial',
                'tipo' => 'Número',
                'resumen' => 'Cantidad de paloma doméstica observada en el primer día de visita registrado (sector único / ave única).',
                'orden' => 509,
            ],
            'texto_objetivo' => [
                'categoria' => 'Objetivo y metodología',
                'tipo' => 'Texto editable',
                'resumen' => 'Párrafo que explica el objetivo del plan de control de fauna.',
                'orden' => 601,
            ],
            'texto_metodologia' => [
                'categoria' => 'Objetivo y metodología',
                'tipo' => 'Texto editable',
                'resumen' => 'Párrafo que describe la metodología de trabajo (cetrería, trampas, etc.).',
                'orden' => 602,
            ],
            'texto_sin_visitas_periodo' => [
                'categoria' => 'Objetivo y metodología',
                'tipo' => 'Texto editable',
                'resumen' => 'Mensaje que se muestra cuando no hay visitas en el período seleccionado.',
                'orden' => 604,
            ],
            'texto_graficos_sin_datos' => [
                'categoria' => 'Gráficos',
                'tipo' => 'Texto editable',
                'resumen' => 'Mensaje cuando no hay datos suficientes para dibujar los gráficos.',
                'orden' => 701,
            ],
            'texto_plantilla_reduccion_poblacion' => [
                'categoria' => 'Situación actual',
                'tipo' => 'Texto editable',
                'resumen' => 'Frase de reducción poblacional. Use :porcentaje donde corresponda (ej.: «La población de aves ha disminuido en un :porcentaje%.»).',
                'orden' => 904,
            ],
            'texto_conclusion' => [
                'categoria' => 'Situación actual',
                'tipo' => 'Texto editable',
                'resumen' => 'Párrafo de conclusión al final del informe, junto a la firma.',
                'orden' => 908,
            ],
            'url_imagen_firma' => [
                'categoria' => 'Situación actual',
                'tipo' => 'URL de imagen',
                'resumen' => 'Imagen de la firma del técnico responsable.',
                'orden' => 909,
            ],
            'contacto_sitio_web' => [
                'categoria' => 'Contacto',
                'tipo' => 'Texto editable',
                'resumen' => 'Sitio web de contacto que aparece en la página final.',
                'orden' => 1001,
            ],
            'contacto_email' => [
                'categoria' => 'Contacto',
                'tipo' => 'Texto editable',
                'resumen' => 'Correo electrónico de contacto.',
                'orden' => 1002,
            ],
            'contacto_celular' => [
                'categoria' => 'Contacto',
                'tipo' => 'Texto editable',
                'resumen' => 'Número de celular de contacto.',
                'orden' => 1003,
            ],
            'contacto_telefono_internacional' => [
                'categoria' => 'Contacto',
                'tipo' => 'Texto editable',
                'resumen' => 'Teléfono con prefijo internacional.',
                'orden' => 1004,
            ],
            'url_logo_contacto' => [
                'categoria' => 'Contacto',
                'tipo' => 'URL de imagen',
                'resumen' => 'Logo que se muestra en la página de contacto.',
                'orden' => 1005,
            ],
            'texto_alt_logo_contacto' => [
                'categoria' => 'Contacto',
                'tipo' => 'Texto editable',
                'resumen' => 'Texto alternativo del logo en la página de contacto.',
                'orden' => 1006,
            ],
        ];
    }

    /**
     * Textos fijos con valores predeterminados editables en la plantilla.
     *
     * @return array<string, string>
     */
    public function textosPredeterminados(): array
    {
        return [
            'texto_alt_logo_portada' => 'Intercepta Uruguay',
            'texto_situacion_inicial_parrafo_1' => 'En el estudio del predio a controlar, se constató la presencia de una población significativa de paloma doméstica (Columba livia).',
            'texto_situacion_inicial_parrafo_2' => 'La permanencia de la paloma doméstica en el predio se encuentra asociada principalmente a la disponibilidad de refugio, áreas de descanso (dormideros), sitios de nidificación y fuentes de alimento.',
            'encabezado_tabla_especie' => 'Especie identificada',
            'encabezado_tabla_nombre_cientifico' => 'Nombre científico',
            'encabezado_tabla_situacion_observada' => 'Situación observada',
            'encabezado_tabla_tipo_ave' => 'Tipo de ave',
            'encabezado_tabla_poblacion_inicial' => 'Población Inicial',
            'texto_objetivo' => 'El principal objetivo es disminuir la población inicial entre un 80% a un 90% en un período máximo de 3 meses. En una gran cantidad de casos se logra un control del 100% en este mismo período. De lo contrario, esta cifra puede alcanzarse en los meses siguientes.',
            'texto_metodologia' => 'La metodología a usar es la cetrería, generalmente con esto ya es suficiente. Eventualmente se evaluará si es conveniente complementar con otros métodos (trampas específicas para palomas, drones, palos telescópicos para nidos, entre otros)',
            'texto_sin_visitas_periodo' => 'No hay visitas para mostrar en el período seleccionado.',
            'texto_graficos_sin_datos' => 'Sin datos de visitas para graficar en este período.',
            'texto_plantilla_reduccion_poblacion' => 'La población de aves ha disminuido en un :porcentaje%.',
            'texto_conclusion' => 'En vista de los resultados alcanzados, creemos que el plan de trabajo implantado está siendo exitoso.',
            'contacto_sitio_web' => 'interceptauruguay.com.uy',
            'contacto_email' => 'mmaier@interceptauruguay.com.uy',
            'contacto_celular' => 'Cel.: 094 421 287',
            'contacto_telefono_internacional' => 'Ext.: (+598) 94 421 287',
            'texto_alt_logo_contacto' => 'Intercepta Uruguay',
        ];
    }

    /**
     * @param  array<string, mixed>  $datosCalculados  Datos ya calculados (visitas, gráficos, etc.)
     * @return array<string, mixed>
     */
    public function construir(Client $client, Report $report, array $period, array $datosCalculados): array
    {
        $dateFrom = $period['date_from'];
        $dateUntil = $period['date_until'];
        $richContent = $period['rich_content_data'] ?? [];

        $textos = $this->textosParaPlantilla($client);
        $especies = $this->especiesDesdeVisitas(
            $datosCalculados['registros_visita'] ?? collect(),
            $datosCalculados['registros_visita_historicos'] ?? collect(),
        );

        $detalleServicio = $datosCalculados['detalle_servicio_por_ubicacion'] ?? ['sections' => []];
        $primerSector = $detalleServicio['sections'][0] ?? null;
        $esPlantillaSectorUnicoAveUnica = ($client->import_mode ?? null) === ClientImportMode::SingleSectorSingleBird;
        $tablaPoblacionInicial = $datosCalculados['tabla_poblacion_inicial_por_ave'] ?? [];
        $poblacionInicial = (int) ($tablaPoblacionInicial[0]['poblacion_inicial'] ?? 0);

        $variables = [
            'cliente' => $client,
            'nombre_cliente' => (string) $client->name,
            'direccion_cliente' => trim((string) ($client->address ?? '')),
            'informe' => $report,
            'etiqueta_periodo' => (string) ($period['period_label'] ?? $richContent['period_label'] ?? ''),
            'fecha_desde' => $dateFrom,
            'fecha_hasta' => $dateUntil,
            'fecha_desde_texto' => $this->formatearFecha($dateFrom),
            'fecha_hasta_texto' => $this->formatearFecha($dateUntil),
            'fecha_generacion_texto' => ($report->generated_at ?? now())->format('d/m/Y H:i'),
            'visitas' => $richContent['visits'] ?? [],
            'columnas_visitas' => $richContent['visit_columns'] ?? [],
            'registros_visita' => $datosCalculados['registros_visita'] ?? collect(),
            'cantidad_visitas' => $richContent['visits_count'] ?? 0,
            'total_observaciones' => $richContent['total_observations'] ?? 0,
            'total_cantidad' => $richContent['total_quantity'] ?? 0,
            'totales_por_tipo_ave' => $richContent['totals_by_bird_type'] ?? [],
            'totales_por_ubicacion' => $richContent['totals_by_location'] ?? [],
            'agregaciones' => $period['aggregations'] ?? [],
            'graficos_lineas_periodo' => $datosCalculados['graficos_lineas_periodo'] ?? ['charts' => []],
            'graficos_evolucion_fauna' => $datosCalculados['graficos_evolucion_fauna'] ?? ['charts' => []],
            'detalle_servicio_por_ubicacion' => $detalleServicio,
            'abundancia_ultimo_dia_sector' => (string) ($primerSector['abundancia'] ?? '—'),
            'situacion_actual_y_conclusiones' => $datosCalculados['situacion_actual_y_conclusiones'] ?? [],
            'url_imagen_cabecera_portada' => $this->urlAssetPublico('images/header_reporte.png'),
            'url_logo_portada' => $this->urlAssetPublico('images/intercepta-logo.svg') ?? asset('images/intercepta-logo.svg'),
            'url_imagen_firma' => $this->urlAssetPublico('images/firma_manuel.jpeg') ?? asset('images/firma_manuel.jpeg'),
            'url_logo_contacto' => $this->urlAssetPublico('images/intercepta-logo.svg') ?? asset('images/intercepta-logo.svg'),
            ...$textos,
        ];

        if ($esPlantillaSectorUnicoAveUnica) {
            $variables['poblacion_inicial'] = $poblacionInicial;
        } else {
            $variables['nombre_sector'] = (string) ($primerSector['title'] ?? '—');
            $variables['tabla_poblacion_inicial_por_ave'] = $tablaPoblacionInicial;
            $variables['especies_identificadas_tabla'] = $especies['tabla'];
            $variables['texto_especies_identificadas'] = $especies['texto'];
        }

        return array_merge($variables, $this->aliasCompatibilidad($variables));
    }

    /**
     * @return array<string, string>
     */
    private function textosParaPlantilla(Client $client): array
    {
        $textos = $this->textosPredeterminados();

        if (($client->import_mode ?? null) !== ClientImportMode::SingleSectorSingleBird) {
            return $textos;
        }

        foreach ([
            'texto_situacion_inicial_parrafo_1',
            'texto_situacion_inicial_parrafo_2',
            'texto_objetivo',
            'texto_metodologia',
            'texto_sin_visitas_periodo',
            'encabezado_tabla_tipo_ave',
            'encabezado_tabla_poblacion_inicial',
            'texto_graficos_sin_datos',
            'texto_conclusion',
            'texto_alt_logo_portada',
            'texto_alt_logo_contacto',
            'contacto_sitio_web',
            'contacto_email',
            'contacto_celular',
            'contacto_telefono_internacional',
            'texto_plantilla_reduccion_poblacion',
        ] as $clave) {
            unset($textos[$clave]);
        }

        return $textos;
    }

    /**
     * @param  Collection<int, VisitReport>  $periodVisitReports
     * @param  Collection<int, VisitReport>  $historicalVisitReports
     * @return array{tabla: list<array{nombre_comun: string, descripcion: string, nombre_cientifico: string, situacion: string}>, texto: string}
     */
    public function especiesDesdeVisitas(Collection $periodVisitReports, Collection $historicalVisitReports): array
    {
        $birdTypes = $periodVisitReports
            ->merge($historicalVisitReports)
            ->map(fn (VisitReport $report): ?BirdType => $report->birdType)
            ->filter()
            ->unique(fn (BirdType $birdType): int => (int) $birdType->getKey())
            ->sortBy('name')
            ->values();

        if ($birdTypes->isEmpty()) {
            $defaultBird = app(BirdTypeResolver::class)->default();

            return [
                'tabla' => [
                    [
                        'nombre_comun' => $defaultBird->common_name,
                        'descripcion' => (string) ($defaultBird->scientific_name ?? '—'),
                        'nombre_cientifico' => (string) ($defaultBird->scientific_name ?? '—'),
                        'situacion' => 'Población significativa asociada a refugio, dormideros, sitios de nidificación y fuentes de alimento.',
                    ],
                ],
                'texto' => 'Especies identificadas: '.$defaultBird->labelWithScientific().'.',
            ];
        }

        $tabla = $birdTypes
            ->map(function (BirdType $birdType): array {
                $nombreComun = trim((string) $birdType->common_name);
                $descripcionAve = trim((string) ($birdType->scientific_name ?? ''));
                $descripcion = $descripcionAve !== '' ? $descripcionAve : '—';

                return [
                    'nombre_comun' => $nombreComun,
                    'descripcion' => $descripcion,
                    'nombre_cientifico' => $descripcion,
                    'situacion' => 'Población registrada en visitas del predio.',
                ];
            })
            ->all();

        $textoPartes = $birdTypes
            ->map(fn (BirdType $birdType): string => $birdType->labelWithScientific())
            ->all();

        return [
            'tabla' => $tabla,
            'texto' => 'Especies identificadas: '.implode('; ', $textoPartes).'.',
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function aliasCompatibilidad(array $variables): array
    {
        return [
            'client' => $variables['cliente'],
            'report' => $variables['informe'],
            'period_label' => $variables['etiqueta_periodo'],
            'dateFrom' => $variables['fecha_desde'],
            'dateUntil' => $variables['fecha_hasta'],
            'visits' => $variables['visitas'],
            'visit_columns' => $variables['columnas_visitas'],
            'visit_reports' => $variables['registros_visita'],
            'visits_count' => $variables['cantidad_visitas'],
            'total_quantity' => $variables['total_cantidad'],
            'totals_by_bird_type' => $variables['totales_por_tipo_ave'],
            'totals_by_location' => $variables['totales_por_ubicacion'],
            'aggregations' => $variables['agregaciones'],
            'report_line_charts' => $variables['graficos_lineas_periodo'],
            'report_fauna_evolution_charts' => $variables['graficos_evolucion_fauna'],
            'report_service_details_by_location' => $variables['detalle_servicio_por_ubicacion'],
            'report_current_situation_and_conclusions' => $variables['situacion_actual_y_conclusiones'],
            'coverHeaderImageUrl' => $variables['url_imagen_cabecera_portada'],
        ];
    }

    private function formatearFecha(mixed $fecha): string
    {
        if ($fecha instanceof CarbonImmutable) {
            return $fecha->format('d/m/Y');
        }

        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('d/m/Y');
        }

        return '';
    }

    private function urlAssetPublico(string $relativePublicPath): ?string
    {
        $path = public_path($relativePublicPath);

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        return asset($relativePublicPath);
    }
}
