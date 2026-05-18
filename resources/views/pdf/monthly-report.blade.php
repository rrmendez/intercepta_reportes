<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte mensual</title>
    <style>
        html, body { margin: 0; background: #ffffff; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
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
    @if (filled($renderedTemplateContent ?? null))
        <div class="section">
            {!! $renderedTemplateContent !!}
        </div>
    @elseif ($template)
        <div class="section">
            <h2>Plantilla: {{ $template->name }}</h2>
            @if (filled($template->content))
                <div>{!! $template->content !!}</div>
            @endif
        </div>

        @foreach ($sections as $section)
            <div class="section">
                <h3>{{ $section->title }}</h3>
                <div>{!! $section->text !!}</div>
            </div>
        @endforeach
    @endif
</body>
</html>
