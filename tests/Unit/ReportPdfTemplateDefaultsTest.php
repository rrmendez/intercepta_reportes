<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Models\Client;
use App\Services\ReportPdfTemplateDefaults;
use Tests\TestCase;

uses(TestCase::class);

it('expands all pdf partial includes recursively', function (): void {
    $source = <<<'BLADE'
<!DOCTYPE html>
<html lang="es"><body>
@include('pdf.partials.report-cover-page')
@include('pdf.partials.report-fauna-evolution-page')
@include('pdf.partials.report-pdf-fixed-footer', [
    'client' => $client,
    'report' => $report,
    'period_label' => $period_label,
])
</body></html>
BLADE;

    $expanded = ReportPdfTemplateDefaults::expandPartialIncludes($source);

    expect($expanded)->toContain('CONTROL BIOLÓGICO DE FAUNA')
        ->and($expanded)->toContain('Evolución del control de fauna')
        ->and($expanded)->toContain('report-pdf-fixed-footer__bar')
        ->and($expanded)->toContain('window.ReportPdfCharts')
        ->and($expanded)->not->toMatch('/^[ \t]*@include\s*\(\s*[\'"]pdf\.partials\./m');
});

it('is idempotent when the source is already expanded', function (): void {
    $partial = (string) file_get_contents(resource_path('views/pdf/partials/report-cover-page.blade.php'));

    $expandedOnce = ReportPdfTemplateDefaults::expandPartialIncludes($partial);
    $expandedTwice = ReportPdfTemplateDefaults::expandPartialIncludes($expandedOnce);

    expect($expandedTwice)->toBe($expandedOnce);
});

it('reads the basic disk template without mutating it', function (): void {
    $client = Client::make([
        'import_mode' => ClientImportMode::SingleSectorSingleBird,
    ]);

    $path = resource_path('pdf-report-templates/single_sector_single_bird.blade.php');
    $before = (string) file_get_contents($path);

    $loaded = ReportPdfTemplateDefaults::bladeSourceForClient($client);
    $after = (string) file_get_contents($path);

    expect($loaded)->toContain('Informe del servicio de control de fauna')
        ->and($loaded)->toContain('Situación inicial del predio')
        ->and($loaded)->toContain("@include('pdf.partials.single-sector-single-bird-head')")
        ->and($loaded)->not->toContain("@include('pdf.partials.report-cover-page')")
        ->and($loaded)->not->toContain("@include('pdf.partials.report-initial-situation-page-single-bird')")
        ->and($after)->toBe($before);
});

it('builds a fully expanded basic source for a client import mode', function (): void {
    $client = Client::make([
        'import_mode' => ClientImportMode::SingleSectorSingleBird,
    ]);

    $expanded = ReportPdfTemplateDefaults::basicExpandedSourceForClient($client);

    expect($expanded)->toContain('Detalles del servicio por lugar de control')
        ->and($expanded)->toContain('Situación actual y conclusiones')
        ->and($expanded)->toContain('Tipo de ave')
        ->and($expanded)->toContain('Población Inicial')
        ->and($expanded)->toContain('El principal objetivo es disminuir la población inicial entre un 80% a un 90%')
        ->and($expanded)->toContain('La metodología a usar es la cetrería')
        ->and($expanded)->toContain('En el estudio del predio a controlar, se constató la presencia')
        ->and($expanded)->toContain('Especies identificadas: Paloma doméstica (Columba livia).')
        ->and($expanded)->toContain('CONTACTO')
        ->and($expanded)->toContain('report-current-situation-and-conclusions-page__signature-image')
        ->and($expanded)->not->toContain('$texto_objetivo')
        ->and($expanded)->not->toContain('$texto_metodologia')
        ->and($expanded)->not->toContain('$texto_situacion_inicial_parrafo_1')
        ->and($expanded)->toContain("@include('pdf.partials.single-sector-single-bird-head')")
        ->and($expanded)->not->toMatch('/^[ \t]*@include\s*\(\s*[\'"]pdf\.partials\.(?!single-sector-single-bird-head)/m');
});

it('keeps the single sector single bird head partial collapsed in the editor', function (): void {
    $source = <<<'BLADE'
<head>
@include('pdf.partials.single-sector-single-bird-head')
</head>
BLADE;

    $expanded = ReportPdfTemplateDefaults::editableBladeSource($source);

    expect($expanded)->toBe($source)
        ->and($expanded)->toContain("@include('pdf.partials.single-sector-single-bird-head')");
});

it('reads the single sector multi bird disk template without mutating it', function (): void {
    $client = Client::make([
        'import_mode' => ClientImportMode::SingleSectorMultiBird,
    ]);

    $path = resource_path('pdf-report-templates/single_sector_multi_bird.blade.php');
    $before = (string) file_get_contents($path);

    $loaded = ReportPdfTemplateDefaults::bladeSourceForClient($client);
    $after = (string) file_get_contents($path);

    expect($loaded)->toContain('Informe del servicio de control de fauna')
        ->and($loaded)->toContain('Situación inicial del predio')
        ->and($loaded)->toContain("@include('pdf.partials.single-sector-multi-bird-head')")
        ->and($loaded)->not->toContain("@include('pdf.partials.report-cover-page')")
        ->and($after)->toBe($before);
});

it('keeps the single sector multi bird head partial collapsed in the editor', function (): void {
    $source = <<<'BLADE'
<head>
@include('pdf.partials.single-sector-multi-bird-head')
</head>
BLADE;

    $expanded = ReportPdfTemplateDefaults::editableBladeSource($source);

    expect($expanded)->toBe($source)
        ->and($expanded)->toContain("@include('pdf.partials.single-sector-multi-bird-head')");
});

it('reads the multi sector multi bird disk template without mutating it', function (): void {
    $client = Client::make([
        'import_mode' => ClientImportMode::MultiSectorMultiBird,
    ]);

    $path = resource_path('pdf-report-templates/multi_sector_multi_bird.blade.php');
    $before = (string) file_get_contents($path);

    $loaded = ReportPdfTemplateDefaults::bladeSourceForClient($client);
    $after = (string) file_get_contents($path);

    expect($loaded)->toContain('Informe del servicio de control de fauna')
        ->and($loaded)->toContain('Situación inicial del predio')
        ->and($loaded)->toContain("@include('pdf.partials.multi-sector-multi-bird-head')")
        ->and($loaded)->not->toContain("@include('pdf.partials.report-cover-page')")
        ->and($after)->toBe($before);
});

it('keeps the multi sector multi bird head partial collapsed in the editor', function (): void {
    $source = <<<'BLADE'
<head>
@include('pdf.partials.multi-sector-multi-bird-head')
</head>
BLADE;

    $expanded = ReportPdfTemplateDefaults::editableBladeSource($source);

    expect($expanded)->toBe($source)
        ->and($expanded)->toContain("@include('pdf.partials.multi-sector-multi-bird-head')");
});
