<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Codifica archivos bajo `public/` como data URI para el `footerTemplate` de Chromium,
 * donde las URLs http(s) suelen no resolverse de forma fiable.
 */
final class ReportPdfPublicImageDataUri
{
    /**
     * @param  non-empty-string  $relativePublicPath  p. ej. `images/birdlife.png`
     */
    public static function fromRelativePublicPath(string $relativePublicPath): ?string
    {
        $path = public_path($relativePublicPath);
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        $binary = file_get_contents($path);
        if ($binary === false || $binary === '') {
            return null;
        }

        $lower = strtolower($relativePublicPath);
        $mime = match (true) {
            str_ends_with($lower, '.svg') => 'image/svg+xml',
            str_ends_with($lower, '.png') => 'image/png',
            str_ends_with($lower, '.jpg'), str_ends_with($lower, '.jpeg') => 'image/jpeg',
            str_ends_with($lower, '.webp') => 'image/webp',
            str_ends_with($lower, '.gif') => 'image/gif',
            default => 'application/octet-stream',
        };

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }
}
