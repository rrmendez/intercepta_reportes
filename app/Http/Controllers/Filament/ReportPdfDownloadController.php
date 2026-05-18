<?php

declare(strict_types=1);

namespace App\Http\Controllers\Filament;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class ReportPdfDownloadController
{
    public function __invoke(Request $request, Report $report): Response
    {
        Gate::authorize('view', $report);

        $path = $report->generated_file_path;

        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $report->generated_filename);
    }
}
