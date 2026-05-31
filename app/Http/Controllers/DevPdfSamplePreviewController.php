<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\ClientImportMode;
use App\Contracts\HtmlToPdfConverter;
use App\Services\DevPdfReportSample;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportPdfDocumentHtml;
use App\Services\ReportPdfTemplateDefaults;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Vista previa del PDF con datos fijos (solo entorno local).
 *
 * GET /dev/pdf-sample — HTML (por defecto)
 * GET /dev/pdf-sample?template=single_sector_single_bird — plantilla una zona / una ave (Conaprole)
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

        $mode = ClientImportMode::tryFrom((string) $request->query('template', ''))
            ?? ClientImportMode::SingleSectorSingleBird;

        $sample = DevPdfReportSample::build($mode);
        $client = $sample['client'];
        $report = $sample['report'];
        $period = $sample['period'];
        $periodLabel = (string) $period['period_label'];

        $blade = ReportPdfTemplateDefaults::bladeSourceForClient($client);

        $html = $bladeRenderer->renderDocument($blade, $client, $report, $period);

        $debugMargins = $request->boolean('debug_margins');
        if ($debugMargins) {
            $html = $this->injectDocumentMarginDebugCss($html);
        }

        if ($request->query('mode') !== 'pdf') {
            $html = $this->injectDevPreviewToolbar($html, $mode, $debugMargins);
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
                'Content-Disposition' => 'inline; filename="muestra-informe-'.$mode->value.'.pdf"',
            ]);
        }

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function injectDevPreviewToolbar(string $html, ClientImportMode $mode, bool $debugMargins): string
    {
        $query = [
            'template' => $mode->value,
        ];

        if ($debugMargins) {
            $query['debug_margins'] = '1';
        }

        $htmlUrl = route('dev.pdf-sample', $query);
        $pdfUrl = route('dev.pdf-sample', array_merge($query, ['mode' => 'pdf']));
        $debugUrl = route('dev.pdf-sample', array_merge($query, ['debug_margins' => '1']));

        $templateOptions = collect(ClientImportMode::cases())
            ->map(function (ClientImportMode $option) use ($mode, $debugMargins): string {
                $params = ['template' => $option->value];
                if ($debugMargins) {
                    $params['debug_margins'] = '1';
                }
                $url = route('dev.pdf-sample', $params);
                $selected = $option === $mode ? ' selected' : '';

                return '<option value="'.e($url).'"'.$selected.'>'.e($option->label()).'</option>';
            })
            ->implode('');

        $toolbar = <<<HTML
<div id="dev-pdf-preview-toolbar" style="position:fixed;top:0;left:0;right:0;z-index:99999;display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:10px 14px;background:#111827;color:#f9fafb;font:13px/1.4 system-ui,sans-serif;border-bottom:2px solid #e8b14c;box-shadow:0 4px 16px rgba(0,0,0,.25);">
    <strong style="margin-right:4px;">Dev PDF</strong>
    <label style="display:flex;align-items:center;gap:6px;">
        <span>Plantilla</span>
        <select onchange="window.location.href=this.value" style="padding:4px 8px;border-radius:4px;border:1px solid #374151;background:#1f2937;color:#f9fafb;max-width:280px;">{$templateOptions}</select>
    </label>
    <a href="{$htmlUrl}" style="color:#fbbf24;">HTML</a>
    <a href="{$pdfUrl}" target="_blank" rel="noopener" style="color:#fbbf24;">PDF</a>
    <a href="{$debugUrl}" style="color:#93c5fd;">Márgenes debug</a>
    <span style="opacity:.75;margin-left:auto;">Referencia: Informe final una zona un ave.pdf</span>
</div>
HTML;

        return $this->insertAfterOpeningBody($html, $toolbar.'<div style="display:block;height:52px;" aria-hidden="true"></div>');
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

    private function insertAfterOpeningBody(string $html, string $injection): string
    {
        if (preg_match('/<body[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $tag = $matches[0][0];
            $position = $matches[0][1] + strlen($tag);

            return substr($html, 0, $position).$injection.substr($html, $position);
        }

        return $injection.$html;
    }
}
