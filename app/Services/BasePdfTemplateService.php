<?php

declare(strict_types=1);

namespace App\Services;

use App\ClientImportMode;
use RuntimeException;

final class BasePdfTemplateService
{
    public function readEditableSource(ClientImportMode $mode): string
    {
        return ReportPdfTemplateDefaults::editableBladeSource(
            ReportPdfTemplateDefaults::bladeSourceForMode($mode),
        );
    }

    public function save(ClientImportMode $mode, string $pdfTemplate): void
    {
        $path = $this->path($mode);

        if (file_put_contents($path, $pdfTemplate) === false) {
            throw new RuntimeException("No se pudo guardar la plantilla base en {$path}.");
        }
    }

    private function path(ClientImportMode $mode): string
    {
        return resource_path("pdf-report-templates/{$mode->value}.blade.php");
    }
}
