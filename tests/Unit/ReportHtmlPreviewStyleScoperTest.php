<?php

declare(strict_types=1);

use App\Services\ReportHtmlPreviewStyleScoper;
use Tests\TestCase;

uses(TestCase::class);

it('scopes global document selectors to the preview container', function (): void {
    $css = <<<'CSS'
html, body { margin: 0; background: #ffffff; }
body { font-family: DejaVu Sans, sans-serif; }
h1, h2 { margin: 0 0 8px 0; }
.report-cover { padding: 0; }
CSS;

    $scoped = app(ReportHtmlPreviewStyleScoper::class)->scope($css);

    expect($scoped)->toContain('.report-html-preview')
        ->and($scoped)->toContain('margin: 0; background: #ffffff;')
        ->and($scoped)->toContain('font-family: DejaVu Sans, sans-serif;')
        ->and($scoped)->toContain('.report-html-preview h1')
        ->and($scoped)->toContain('.report-html-preview .report-cover')
        ->and($scoped)->not->toMatch('/(?<!\.report-html-preview )body\s*\{/');
});

it('scopes selectors inside media queries', function (): void {
    $css = <<<'CSS'
@media print {
    .report-pdf-default-header {
        position: fixed;
    }
}
CSS;

    $scoped = app(ReportHtmlPreviewStyleScoper::class)->scope($css);

    expect($scoped)->toContain('@media print {')
        ->and($scoped)->toContain('.report-html-preview .report-pdf-default-header')
        ->and($scoped)->toContain('position: fixed;');
});

it('marks scoped style tags for preview html', function (): void {
    $html = '<style>body { color: red; }</style>';

    $scoped = app(ReportHtmlPreviewStyleScoper::class)->scopeStyleTags($html);

    expect($scoped)->toContain('data-report-preview-scoped="1"')
        ->and($scoped)->toContain('.report-html-preview')
        ->and($scoped)->toContain('color: red;');
});
