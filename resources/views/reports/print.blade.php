<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $definition->name() }} - {{ $pharmacyName }}</title>
    <style>
        @media print {
            @page { margin: 12mm; size: A4 landscape; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 10pt; color: #111; }
            .no-print { display: none !important; }
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 11pt; color: #222; margin: 20px; line-height: 1.4; }
        .header { border-bottom: 2px solid #0d9488; padding-bottom: 12px; margin-bottom: 16px; }
        .title { font-size: 18pt; font-weight: bold; margin: 0 0 4px 0; color: #0f766e; }
        .subtitle { font-size: 10pt; color: #555; margin: 0; }
        .meta-bar { display: flex; justify-content: space-between; font-size: 9pt; color: #666; margin-top: 8px; }
        .summary-grid { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
        .summary-card { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; min-width: 120px; background: #f8fafc; }
        .summary-label { font-size: 8pt; text-transform: uppercase; color: #64748b; margin-bottom: 2px; }
        .summary-val { font-size: 12pt; font-weight: bold; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 9pt; }
        th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; font-weight: 600; }
        td { border: 1px solid #e2e8f0; padding: 5px 8px; }
        tr:nth-child(even) { background: #f8fafc; }
        .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #cbd5e1; font-size: 8pt; color: #64748b; text-align: right; }
        .print-btn { background: #0d9488; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px;">
        <button class="print-btn" onclick="window.print()">{{ __('reports.print_report') }}</button>
    </div>

    <div class="header">
        <h1 class="title">{{ $pharmacyName }}</h1>
        <p class="subtitle">{{ $definition->name() }} — {{ $definition->description() }}</p>
        <div class="meta-bar">
            <div>
                <strong>{{ __('reports.generated_at') }}:</strong> {{ $generatedAt }} |
                <strong>{{ __('reports.generated_by') }}:</strong> {{ $generatedBy }}
            </div>
            <div>
                @if (! empty($filters['start_date']))
                    <strong>{{ __('reports.period') }}:</strong> {{ $filters['start_date'] }} {{ __('reports.to') }} {{ $filters['end_date'] ?? $filters['start_date'] }}
                @endif
            </div>
        </div>
    </div>

    @if (! empty($summary))
        <div class="summary-grid">
            @foreach ($summary as $key => $val)
                <div class="summary-card">
                    <div class="summary-label">{{ str_replace('_', ' ', $key) }}</div>
                    <div class="summary-val">{{ $val }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}" style="text-align: center; padding: 20px; color: #94a3b8;">
                        {{ __('reports.no_data_for_period') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $pharmacyName }} • {{ __('reports.title') }}
    </div>
</body>
</html>

