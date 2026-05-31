<?php

declare(strict_types=1);

namespace App\Services\BirdTypes;

use Illuminate\Support\Str;

final class BirdTypeTokenNormalizer
{
    public function normalize(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '');
    }
}
