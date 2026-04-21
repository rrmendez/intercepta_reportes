<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Report</title>
    <style>
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
    <h1>Intercepta Monthly Report</h1>
    <p><strong>Client:</strong> {{ $client->name }}</p>
    <p><strong>Period:</strong> {{ $periodLabel }}</p>
    <p><strong>Generated at:</strong> {{ $report->generated_at?->format('Y-m-d H:i') }}</p>

    @if ($template)
        <div class="section">
            <h2>Template: {{ $template->name }}</h2>
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

    <div class="section">
        <h2>Summary</h2>
        <p><strong>Total visits:</strong> {{ $visits->count() }}</p>
        <p><strong>Total observations:</strong> {{ $visitReports->count() }}</p>
        <p><strong>Total quantity:</strong> {{ $visitReports->sum('quantity') }}</p>
    </div>

    <div class="section">
        <h2>Quantity by Bird Type</h2>
        <table>
            <thead>
                <tr>
                    <th>Bird Type</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($aggregations['totals_by_bird_type'] as $name => $total)
                    <tr>
                        <td>{{ $name ?: 'N/A' }}</td>
                        <td>{{ $total }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="muted">No data</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Quantity by Location</h2>
        <table>
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($aggregations['totals_by_location'] as $name => $total)
                    <tr>
                        <td>{{ $name ?: 'N/A' }}</td>
                        <td>{{ $total }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="muted">No data</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
