<?php

use App\Services\ReportChartScriptInjector;

it('renders the line charts partial with canvases, config json and chart bundle', function (): void {
    expect(app(ReportChartScriptInjector::class)->hasBuiltBundle())->toBeTrue();

    $reportLineCharts = [
        'charts' => [
            [
                'id' => 'report-chart-bird-type',
                'title' => 'Cantidad por tipo de ave',
                'labels' => ['10/05', '11/05'],
                'datasets' => [
                    [
                        'label' => 'Palomas',
                        'data' => [3, 5],
                        'borderColor' => '#2563eb',
                        'backgroundColor' => '#2563eb',
                        'borderWidth' => 2,
                        'pointRadius' => 2,
                        'tension' => 0.2,
                        'fill' => false,
                    ],
                ],
            ],
            [
                'id' => 'report-chart-location',
                'title' => 'Cantidad por ubicacion',
                'labels' => ['10/05', '11/05'],
                'datasets' => [
                    [
                        'label' => 'Sector A',
                        'data' => [3, 5],
                        'borderColor' => '#dc2626',
                        'backgroundColor' => '#dc2626',
                        'borderWidth' => 2,
                        'pointRadius' => 2,
                        'tension' => 0.2,
                        'fill' => false,
                    ],
                ],
            ],
        ],
    ];

    $html = view('pdf.partials.report-line-charts', [
        'report_line_charts' => $reportLineCharts,
    ])->render();

    expect($html)->toContain('data-report-charts="1"')
        ->and($html)->toContain('id="report-chart-bird-type"')
        ->and($html)->toContain('id="report-chart-location"')
        ->and($html)->toContain('id="report-charts-config"')
        ->and($html)->toContain('"report-chart-bird-type"')
        ->and($html)->toContain('window.ReportPdfCharts.render')
        ->and($html)->toContain('window.__reportChartsReady')
        ->and($html)->toContain('<script>')
        ->and($html)->toContain('Chart');
});

it('shows a muted message when there is no chart data', function (): void {
    $html = view('pdf.partials.report-line-charts', [
        'report_line_charts' => [
            'charts' => [
                ['id' => 'report-chart-bird-type', 'title' => 'Aves', 'labels' => [], 'datasets' => []],
                ['id' => 'report-chart-location', 'title' => 'Ubicaciones', 'labels' => [], 'datasets' => []],
            ],
        ],
    ])->render();

    expect($html)->toContain('Sin datos de visitas para graficar')
        ->and($html)->not->toContain('id="report-chart-bird-type"');
});

it('includes line charts in default pdf template source', function (): void {
    $source = file_get_contents(resource_path('pdf-report-templates/default.blade.php'));

    expect($source)->toBeString()
        ->and($source)->toContain("@include('pdf.partials.report-line-charts')");
});
