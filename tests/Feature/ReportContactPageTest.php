<?php

it('renders the contact closing page with brand and contact details', function (): void {
    $html = view('pdf.partials.report-contact-page')->render();

    expect($html)->toContain('report-contact-page')
        ->and($html)->toContain('report-contact-page__center')
        ->and($html)->toContain('report-contact-page__chrome-mask')
        ->and($html)->toContain('vertical-align: middle')
        ->and($html)->not->toContain('report-contact-page__gold-line')
        ->and($html)->not->toContain('report-contact-page__brand-row')
        ->and($html)->toContain('CONTACTO')
        ->and($html)->toContain('interceptauruguay.com.uy')
        ->and($html)->toContain('mmaier@interceptauruguay.com.uy')
        ->and($html)->toContain('Cel.: 094 421 287')
        ->and($html)->toContain('Ext.: (+598) 94 421 287')
        ->and($html)->toContain('Intercepta Uruguay')
        ->and($html)->toContain('page-break-before: always');
});

it('includes the contact page in the single sector single bird template', function (): void {
    $source = file_get_contents(resource_path('pdf-report-templates/single_sector_single_bird.blade.php'));

    expect($source)->toBeString()
        ->and($source)->toContain('CONTACTO')
        ->and($source)->toContain('report-contact-page')
        ->and($source)->not->toContain("@include('pdf.partials.report-contact-page')")
        ->and($source)->not->toContain("@include('pdf.partials.report-pdf-blank-pages')")
        ->and($source)->toContain('Cel.: 094 421 287')
        ->and($source)->not->toContain('$contacto_celular');
});
