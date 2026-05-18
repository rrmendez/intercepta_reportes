<?php

declare(strict_types=1);

namespace App\Services;

use App\ClientImportMode;
use App\Models\Client;

final class ReportPdfTemplateDefaults
{
    public static function editableBladeSource(string $source): string
    {
        return self::expandEditableContentPageIncludes($source);
    }

    public static function bladeSourceForClient(Client $client): string
    {
        $mode = $client->import_mode ?? ClientImportMode::MultiSectorSingleBird;
        $specific = resource_path("pdf-report-templates/{$mode->value}.blade.txt");

        if (is_readable($specific)) {
            return (string) file_get_contents($specific);
        }

        $default = resource_path('pdf-report-templates/default.blade.txt');

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

    @include('pdf.partials.report-pdf-blank-pages')

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

        return match ($mode) {
            ClientImportMode::SingleSectorSingleBird => 'Plantilla PDF - sector unico / ave unica',
            ClientImportMode::SingleSectorMultiBird => 'Plantilla PDF - sector unico / multiples aves',
            ClientImportMode::MultiSectorSingleBird => 'Plantilla PDF - multiples sectores / ave unica',
            ClientImportMode::MultiSectorMultiBird => 'Plantilla PDF - multiples sectores / multiples aves',
        };
    }

    private static function expandEditableContentPageIncludes(string $source): string
    {
        $partials = [
            'report-initial-situation-page',
            'report-objective-methodology-page',
        ];

        foreach ($partials as $partial) {
            $source = preg_replace_callback(
                '/^[ \t]*@include\((["\'])pdf\.partials\.'.preg_quote($partial, '/').'\1\)[ \t]*$/m',
                static fn (): string => self::editableContentPageSource($partial),
                $source,
            ) ?? $source;
        }

        return $source;
    }

    private static function editableContentPageSource(string $partial): string
    {
        $path = resource_path("views/pdf/partials/{$partial}.blade.php");

        if (! is_readable($path)) {
            return "@include('pdf.partials.{$partial}')";
        }

        return rtrim((string) file_get_contents($path));
    }
}
