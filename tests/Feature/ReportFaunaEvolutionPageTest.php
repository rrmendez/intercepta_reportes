<?php

use App\Services\ReportChartScriptInjector;

it('renders the fauna evolution page with two charts and one line per bird type', function (): void {
    expect(app(ReportChartScriptInjector::class)->hasBuiltBundle())->toBeTrue();

    $reportFaunaEvolutionCharts = [
        'charts' => [
            [
                'id' => 'report-chart-fauna-period',
                'title' => 'Conteos de aves',
                'labels' => ['02/03/2026', '10/03/2026'],
                'datasets' => [
                    [
                        'label' => 'Palomas',
                        'data' => [0, 1],
                        'borderColor' => '#2563eb',
                        'backgroundColor' => '#2563eb',
                        'borderWidth' => 2,
                        'pointRadius' => 2,
                        'tension' => 0.2,
                        'fill' => false,
                    ],
                    [
                        'label' => 'Cotorras',
                        'data' => [2, 0],
                        'borderColor' => '#dc2626',
                        'backgroundColor' => '#dc2626',
                        'borderWidth' => 2,
                        'pointRadius' => 2,
                        'tension' => 0.2,
                        'fill' => false,
                    ],
                ],
                'x_axis_label' => 'Fecha',
                'y_axis_label' => 'Cantidad',
            ],
            [
                'id' => 'report-chart-fauna-historical',
                'title' => 'Conteos de aves (historico)',
                'labels' => ['15/10/2025', '02/03/2026'],
                'datasets' => [
                    [
                        'label' => 'Palomas',
                        'data' => [125, 1],
                        'borderColor' => '#2563eb',
                        'backgroundColor' => '#2563eb',
                        'borderWidth' => 2,
                        'pointRadius' => 2,
                        'tension' => 0.2,
                        'fill' => false,
                    ],
                ],
                'x_axis_label' => 'Fecha',
                'y_axis_label' => 'Cantidad',
            ],
        ],
    ];

    $html = view('pdf.partials.report-fauna-evolution-page', [
        'report_fauna_evolution_charts' => $reportFaunaEvolutionCharts,
    ])->render();

    expect($html)->toContain('Evolución del control de fauna')
        ->and($html)->toContain('id="report-chart-fauna-period"')
        ->and($html)->toContain('id="report-chart-fauna-historical"')
        ->and($html)->toContain('report-charts-config')
        ->and($html)->toContain('requestAnimationFrame')
        ->and($html)->toContain('data-report-chart-canvas-wrap')
        ->and($html)->toContain('data-chart-height="260"')
        ->and($html)->toContain('height: 260px');
});

it('includes fauna evolution page in single sector single bird template', function (): void {
    $source = file_get_contents(resource_path('pdf-report-templates/single_sector_single_bird.blade.php'));

    expect($source)->toBeString()
        ->and($source)->toContain('Evolución del control de fauna')
        ->and($source)->toContain('report-fauna-evolution-page')
        ->and($source)->not->toContain("@include('pdf.partials.report-fauna-evolution-page')")
        ->and($source)->not->toContain("@include('pdf.partials.report-line-charts')");
});
