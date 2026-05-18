<?php

declare(strict_types=1);

use App\Services\ReportPdfPublicImageDataUri;
use Tests\TestCase;

uses(TestCase::class);

it('returns a png data uri for a public png file', function (): void {
    $uri = ReportPdfPublicImageDataUri::fromRelativePublicPath('images/birdlife.png');

    expect($uri)->toBeString()
        ->and($uri)->toStartWith('data:image/png;base64,')
        ->and(strlen($uri))->toBeGreaterThan(100);
});

it('returns an svg data uri for a public svg file', function (): void {
    $uri = ReportPdfPublicImageDataUri::fromRelativePublicPath('images/auc.svg');

    expect($uri)->toBeString()
        ->and($uri)->toStartWith('data:image/svg+xml;base64,');
});

it('returns null for a missing path', function (): void {
    expect(ReportPdfPublicImageDataUri::fromRelativePublicPath('images/no-existe.png'))->toBeNull();
});
