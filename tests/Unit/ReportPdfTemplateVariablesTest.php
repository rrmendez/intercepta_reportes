<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Report;
use App\Models\VisitReport;
use App\Services\Reports\ReportBladeVariableReference;
use App\Services\Reports\ReportPdfTemplateVariables;
use Carbon\CarbonImmutable;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('expone variables en español con textos predeterminados editables', function (): void {
    $client = Client::make([
        'name' => 'Cliente Demo',
        'address' => 'Montevideo',
        'import_mode' => ClientImportMode::SingleSectorSingleBird,
    ]);

    $report = Report::make([
        'date_from' => '2026-05-01',
        'date_until' => '2026-05-31',
        'generated_at' => CarbonImmutable::parse('2026-05-24 10:00:00'),
    ]);

    $variables = app(ReportPdfTemplateVariables::class)->construir($client, $report, [
        'date_from' => CarbonImmutable::parse('2026-05-01'),
        'date_until' => CarbonImmutable::parse('2026-05-31'),
        'period_label' => 'Mayo 2026',
        'aggregations' => ['totals_by_bird_type' => [], 'totals_by_location' => []],
        'rich_content_data' => [
            'visits' => [],
            'visit_columns' => [],
            'visits_count' => 0,
            'total_observations' => 0,
            'total_quantity' => 0,
            'totals_by_bird_type' => [],
            'totals_by_location' => [],
        ],
    ], [
        'registros_visita' => collect(),
        'registros_visita_historicos' => collect(),
        'tabla_poblacion_inicial_por_ave' => [
            [
                'nombre_comun' => 'Paloma doméstica',
                'descripcion' => 'Columba livia',
                'poblacion_inicial' => 125,
            ],
        ],
        'graficos_lineas_periodo' => ['charts' => []],
        'graficos_evolucion_fauna' => ['charts' => []],
        'detalle_servicio_por_ubicacion' => ['sections' => []],
        'situacion_actual_y_conclusiones' => [],
    ]);

    expect($variables)->toHaveKeys([
        'cliente',
        'nombre_cliente',
        'etiqueta_periodo',
        'fecha_desde_texto',
        'fecha_hasta_texto',
        'poblacion_inicial',
        'visitas',
    ])
        ->and($variables['nombre_cliente'])->toBe('Cliente Demo')
        ->and($variables['fecha_desde_texto'])->toBe('01/05/2026')
        ->and($variables['poblacion_inicial'])->toBe(125)
        ->and($variables['client'])->toBe($variables['cliente'])
        ->and($variables['visits'])->toBe($variables['visitas'])
        ->and($variables)->not->toHaveKeys([
            'tabla_poblacion_inicial_por_ave',
            'texto_situacion_inicial_parrafo_1',
            'texto_situacion_inicial_parrafo_2',
            'texto_objetivo',
            'texto_metodologia',
            'texto_sin_visitas_periodo',
            'texto_especies_identificadas',
            'especies_identificadas_tabla',
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
            'nombre_sector',
        ]);
});

it('infiere especies desde los tipos de ave de las visitas', function (): void {
    $birdType = BirdType::factory()->create([
        'slug' => 'gaviotas',
        'name' => 'Gaviotas',
        'common_name' => 'Gaviota',
        'common_name_plural' => 'Gaviotas',
        'scientific_name' => 'Larus dominicanus',
    ]);

    $visitReport = new VisitReport;
    $visitReport->setRelation('birdType', $birdType);

    $especies = app(ReportPdfTemplateVariables::class)->especiesDesdeVisitas(
        Collection::make([$visitReport]),
        collect(),
    );

    expect($especies['texto'])->toContain('Gaviota (Larus dominicanus)')
        ->and($especies['tabla'][0]['nombre_comun'])->toBe('Gaviota');
});

it('documenta variables con resumenes en español para usuarios no técnicos', function (): void {
    $definiciones = app(ReportPdfTemplateVariables::class)->definiciones();

    expect($definiciones)->toHaveKey('visitas')
        ->and($definiciones['visitas']['tipo'])->toBe('Tabla de visitas')
        ->and($definiciones['visitas']['resumen'])->toContain('Filas de la tabla de visitas');

    $rows = app(ReportBladeVariableReference::class)->rows([
        'visitas' => [],
        'texto_conclusion' => 'Conclusión demo',
        'nombre_cliente' => 'Acme',
    ]);

    expect(collect($rows)->pluck('name')->all())->toContain('$visitas', '$texto_conclusion')
        ->and(collect($rows)->firstWhere('name', '$nombre_cliente')['summary'])->toContain('Valor actual: Acme');
});
