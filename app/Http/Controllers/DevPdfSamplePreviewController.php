<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\HtmlToPdfConverter;
use App\Models\Client;
use App\Models\Report;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportPdfDocumentHtml;
use App\Services\TemplateRichContent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Vista previa del PDF con datos fijos (solo entorno local).
 *
 * GET /dev/pdf-sample — HTML (por defecto)
 * GET /dev/pdf-sample?mode=pdf — PDF binario (Chromium / Browsershot)
 * GET /dev/pdf-sample?debug_margins=1 — bordes de depuracion (html/body/pie; en PDF tambien el contenedor del footerTemplate)
 */
final class DevPdfSamplePreviewController extends Controller
{
    public function __invoke(
        Request $request,
        ReportBladeStringRenderer $bladeRenderer,
        HtmlToPdfConverter $pdfConverter,
    ): Response {
        abort_unless(app()->isLocal(), 404);

        $client = Client::make([
            'id' => 1,
            'name' => 'Cliente demostración S.A.',
            'address' => 'Av. 18 de Julio 1234, Montevideo',
            'active' => true,
        ]);

        $report = Report::make([
            'id' => 99,
            'client_id' => 1,
            'month' => 1,
            'year' => 2026,
            'date_from' => '2026-01-01',
            'date_until' => '2026-01-31',
        ])->setRelation('client', $client);

        $report->setAttribute('generated_at', CarbonImmutable::parse('2026-05-14 10:30:00'));

        $periodLabel = 'enero 2026';
        $visits = new Collection;
        $visitReports = new Collection;
        $aggregations = TemplateRichContent::aggregations($visitReports);

        $richContentData = array_merge(
            TemplateRichContent::reportData(
                client: $client,
                periodLabel: $periodLabel,
                visits: $visits,
                visitReports: $visitReports,
                aggregations: $aggregations,
                report: $report,
            ),
            [
                'visits_count' => 7,
                'total_observations' => 24,
                'total_quantity' => 156,
                'totals_by_bird_type' => [
                    'Paloma doméstica' => 88,
                    'Gaviota cocinera' => 42,
                ],
                'totals_by_location' => [
                    'Planta industrial — sector norte' => 70,
                    'Planta industrial — sector sur' => 60,
                ],
            ],
        );

        $from = CarbonImmutable::parse('2026-01-01')->startOfDay();
        $until = CarbonImmutable::parse('2026-01-31')->endOfDay();

        $period = [
            'client' => $client,
            'date_from' => $from,
            'date_until' => $until,
            'period_label' => $periodLabel,
            'visits' => $visits,
            'visit_reports' => $visitReports,
            'aggregations' => [
                'totals_by_bird_type' => $richContentData['totals_by_bird_type'],
                'totals_by_location' => $richContentData['totals_by_location'],
            ],
            'rich_content_data' => $richContentData,
            'snapshot' => [
                'period' => $periodLabel,
                'visits_count' => 7,
            ],
        ];

        $bladePath = resource_path('pdf-report-templates/default.blade.txt');
        $blade = is_readable($bladePath) ? (string) file_get_contents($bladePath) : '';

        $html = $bladeRenderer->renderDocument($blade, $client, $report, $period);

        $debugMargins = $request->boolean('debug_margins');
        if ($debugMargins) {
            $html = $this->injectDocumentMarginDebugCss($html);
        }

        $chromeFooterHtml = view('pdf.partials.report-pdf-chrome-footer-template', [
            'client' => $client,
            'report' => $report,
            'period_label' => $periodLabel,
        ])->render();

        if ($debugMargins) {
            $chromeFooterHtml = $this->injectChromeFooterMarginDebugOutline($chromeFooterHtml);
        }

        $documentHtml = ReportPdfDocumentHtml::withDefaultHeader(
            ReportPdfDocumentHtml::withoutEmbeddedFixedFooter($html),
            view('pdf.partials.report-pdf-default-header')->render(),
        );

        if ($request->query('mode') === 'pdf') {
            return response($pdfConverter->convert($documentHtml, [
                'chrome_footer_html' => $chromeFooterHtml,
            ]), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="muestra-informe.pdf"',
            ]);
        }

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Inserta CSS de depuracion antes de </head>: bordes en html/body y pie embebido; sombra interior en body para ver padding.
     */
    private function injectDocumentMarginDebugCss(string $html): string
    {
        $block = <<<'HTML'
<style id="dev-pdf-margin-debug">
/* Leyenda: amarillo tenue = fondo html | morado = borde html | verde = borde body | azul interior = box-shadow inset (marca padding del body) | naranja = pie embebido */
html {
    outline: 4px solid #a21caf !important;
    outline-offset: -4px;
    background: #fef9c3 !important;
}
body {
    outline: 4px solid #15803d !important;
    outline-offset: -4px;
    box-shadow: inset 0 0 0 12px rgba(37, 99, 235, 0.35) !important;
}
#report-pdf-fixed-footer-root {
    outline: 4px solid #c2410c !important;
    outline-offset: -2px;
}
</style>
HTML;

        return $this->insertBeforeClosingHead($html, $block);
    }

    /**
     * Contorno azul en el contenedor raiz del footerTemplate de Chromium.
     */
    private function injectChromeFooterMarginDebugOutline(string $footerHtml): string
    {
        $needle = '<div style="width:100%;height:';

        if (! str_contains($footerHtml, $needle)) {
            return $footerHtml;
        }

        return str_replace(
            $needle,
            '<div style="outline:5px solid #2563eb;outline-offset:-3px;width:100%;height:',
            $footerHtml,
        );
    }

    private function insertBeforeClosingHead(string $html, string $injection): string
    {
        if (preg_match('/<\/head\s*>/i', $html) === 1) {
            $replaced = preg_replace('/<\/head\s*>/i', $injection.'</head>', $html, 1);

            return is_string($replaced) ? $replaced : $html;
        }

        return $injection.$html;
    }
}
