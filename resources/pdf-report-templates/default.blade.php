<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte mensual</title>
    <style>
        html, body { margin: 0; background: #ffffff; }
        body { font-family: DejaVu Sans, sans-serif, system-ui, sans-serif; font-size: 12px; color: #111827; }
        h1, h2, h3 { margin: 0 0 8px 0; }
        h1 { font-size: 20px; }
        h2 { font-size: 16px; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        .muted { color: #6b7280; }
        .section { margin-top: 14px; }
    </style>
</head>
<body>
    @include('pdf.partials.report-cover-page')

    @include('pdf.partials.report-initial-situation-page')

    @include('pdf.partials.report-objective-methodology-page')

    @include('pdf.partials.report-line-charts')

    @include('pdf.partials.report-contact-page')

    @include('pdf.partials.report-pdf-fixed-footer', [
        'client' => $client,
        'report' => $report,
        'period_label' => $period_label,
    ])
</body>
</html>
