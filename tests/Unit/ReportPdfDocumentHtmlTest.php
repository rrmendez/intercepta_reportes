<?php

declare(strict_types=1);

use App\Services\ReportPdfDocumentHtml;
use Tests\TestCase;

uses(TestCase::class);

it('prepares print documents with header and without embedded footer', function (): void {
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="es"><body>
<div id="report-pdf-fixed-footer-root">Footer</div>
<p>Contenido</p>
</body></html>
HTML;

    $prepared = ReportPdfDocumentHtml::preparePrintDocument($html, '<div id="report-pdf-default-header-root">Header</div>');

    expect($prepared)->toContain('report-pdf-default-header-root')
        ->and($prepared)->toContain('Contenido')
        ->and($prepared)->not->toContain('report-pdf-fixed-footer-root');
});
