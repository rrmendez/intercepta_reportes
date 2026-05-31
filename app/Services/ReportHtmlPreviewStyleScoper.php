<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Limita reglas CSS del informe PDF al contenedor de vista previa en Filament.
 */
final class ReportHtmlPreviewStyleScoper
{
    private const string SCOPE = '.report-html-preview';

    private const int MAX_AT_RULE_PASSES = 32;

    public function scope(string $css): string
    {
        $css = trim($css);

        if ($css === '') {
            return '';
        }

        $css = $this->stripComments($css);
        $css = $this->scopeAtRules($css);

        return trim($this->scopeRules($css));
    }

    /**
     * @param  non-empty-string  $stylesHtml
     */
    public function scopeStyleTags(string $stylesHtml): string
    {
        if (! str_contains($stylesHtml, '<style')) {
            return $stylesHtml;
        }

        return (string) preg_replace_callback(
            '/<style\b([^>]*)>([\s\S]*?)<\/style>/i',
            function (array $matches): string {
                if (str_contains($matches[1], 'data-report-preview-scoped')) {
                    return $matches[0];
                }

                $scopedCss = $this->scope($matches[2]);

                return '<style data-report-preview-scoped="1"'.$matches[1].'>'.$scopedCss.'</style>';
            },
            $stylesHtml,
        );
    }

    private function scopeAtRules(string $css): string
    {
        $pattern = '/@(media|supports|layer)\s([^{]+)\{((?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})*)\}/s';

        for ($pass = 0; $pass < self::MAX_AT_RULE_PASSES; $pass++) {
            $next = preg_replace_callback(
                $pattern,
                function (array $matches): string {
                    $inner = $this->scopeRules($this->scopeAtRules($matches[3]));

                    return '@'.$matches[1].' '.$matches[2].'{'.$inner.'}';
                },
                $css,
            );

            if (! is_string($next) || $next === $css) {
                break;
            }

            $css = $next;
        }

        return $css;
    }

    private function scopeRules(string $css): string
    {
        $output = '';
        $length = strlen($css);
        $offset = 0;

        while ($offset < $length) {
            if (preg_match('/\s+/A', $css, $whitespace, 0, $offset) === 1) {
                $output .= $whitespace[0];
                $offset += strlen($whitespace[0]);

                continue;
            }

            if ($css[$offset] === '@') {
                if (preg_match('/@[^{]+\{(?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})*\}/s', $css, $atRule, 0, $offset) === 1) {
                    $output .= $atRule[0];
                    $offset += strlen($atRule[0]);

                    continue;
                }

                break;
            }

            if (preg_match('/[^{]+/A', $css, $selectors, 0, $offset) !== 1) {
                break;
            }

            $selectorText = $selectors[0];
            $offset += strlen($selectorText);

            if ($offset >= $length || $css[$offset] !== '{') {
                $output .= $selectorText;

                continue;
            }

            $blockStart = $offset;
            $depth = 0;

            for ($index = $blockStart; $index < $length; $index++) {
                $character = $css[$index];

                if ($character === '{') {
                    $depth++;
                } elseif ($character === '}') {
                    $depth--;

                    if ($depth === 0) {
                        $declarationBlock = substr($css, $blockStart, $index - $blockStart + 1);
                        $output .= $this->scopeSelectors(trim($selectorText)).$declarationBlock;
                        $offset = $index + 1;

                        continue 2;
                    }
                }
            }

            $output .= $selectorText;

            break;
        }

        if ($offset < $length) {
            $output .= substr($css, $offset);
        }

        return $output;
    }

    private function scopeSelectors(string $selectors): string
    {
        $parts = array_map('trim', explode(',', $selectors));

        return implode(', ', array_map(fn (string $selector): string => $this->scopeSelector($selector), $parts));
    }

    private function scopeSelector(string $selector): string
    {
        $selector = trim($selector);

        if ($selector === '') {
            return self::SCOPE;
        }

        if ($selector === self::SCOPE || str_starts_with($selector, self::SCOPE.' ')) {
            return $selector;
        }

        if (in_array($selector, ['html', 'body', ':root'], true)) {
            return self::SCOPE;
        }

        if (preg_match('/^(html|body)\b/u', $selector) === 1) {
            return (string) preg_replace('/^(html|body)\b/u', self::SCOPE, $selector, 1);
        }

        return self::SCOPE.' '.$selector;
    }

    private function stripComments(string $css): string
    {
        $stripped = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);

        return is_string($stripped) ? $stripped : $css;
    }
}
