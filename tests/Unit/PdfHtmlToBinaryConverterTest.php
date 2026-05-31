<?php

use Tests\TestCase;

uses(TestCase::class);

it('configures chromium pdf generation to emulate print media', function (): void {
    $path = base_path('app/Services/PdfHtmlToBinaryConverter.php');
    $src = (string) file_get_contents($path);

    expect($src)->toContain("->emulateMedia('print')")
        ->and($src)->toContain("config('services.report_pdf.margins_mm', 12)")
        ->and($src)->toContain("config('services.report_pdf.bottom_margin_mm', 0)")
        ->and($src)->toContain("config('services.report_pdf.chrome_footer_slot_mm', 28)")
        ->and($src)->toContain('$bottomMm = $chromeFooterSlotMm')
        ->and($src)->toContain('->showBrowserHeaderAndFooter()')
        ->and($src)->toContain('->footerHtml($chromeFooterHtml)')
        ->and($src)->toContain('chrome_footer_html')
        ->and($src)->toContain('->margins($sideMarginMm, $sideMarginMm, $bottomMarginMm, $sideMarginMm, \'mm\')')
        ->and($src)->toContain("str_contains(\$html, 'data-report-charts')")
        ->and($src)->toContain('window.__reportChartsReady === true')
        ->and($src)->toContain('chart.chartArea.height > 0')
        ->and($src)->toContain('15_000');
});
