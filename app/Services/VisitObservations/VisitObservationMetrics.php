<?php

declare(strict_types=1);

namespace App\Services\VisitObservations;

final readonly class VisitObservationMetrics
{
    public function __construct(
        public int $nestsRemoved,
        public int $capturesFalconry,
        public int $capturesTrap,
    ) {}
}
