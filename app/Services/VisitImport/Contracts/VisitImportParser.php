<?php

namespace App\Services\VisitImport\Contracts;

use App\Services\VisitImport\VisitImportPayload;

interface VisitImportParser
{
    public function parse(string $absolutePath): VisitImportPayload;
}
