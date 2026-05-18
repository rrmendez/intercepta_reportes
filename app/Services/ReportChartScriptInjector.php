<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Embebe el bundle Vite de Chart.js como script inline para documentos PDF autocontenidos.
 */
final class ReportChartScriptInjector
{
    public function inlineBundle(): string
    {
        $path = $this->resolveBuiltScriptPath();

        if ($path === null) {
            throw new RuntimeException(
                'No se encontro el bundle de graficos. Ejecuta: npm install && npm run build'
            );
        }

        $contents = File::get($path);

        return '<script>'.$contents.'</script>';
    }

    public function hasBuiltBundle(): bool
    {
        return $this->resolveBuiltScriptPath() !== null;
    }

    private function resolveBuiltScriptPath(): ?string
    {
        $manifestPath = public_path('build/manifest.json');

        if (! is_file($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            return null;
        }

        $entry = $manifest['resources/js/report-pdf-charts.js']['file'] ?? null;

        if (! is_string($entry) || $entry === '') {
            return null;
        }

        $absolute = public_path('build/'.$entry);

        return is_file($absolute) ? $absolute : null;
    }
}
