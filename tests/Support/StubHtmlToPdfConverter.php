<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\HtmlToPdfConverter;

/**
 * Reemplaza Browsershot en tests para no requerir Node ni puppeteer.
 */
final class StubHtmlToPdfConverter implements HtmlToPdfConverter
{
    public function convert(string $html, array $options = []): string
    {
        $stub = <<<'PDF'
%PDF-1.4
%stub for automated tests (no headless Chrome)
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj trailer<</Root 1 0 R>>%%EOF
PDF;

        return str_pad($stub, 150, ' ');
    }
}
