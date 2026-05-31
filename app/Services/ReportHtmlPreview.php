<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Report;
use DOMDocument;
use DOMElement;
use Illuminate\Support\HtmlString;

/**
 * HTML embebido en Filament alineado con el documento que Chromium convierte a PDF.
 */
final class ReportHtmlPreview
{
    public function build(
        string $documentHtml,
        Client $client,
        Report $report,
        string $periodLabel,
    ): string {
        $headerHtml = view('pdf.partials.report-pdf-default-header')->render();
        $footerHtml = view('pdf.partials.report-pdf-chrome-footer-template', [
            'client' => $client,
            'report' => $report,
            'period_label' => $periodLabel,
        ])->render();

        $prepared = ReportPdfDocumentHtml::preparePrintDocument($documentHtml, $headerHtml);
        $styles = app(ReportHtmlPreviewStyleScoper::class)->scopeStyleTags(
            $this->extractStyleBlocks($prepared),
        );
        $bodyHtml = app(ReportBladeStringRenderer::class)->htmlForAdminPreview($prepared);
        $bodyHtml = $this->stripChartExecutionScripts($bodyHtml);
        $bodyHtml = $this->stripStyleBlocks($bodyHtml);
        $bodyHtml = $this->wrapPagesWithPreviewChrome(
            $bodyHtml,
            $this->markupWithoutStyles($headerHtml),
            $this->markupWithoutStyles($footerHtml),
        );

        return $styles.$this->simulationStyles().$bodyHtml;
    }

    public function wrap(string $bodyHtml, int $revision = 0): HtmlString
    {
        return new HtmlString(
            '<div x-ignore data-report-preview-revision="'.$revision.'" class="report-html-preview max-h-[70vh] overflow-auto rounded-xl border border-gray-200 bg-gray-100 p-3 text-gray-950 shadow-sm dark:border-gray-700">'
            .$bodyHtml
            .'</div>',
        );
    }

    /**
     * @deprecated Use {@see build()} to match the PDF document pipeline.
     */
    public function prepareBodyHtml(string $html): string
    {
        $html = app(ReportBladeStringRenderer::class)->htmlForAdminPreview($html);

        return $this->stripChartExecutionScripts($html);
    }

    /**
     * @deprecated Preview styles are included in {@see build()}.
     */
    public function styles(): string
    {
        return $this->simulationStyles();
    }

    public function simulationStyles(): string
    {
        $sideMarginMm = $this->sideMarginMm();
        $footerSlotMm = $this->chromeFooterSlotMm();

        return <<<HTML
<style id="report-html-preview-simulation-styles">
            .report-html-preview {
                background: #e5e7eb;
            }

            .report-html-preview .report-pdf-preview-sheet {
                box-sizing: border-box;
                position: relative;
                width: 210mm;
                min-height: 297mm;
                height: auto;
                margin: 0 auto 16px;
                padding: {$sideMarginMm}mm {$sideMarginMm}mm 0;
                overflow: visible;
                background: #ffffff;
            }

            .report-html-preview .report-pdf-preview-sheet:last-child {
                margin-bottom: 0;
            }

            .report-html-preview .report-pdf-preview-sheet__body {
                box-sizing: border-box;
                min-height: calc(297mm - {$footerSlotMm}mm);
                padding-bottom: {$footerSlotMm}mm;
            }

            .report-html-preview .report-pdf-preview-sheet__body > .report-cover {
                margin-left: calc(-1 * {$sideMarginMm}mm);
                margin-right: calc(-1 * {$sideMarginMm}mm);
                width: calc(100% + (2 * {$sideMarginMm}mm));
            }

            .report-html-preview .report-pdf-preview-sheet__body [class*="-page"] {
                page-break-after: auto !important;
                break-after: auto !important;
                min-height: auto !important;
            }

            .report-html-preview table {
                display: table !important;
                width: 100% !important;
                border-collapse: collapse !important;
            }

            .report-html-preview table thead {
                display: table-header-group !important;
            }

            .report-html-preview table tbody {
                display: table-row-group !important;
            }

            .report-html-preview table tfoot {
                display: table-footer-group !important;
            }

            .report-html-preview table tr {
                display: table-row !important;
            }

            .report-html-preview table th,
            .report-html-preview table td {
                display: table-cell !important;
            }

            .report-html-preview .report-contact-page {
                page-break-before: auto !important;
                break-before: auto !important;
                min-height: calc(297mm - {$footerSlotMm}mm) !important;
            }

            .report-html-preview .report-contact-page__layout {
                display: table !important;
                width: 100% !important;
                height: calc(297mm - {$footerSlotMm}mm) !important;
                min-height: calc(297mm - {$footerSlotMm}mm) !important;
            }

            .report-html-preview .report-contact-page__center {
                display: table-cell !important;
                vertical-align: middle !important;
            }

            .report-html-preview .report-contact-page__chrome-mask {
                display: none !important;
            }

            .report-html-preview .report-pdf-preview-sheet__header {
                margin-bottom: 6mm;
            }

            .report-html-preview .report-pdf-preview-sheet__header .report-pdf-default-header {
                display: flex !important;
                position: relative !important;
                top: auto !important;
                left: auto !important;
                right: auto !important;
                z-index: auto !important;
                box-sizing: border-box;
                align-items: center;
                width: 100%;
                height: 18mm;
                margin: 0;
                padding: 0;
                background: transparent;
            }

            .report-html-preview .report-pdf-preview-sheet__header .report-pdf-default-header__logo {
                display: block;
                flex: 0 0 auto;
                width: auto;
                height: 17mm;
                margin: 0;
                padding: 0;
                border: 0;
                object-fit: contain;
                object-position: left center;
            }

            .report-html-preview .report-pdf-preview-sheet__header .report-pdf-default-header__line {
                flex: 1 1 auto;
                height: 0;
                margin: 0 0 0 4mm;
                padding: 0;
                border: 0;
                border-top: 2px solid #d4a012;
            }

            .report-html-preview .report-pdf-preview-sheet__chrome-footer {
                position: absolute;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 2;
            }

            .report-html-preview .report-fauna-evolution-page__chart-canvas-wrap,
            .report-html-preview [data-report-chart-canvas-wrap] {
                overflow: visible;
            }
        </style>

HTML;
    }

    /**
     * Quita scripts de Chart.js del HTML embebido; la vista previa usa el bundle Vite de la pagina.
     */
    public function stripChartExecutionScripts(string $html): string
    {
        if ($html === '' || ! str_contains($html, '<script')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<script\b[^>]*>[\s\S]*?<\/script>/i',
            static function (array $matches): string {
                $tag = $matches[0];

                if (preg_match('/type\s*=\s*["\']application\/json["\']/i', $tag) === 1) {
                    return $tag;
                }

                return '';
            },
            $html,
        );
    }

    private function wrapPagesWithPreviewChrome(
        string $bodyHtml,
        string $headerMarkup,
        string $footerMarkup,
    ): string {
        $bodyHtml = trim($bodyHtml);

        if ($bodyHtml === '') {
            return '';
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="report-preview-root">'.$bodyHtml.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT,
        );

        libxml_clear_errors();

        if (! $loaded) {
            return $bodyHtml;
        }

        $root = $dom->getElementById('report-preview-root');

        if ($root === null) {
            return $bodyHtml;
        }

        $sheets = '';

        foreach ($root->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->getAttribute('id') === 'report-pdf-default-header-root') {
                continue;
            }

            if (in_array(strtolower($child->tagName), ['style', 'script'], true)) {
                continue;
            }

            $class = $child->getAttribute('class');
            $isCover = str_contains($class, 'report-cover');
            $isContactPage = str_contains($class, 'report-contact-page');
            $sheetHeader = ($isCover || $isContactPage)
                ? ''
                : '<div class="report-pdf-preview-sheet__header">'.$headerMarkup.'</div>';

            $sectionHtml = $dom->saveHTML($child) ?: '';

            if (trim($sectionHtml) === '') {
                continue;
            }

            $sheets .= '<div class="report-pdf-preview-sheet">'
                .$sheetHeader
                .'<div class="report-pdf-preview-sheet__body">'.$sectionHtml.'</div>'
                .'<div class="report-pdf-preview-sheet__chrome-footer">'.$footerMarkup.'</div>'
                .'</div>';
        }

        return $sheets !== '' ? $sheets : $bodyHtml;
    }

    private function extractStyleBlocks(string $html): string
    {
        if (! preg_match_all('/<style\b[^>]*>[\s\S]*?<\/style>/i', $html, $matches)) {
            return '';
        }

        return implode("\n", $matches[0]);
    }

    private function stripStyleBlocks(string $html): string
    {
        if ($html === '' || ! str_contains($html, '<style')) {
            return $html;
        }

        $stripped = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $html);

        return is_string($stripped) ? trim($stripped) : $html;
    }

    private function markupWithoutStyles(string $html): string
    {
        $stripped = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $html);

        return is_string($stripped) ? trim($stripped) : trim($html);
    }

    private function sideMarginMm(): int
    {
        return max(0, min(40, (int) config('services.report_pdf.margins_mm', 12)));
    }

    private function chromeFooterSlotMm(): int
    {
        return max(18, min(55, (int) config('services.report_pdf.chrome_footer_slot_mm', 28)));
    }
}
