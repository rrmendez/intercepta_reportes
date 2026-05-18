<?php

use App\Models\Client;
use App\Models\Report;
use App\ReportStatus;
use App\Services\ReportPdfDocumentHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('strips embedded fixed footer root and matching style from full html', function (): void {
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>t</title></head>
<body>
<p>Contenido</p>
<style id="report-pdf-fixed-footer-styles">.report-pdf-fixed-footer__bar { color: red; }</style>
<div id="report-pdf-fixed-footer-root"><span>Pie</span></div>
</body></html>
HTML;

    $out = ReportPdfDocumentHtml::withoutEmbeddedFixedFooter($html);

    expect($out)->not->toContain('report-pdf-fixed-footer-root')
        ->and($out)->not->toContain('report-pdf-fixed-footer__bar')
        ->and($out)->toContain('Contenido');
});

it('injects the default normal page header after the body opening tag', function (): void {
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>t</title></head>
<body class="pdf">
<main>Contenido</main>
</body></html>
HTML;

    $out = ReportPdfDocumentHtml::withDefaultHeader($html, '<div id="report-pdf-default-header-root">Header</div>');

    expect($out)->toContain('<body class="pdf"><div id="report-pdf-default-header-root">Header</div>')
        ->and(substr_count($out, 'report-pdf-default-header-root'))->toBe(1);
});

it('does not duplicate the default normal page header', function (): void {
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="es"><body><div id="report-pdf-default-header-root">Header</div><main>Contenido</main></body></html>
HTML;

    $out = ReportPdfDocumentHtml::withDefaultHeader($html, '<div id="report-pdf-default-header-root">Header nuevo</div>');

    expect(substr_count($out, 'report-pdf-default-header-root'))->toBe(1)
        ->and($out)->not->toContain('Header nuevo');
});

it('renders chrome footer template with meta and absolute image urls', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Chrome Pie',
        'address' => 'Calle 1',
        'active' => true,
    ]);

    $report = Report::query()->create([
        'client_id' => $client->id,
        'month' => 4,
        'year' => 2026,
        'date_from' => '2026-04-01',
        'date_until' => '2026-04-30',
        'status' => ReportStatus::Draft,
    ]);

    $html = view('pdf.partials.report-pdf-chrome-footer-template', [
        'client' => $client,
        'report' => $report,
        'period_label' => 'abril 2026',
    ])->render();

    expect($html)->toContain('Cliente Chrome Pie')
        ->and($html)->toContain('abril 2026')
        ->and($html)->toContain('Informe Nº')
        ->and($html)->toContain((string) $report->id)
        ->and($html)->toContain('transform:translateY(6mm)')
        ->and($html)->toMatch('/data:image\/png;base64,/')
        ->and($html)->toMatch('/data:image\/svg\+xml;base64,/');
});
