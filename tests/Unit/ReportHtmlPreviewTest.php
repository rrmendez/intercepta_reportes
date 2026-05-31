<?php

declare(strict_types=1);

use App\Services\ReportHtmlPreview;
use Tests\TestCase;

uses(TestCase::class);

it('includes pdf aligned simulation styles for a4 sheets', function (): void {
    $styles = app(ReportHtmlPreview::class)->simulationStyles();

    expect($styles)->toContain('report-pdf-preview-sheet')
        ->and($styles)->toContain('width: 210mm')
        ->and($styles)->toContain('min-height: 297mm')
        ->and($styles)->toContain('report-pdf-default-header')
        ->and($styles)->toContain('display: table-header-group !important')
        ->and($styles)->toContain('display: table-row-group !important');
});

it('strips chart execution scripts but keeps json config in preview html', function (): void {
    $html = <<<'HTML'
<section>
<script type="application/json" id="report-charts-config">{"charts":[]}</script>
<script>window.ReportPdfCharts.render({});</script>
</section>
HTML;

    $stripped = app(ReportHtmlPreview::class)->stripChartExecutionScripts($html);

    expect($stripped)->toContain('id="report-charts-config"')
        ->and($stripped)->not->toContain('ReportPdfCharts.render');
});

it('wraps preview html with revision attribute', function (): void {
    $wrapped = app(ReportHtmlPreview::class)->wrap('<p>Contenido</p>', 7);

    expect($wrapped->toHtml())->toContain('class="report-html-preview')
        ->and($wrapped->toHtml())->toContain('data-report-preview-revision="7"')
        ->and($wrapped->toHtml())->toContain('Contenido');
});
