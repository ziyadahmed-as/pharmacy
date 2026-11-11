<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('closing.report_title') }} - {{ $closing->date }}</title>
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
            max-width: 700px;
            margin: 0 auto;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .text-teal { color: #0f766e; }
        .text-red { color: #b91c1c; }
        .text-amber { color: #b45309; }
        .text-slate-500 { color: #64748b; }
        .border-b { border-bottom: 1px solid #e2e8f0; }
        .border-t { border-top: 1px solid #e2e8f0; }
        .py-2 { padding-top: 8px; padding-bottom: 8px; }
        .mb-4 { margin-bottom: 16px; }
        .mt-4 { margin-top: 16px; }
        .flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .items-center { align-items: center; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; padding: 6px 0; color: #64748b; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        td { padding: 6px 0; }
        .text-right { text-align: right; }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-white { background: #fff; border: 1px solid #cbd5e1; color: #334155; }
        .btn-teal { background: #0d9488; border: none; color: #fff; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            .card { border: none; box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Navigation Actions --}}
        <div class="no-print flex justify-between items-center mb-4">
            <a href="{{ route('closing.day') }}" class="btn btn-white">
                {{ __('closing.back_to_closing') }}
            </a>
            <button onclick="window.print()" class="btn btn-teal">
                {{ __('closing.print_report') }}
            </button>
        </div>

        {{-- Summary Card --}}
        <div class="card">
            {{-- Header --}}
            <div class="border-b text-center pb-4 mb-4">
                <h1 style="font-size: 20px; margin: 0; text-transform: uppercase; font-weight: 900;">{{ setting('pharmacy.name', 'PMS Pharmacy') }}</h1>
                <p class="font-bold text-teal" style="font-size: 14px; margin: 4px 0 0 0;">{{ __('closing.report_title') }}</p>
                <p class="font-mono text-slate-500" style="font-size: 12px; margin: 2px 0 0 0;">Date: {{ $closing->date }} · Status: {{ strtoupper($closing->status) }}</p>
            </div>

            {{-- Metadata Grid --}}
            <div class="grid-2 mb-4">
                <div>
                    <span class="text-slate-500" style="font-size: 11px; display: block;">{{ __('closing.closed_by') }}</span>
                    <strong style="color: #1e293b;">{{ $closing->closer->name }}</strong>
                </div>
                <div>
                    <span class="text-slate-500" style="font-size: 11px; display: block;">{{ __('closing.locked_at') }}</span>
                    <span class="font-mono">{{ $closing->locked_at ? $closing->locked_at->format('d M Y, H:i') : '—' }}</span>
                </div>
            </div>

            {{-- Metric Highlights --}}
            <div class="grid-3 border-t py-2 mb-4 text-center">
                <div style="background: #f8fafc; padding: 12px; border-radius: 8px;">
                    <span class="text-slate-500" style="font-size: 10px; text-transform: uppercase; display: block;">Sales Total</span>
                    <strong class="font-mono text-teal" style="font-size: 14px;">{{ number_format($metrics['sales_total'] / 100, 2) }} ETB</strong>
                </div>
                <div style="background: #f8fafc; padding: 12px; border-radius: 8px;">
                    <span class="text-slate-500" style="font-size: 10px; text-transform: uppercase; display: block;">Refunds Total</span>
                    <strong class="font-mono text-amber" style="font-size: 14px;">{{ number_format($metrics['refunds_total'] / 100, 2) }} ETB</strong>
                </div>
                <div style="background: #f8fafc; padding: 12px; border-radius: 8px;">
                    <span class="text-slate-500" style="font-size: 10px; text-transform: uppercase; display: block;">Voids Total</span>
                    <strong class="font-mono text-red" style="font-size: 14px;">{{ number_format($metrics['voids_total'] / 100, 2) }} ETB</strong>
                </div>
            </div>

            {{-- Shifts Breakdown --}}
            <div class="border-t py-2 mb-4">
                <h2 style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin: 8px 0;">{{ __('closing.shifts_breakdown') }}</h2>
                <table>
                    <thead>
                        <tr class="border-b">
                            <th>{{ __('shifts.till') }}</th>
                            <th>{{ __('sales.served_by') }}</th>
                            <th class="text-right">{{ __('shifts.opening_float') }}</th>
                            <th class="text-right">Counted Cash</th>
                            <th class="text-right">Variance</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono" style="font-size: 12px;">
                        @foreach ($shifts as $s)
                            <tr class="border-b">
                                <td style="font-family: inherit; font-weight: 600;">{{ $s->till->name }}</td>
                                <td style="font-family: inherit;">{{ $s->user->name }}</td>
                                <td class="text-right">{{ number_format($s->opening_float / 100, 2) }}</td>
                                <td class="text-right font-bold">{{ number_format(($s->counted_cash_per_payment_method['cash'] ?? 0) / 100, 2) }}</td>
                                <td class="text-right {{ ($s->variance ?? 0) === 0 ? 'text-teal' : 'text-red' }}">
                                    {{ ($s->variance ?? 0) > 0 ? '+' : '' }}{{ $s->varianceEtb() ?? '0.00' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Tender Reconciliation Table --}}
            <div class="border-t py-2 mb-4">
                <h2 style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin: 8px 0;">{{ __('closing.tender_reconciliation_title') }}</h2>
                <table>
                    <thead>
                        <tr class="border-b">
                            <th>{{ __('sales.method') }}</th>
                            <th class="text-right">{{ __('closing.system_total') }}</th>
                            <th class="text-right">{{ __('closing.counted_actual') }}</th>
                            <th class="text-right">{{ __('closing.variance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono">
                        @php
                            $system = $closing->system_totals_by_method ?? [];
                            $counted = $closing->counted_totals_by_method ?? [];
                            $variances = $closing->variances_by_method ?? [];
                        @endphp
                        @foreach ($system as $m => $sys)
                            @php
                                $cnt = $counted[$m] ?? 0;
                                $var = $variances[$m] ?? 0;
                            @endphp
                            <tr class="border-b">
                                <td style="font-family: inherit; font-weight: 500;">{{ ucfirst(str_replace('_', ' ', $m)) }}</td>
                                <td class="text-right">{{ number_format($sys / 100, 2) }}</td>
                                <td class="text-right font-bold">{{ number_format($cnt / 100, 2) }}</td>
                                <td class="text-right font-bold {{ $var === 0 ? 'text-teal' : ($var > 0 ? 'text-amber' : 'text-red') }}">
                                    {{ $var > 0 ? '+' : '' }}{{ number_format($var / 100, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid #0f172a;" class="font-mono font-bold">
                            <td style="padding-top: 8px; font-family: inherit; text-transform: uppercase;">{{ __('sales.total') }}</td>
                            <td class="text-right" style="padding-top: 8px;">{{ number_format(array_sum($system) / 100, 2) }}</td>
                            <td class="text-right" style="padding-top: 8px;">{{ number_format(array_sum($counted) / 100, 2) }}</td>
                            <td class="text-right text-teal" style="padding-top: 8px;">{{ number_format(array_sum($variances) / 100, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Variance Reasons --}}
            @if (! empty($closing->variance_reasons))
                <div class="border-t py-2 mb-4">
                    <h2 style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin: 8px 0;">Variance Justifications</h2>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 12px;">
                        @foreach ($closing->variance_reasons as $method => $reason)
                            <div style="margin-bottom: 4px;">
                                <strong>{{ ucfirst(str_replace('_', ' ', $method)) }}:</strong> {{ $reason }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Notes --}}
            @if ($closing->notes)
                <div class="border-t py-2 mb-4">
                    <h2 style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin: 8px 0;">Notes</h2>
                    <p style="margin: 0; color: #334155; font-size: 12px;">{{ $closing->notes }}</p>
                </div>
            @endif

            {{-- Signatures --}}
            <div class="grid-2 border-t mt-4" style="padding-top: 28px; text-align: center; font-size: 11px;">
                <div>
                    <div style="border-bottom: 1px solid #cbd5e1; height: 36px; margin-bottom: 4px;"></div>
                    <span class="text-slate-500">Pharmacy Manager Signature</span>
                </div>
                <div>
                    <div style="border-bottom: 1px solid #cbd5e1; height: 36px; margin-bottom: 4px;"></div>
                    <span class="text-slate-500">Owner / Auditor Signature</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

