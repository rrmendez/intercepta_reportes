<?php

declare(strict_types=1);

namespace App\Http\Controllers\Filament;

use App\Models\Report;
use App\Services\GenerateMonthlyReportPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class ReportPdfDownloadController
{
    public function __invoke(Request $request, Report $report, GenerateMonthlyReportPdfService $reports): Response
    {
        Gate::authorize('view', $report);

        $path = $report->generated_file_path;

        if (filled($path) && Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->response($path, $report->generated_filename, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        $pdfBinary = $reports->renderReportPdfBinary($report);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$report->generated_filename.'"',
        ]);
    }
}
