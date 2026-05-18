<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;

/**
 * Post-procesa el HTML completo del informe antes de pasarlo a Chromium.
 */
final class ReportPdfDocumentHtml
{
    public static function withDefaultHeader(string $html, string $headerHtml): string
    {
        $trimmedHeader = trim($headerHtml);

        if ($trimmedHeader === '' || str_contains($html, 'report-pdf-default-header-root')) {
            return $html;
        }

        if (preg_match('/<body\b[^>]*>/i', $html) !== 1) {
            return $trimmedHeader.$html;
        }

        $injected = preg_replace('/<body\b[^>]*>/i', '$0'.$trimmedHeader, $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Quita el pie incrustado (`report-pdf-fixed-footer`) cuando el PDF usa
     * `footerTemplate` de Chromium para evitar duplicar el pie.
     */
    public static function withoutEmbeddedFixedFooter(string $html): string
    {
        if (! str_contains($html, 'report-pdf-fixed-footer-root')) {
            return $html;
        }

        libxml_use_internal_errors(true);

        $wrapped = str_contains($html, '<?xml') ? $html : '<?xml encoding="UTF-8">'.$html;
        $dom = new DOMDocument;

        if (! $dom->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT)) {
            libxml_clear_errors();

            return $html;
        }

        $footer = $dom->getElementById('report-pdf-fixed-footer-root');
        if ($footer !== null && $footer->parentNode !== null) {
            $footer->parentNode->removeChild($footer);
        }

        $footerStyles = $dom->getElementById('report-pdf-fixed-footer-styles');
        if ($footerStyles !== null && $footerStyles->parentNode !== null) {
            $footerStyles->parentNode->removeChild($footerStyles);
        } else {
            foreach (iterator_to_array($dom->getElementsByTagName('style')) as $styleEl) {
                $text = $styleEl->textContent ?? '';
                if (str_contains($text, 'report-pdf-fixed-footer__bar')) {
                    $styleEl->parentNode?->removeChild($styleEl);
                }
            }
        }

        libxml_clear_errors();

        $root = $dom->documentElement;
        if ($root === null) {
            return $html;
        }

        $saved = $dom->saveHTML($root);

        return is_string($saved) && $saved !== '' ? $saved : $html;
    }
}
