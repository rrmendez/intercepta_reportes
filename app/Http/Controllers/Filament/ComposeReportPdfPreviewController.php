<?php

declare(strict_types=1);

namespace App\Http\Controllers\Filament;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class ComposeReportPdfPreviewController
{
    public function __invoke(Request $request, string $token): Response
    {
        /** @var int|string|null $authId */
        $authId = $request->user()?->getAuthIdentifier();
        $userId = is_numeric($authId) ? (int) $authId : 0;

        abort_if($userId <= 0, 403);

        $cacheKey = "compose_report_pdf_preview:{$userId}:{$token}";
        $binary = Cache::pull($cacheKey);

        abort_if(! is_string($binary) || $binary === '', 404);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa.pdf"',
        ]);
    }
}
