<?php

declare(strict_types=1);

namespace App\Http\Controllers\Filament;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class ComposeReportPdfPreviewController
{
    public function __invoke(Request $request, string $token): Response
    {
        /** @var int|string|null $authId */
        $authId = $request->user()?->getAuthIdentifier();
        $userId = is_numeric($authId) ? (int) $authId : 0;

        abort_if($userId <= 0, 403);
        abort_unless(preg_match('/^[a-z0-9]{40}$/', $token) === 1, 404);

        $disk = Storage::disk('local');
        $path = "pdf-previews/{$userId}-{$token}.pdf";

        abort_unless($disk->exists($path), 404);

        if ($disk->lastModified($path) < now()->subMinutes(10)->getTimestamp()) {
            $disk->delete($path);

            abort(404);
        }

        $binary = $disk->get($path);
        $disk->delete($path);

        abort_if(! is_string($binary) || $binary === '', 404);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa.pdf"',
        ]);
    }
}
