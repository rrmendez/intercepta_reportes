<?php

declare(strict_types=1);

namespace App\Services;

use App\ClientImportMode;
use App\Models\Client;

final class ReportPdfTemplateDefaults
{
    private const int MAX_INCLUDE_EXPANSION_DEPTH = 25;

    /**
     * Partials kept as @include in the editor (styles / head bulk).
     *
     * @var list<string>
     */
    private const array EDITOR_COLLAPSED_PARTIALS = [
        'single-sector-single-bird-head',
        'single-sector-multi-bird-head',
        'multi-sector-multi-bird-head',
    ];

    public static function editableBladeSource(string $source): string
    {
        return self::expandPartialIncludes($source, self::MAX_INCLUDE_EXPANSION_DEPTH, self::EDITOR_COLLAPSED_PARTIALS);
    }

    public static function basicExpandedSourceForClient(Client $client): string
    {
        $mode = $client->import_mode ?? ClientImportMode::MultiSectorSingleBird;

        return self::basicExpandedSourceForMode($mode);
    }

    public static function basicExpandedSourceForMode(ClientImportMode $mode): string
    {
        return self::expandPartialIncludes(
            self::bladeSourceForMode($mode),
            self::MAX_INCLUDE_EXPANSION_DEPTH,
            self::EDITOR_COLLAPSED_PARTIALS,
        );
    }

    public static function expandPartialIncludes(
        string $source,
        int $maxDepth = self::MAX_INCLUDE_EXPANSION_DEPTH,
        array $except = [],
    ): string {
        $pattern = '/@include\s*\(\s*(["\'])pdf\.partials\.([^"\']+)\1(?:\s*,\s*\[(?:[^\[\]]|\[[^\]]*\])*\])?\s*\)/s';

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            $expanded = preg_replace_callback(
                $pattern,
                static function (array $matches) use ($except): string {
                    if (in_array($matches[2], $except, true)) {
                        return $matches[0];
                    }

                    return self::partialSource($matches[2]);
                },
                $source,
            );

            if (! is_string($expanded) || $expanded === $source) {
                break;
            }

            $source = $expanded;
        }

        return $source;
    }

    public static function bladeSourceForClient(Client $client): string
    {
        $mode = $client->import_mode ?? ClientImportMode::MultiSectorSingleBird;

        return self::bladeSourceForMode($mode);
    }

    public static function bladeSourceForMode(ClientImportMode $mode): string
    {
        $specific = resource_path("pdf-report-templates/{$mode->value}.blade.php");

        if (is_readable($specific)) {
            return (string) file_get_contents($specific);
        }

        $default = resource_path('pdf-report-templates/default.blade.php');

        if (is_readable($default)) {
            return (string) file_get_contents($default);
        }

        return <<<'BLADE'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte</title>
    <style>
        html, body { margin: 0; background: #ffffff; }
        body { font-family: sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 20px; }
    </style>
</head>
<body>
    @include('pdf.partials.report-cover-page')

    @include('pdf.partials.report-contact-page')

    @include('pdf.partials.report-pdf-fixed-footer', [
        'client' => $client,
        'report' => $report,
        'period_label' => $period_label,
    ])
</body>
</html>
BLADE;
    }

    public static function suggestedName(Client $client): string
    {
        $mode = $client->import_mode ?? ClientImportMode::MultiSectorSingleBird;

        return self::suggestedNameForMode($mode);
    }

    public static function suggestedNameForMode(ClientImportMode $mode): string
    {
        return match ($mode) {
            ClientImportMode::SingleSectorSingleBird => 'Plantilla PDF - sector unico / ave unica',
            ClientImportMode::SingleSectorMultiBird => 'Plantilla PDF - sector unico / multiples aves',
            ClientImportMode::MultiSectorSingleBird => 'Plantilla PDF - multiples sectores / ave unica',
            ClientImportMode::MultiSectorMultiBird => 'Plantilla PDF - multiples sectores / multiples aves',
        };
    }

    private static function partialSource(string $partial): string
    {
        $partial = trim($partial);

        if ($partial === '' || str_contains($partial, '..') || str_contains($partial, '/')) {
            return "@include('pdf.partials.{$partial}')";
        }

        $path = resource_path("views/pdf/partials/{$partial}.blade.php");

        if (! is_readable($path)) {
            return "@include('pdf.partials.{$partial}')";
        }

        return self::stripBladeComments(rtrim((string) file_get_contents($path)));
    }

    private static function stripBladeComments(string $source): string
    {
        $stripped = preg_replace('/\{\{--(?:[^-]|-(?!-\}))*--\}\}/s', '', $source);

        return is_string($stripped) ? $stripped : $source;
    }
}
