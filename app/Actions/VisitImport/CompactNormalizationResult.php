<?php

namespace App\Actions\VisitImport;

use App\Services\VisitImport\VisitImportPayload;

final readonly class CompactNormalizationResult
{
    /**
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $summarySections
     * @param  array<int, string>  $summaryBirdTypes
     */
    public function __construct(
        public VisitImportPayload $payload,
        public array $warnings,
        public array $summarySections,
        public array $summaryBirdTypes,
    ) {}
}
