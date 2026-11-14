<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('cash_revenue.title') }} - {{ $startDate }} to {{ $endDate }}</title>
    <style>
        body {
            font-family: 'Noto Sans Ethiopic', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 24px;
            background: #f8fafc;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .banner {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #92400e;
        }
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .text-emerald { color: #047857; }
        .text-rose { color: #be123c; }
        .text-amber { color: #b45309; }
        .text-slate-500 { color: #64748b; }
        .border-b { border-bottom: 1px solid #e2e8f0; }
        .py-2 { padding-top: 8px; padding-bottom: 8px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-4 { margin-bottom: 16px; }
        .mt-4 { margin-top: 16px; }
        .flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .items-center { align-items: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; padding: 8px; color: #475569; font-weight: 600; font-size: 11px; text-transform: uppercase; background: #f8fafc; }
        td { padding: 8px; border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        .btn {
            display: inline-flex;
            align-items: center;
            background: #0f172a;
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        @media print {
            body { background: #ffffff; padding: 0; }
            .no-print { display: none !important; }
            .card { border: none; padding: 0; box-shadow: none; margin-bottom: 16px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="flex justify-between items-center mb-4 no-print">
            <a href="javascript:history.back()" style="color: #64748b; text-decoration: none;">&larr; Back to PMS</a>
            <button onclick="window.print()" class="btn">Print Summary</button>
        </div>

        <div class="card">
            <div class="text-center mb-4">
                <h1 style="margin: 0 0 4px 0; font-size: 20px;">{{ setting('profile.name', 'Pharmacy Management System') }}</h1>
                <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #334155;">{{ __('cash_revenue.title') }}</h2>
                <p style="margin: 4px 0 0 0; color: #64748b; font-size: 12px;">{{ __('cash_revenue.date') }}: {{ $startDate }} @if($startDate !== $endDate) to {{ $endDate }} @endif</p>
            </div>

            <div class="banner">
                <strong>{{ __('cash_revenue.disclaimer_title') }}</strong><br>
                {{ __('cash_revenue.disclaimer_body') }}
            </div>

            <h3 style="font-size: 14px; margin: 16px 0 8px 0; border-bottom: 2px solid #0f172a; padding-bottom: 4px;">{{ __('cash_revenue.revenue_summary') }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Count</th>
                        <th class="text-right">Amount ({{ $currency }})</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ __('cash_revenue.gross_sales') }}</td>
                        <td>{{ $revenue['gross_sales_count'] }}</td>
                        <td class="text-right font-mono">{{ number_format($revenue['gross_sales'] / 100, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-rose">{{ __('cash_revenue.refunds') }}</td>
                        <td>{{ $revenue['refunds_count'] }}</td>
                        <td class="text-right font-mono text-rose">-{{ number_format($revenue['refunds'] / 100, 2) }}</td>
                    </tr>
                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td>{{ __('cash_revenue.net_sales') }}</td>
                        <td>-</td>
                        <td class="text-right font-mono">{{ number_format($revenue['net_sales'] / 100, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-rose">{{ __('cash_revenue.operating_expenses') }}</td>
                        <td>{{ $revenue['expenses_count'] }}</td>
                        <td class="text-right font-mono text-rose">-{{ number_format($revenue['expenses'] / 100, 2) }}</td>
                    </tr>
                    <tr style="background: #f1f5f9; font-weight: 700; font-size: 14px;">
                        <td>{{ __('cash_revenue.operating_surplus') }}</td>
                        <td>-</td>
                        <td class="text-right font-mono {{ $revenue['operating_surplus'] >= 0 ? 'text-emerald' : 'text-rose' }}">
                            {{ number_format($revenue['operating_surplus'] / 100, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-slate-500">{{ __('cash_revenue.voids') }}</td>
                        <td class="text-slate-500">{{ $revenue['voids_count'] }}</td>
                        <td class="text-right font-mono text-slate-500">{{ number_format($revenue['voids'] / 100, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <h3 style="font-size: 14px; margin: 24px 0 8px 0; border-bottom: 2px solid #0f172a; padding-bottom: 4px;">{{ __('cash_revenue.cash_summary') }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('cash_revenue.payment_method') }}</th>
                        <th class="text-right">{{ __('cash_revenue.received') }}</th>
                        <th class="text-right">{{ __('cash_revenue.refunded') }}</th>
                        <th class="text-right">{{ __('cash_revenue.net_cash') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cash['methods'] as $m)
                        <tr>
                            <td>{{ $m['label'] }}</td>
                            <td class="text-right font-mono">{{ number_format($m['received'] / 100, 2) }}</td>
                            <td class="text-right font-mono {{ $m['refunded'] > 0 ? 'text-rose' : '' }}">{{ number_format($m['refunded'] / 100, 2) }}</td>
                            <td class="text-right font-mono font-bold">{{ number_format($m['net'] / 100, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td>{{ __('cash_revenue.total') }}</td>
                        <td class="text-right font-mono">{{ number_format($cash['total_received'] / 100, 2) }}</td>
                        <td class="text-right font-mono text-rose">{{ number_format($cash['total_refunded'] / 100, 2) }}</td>
                        <td class="text-right font-mono text-emerald font-bold">{{ number_format($cash['total_net'] / 100, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <h3 style="font-size: 14px; margin: 24px 0 8px 0; border-bottom: 2px solid #0f172a; padding-bottom: 4px;">{{ __('cash_revenue.reconciliation_title') }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('cash_revenue.date') }}</th>
                        <th>{{ __('cash_revenue.closer') }}</th>
                        <th class="text-right">{{ __('cash_revenue.system_total') }}</th>
                        <th class="text-right">{{ __('cash_revenue.counted_total') }}</th>
                        <th class="text-right">{{ __('cash_revenue.variance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cash['locked_days'] as $ld)
                        <tr>
                            <td>{{ $ld['date'] }}</td>
                            <td>{{ $ld['closer'] }}</td>
                            <td class="text-right font-mono">{{ number_format($ld['system_total'] / 100, 2) }}</td>
                            <td class="text-right font-mono">{{ number_format($ld['counted_total'] / 100, 2) }}</td>
                            <td class="text-right font-mono font-bold {{ $ld['total_variance'] === 0 ? 'text-emerald' : 'text-rose' }}">
                                {{ number_format($ld['total_variance'] / 100, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-slate-500 py-2">{{ __('cash_revenue.no_locked_days') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

