<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shifts.summary_title') }} - Shift #{{ $shift->id }}</title>
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
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
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
            <a href="{{ route('pos.sale') }}" class="btn btn-white">
                {{ __('shifts.return_to_pos') }}
            </a>
            <button onclick="window.print()" class="btn btn-teal">
                {{ __('shifts.print_summary') }}
            </button>
        </div>

        {{-- Summary Card --}}
        <div class="card">
            {{-- Header --}}
            <div class="border-b text-center pb-4 mb-4">
                <h1 style="font-size: 18px; margin: 0; text-transform: uppercase; font-weight: 900;">{{ setting('pharmacy.name', 'PMS Pharmacy') }}</h1>
                <p class="font-bold text-teal" style="margin: 4px 0 0 0;">{{ __('shifts.summary_title') }}</p>
                <p class="font-mono text-slate-500" style="font-size: 11px; margin: 2px 0 0 0;">Shift #{{ $shift->id }} · {{ $shift->till->name }}</p>
            </div>

            {{-- Metadata Grid --}}
            <div class="grid-2 mb-4">
                <div>
                    <span class="text-slate-500" style="font-size: 11px; display: block;">{{ __('sales.served_by') }}</span>
                    <strong style="color: #1e293b;">{{ $shift->user->name }}</strong>
                </div>
                <div>
                    <span class="text-slate-500" style="font-size: 11px; display: block;">{{ __('common.status') }}</span>
                    <strong class="font-mono text-teal" style="text-transform: uppercase;">{{ $shift->status }}</strong>
                </div>
                <div>
                    <span class="text-slate-500" style="font-size: 11px; display: block;">{{ __('shifts.opened_at') }}</span>
                    <span class="font-mono">{{ $shift->opened_at->format('d M Y, H:i') }}</span>
                </div>
                <div>
                    <span class="text-slate-500" style="font-size: 11px; display: block;">Closed At</span>
                    <span class="font-mono">{{ $shift->closed_at ? $shift->closed_at->format('d M Y, H:i') : '—' }}</span>
                </div>
            </div>

            {{-- Float & Cash Movements --}}
            <div class="border-t py-2 mb-4">
                <h2 style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin: 8px 0;">{{ __('shifts.cash_movements') }}</h2>
                <div class="flex justify-between py-2">
                    <span style="color: #475569;">{{ __('shifts.opening_float') }}</span>
                    <span class="font-mono font-bold">{{ number_format($shift->opening_float / 100, 2) }} ETB</span>
                </div>
                @foreach ($shift->cashMovements as $move)
                    <div class="flex justify-between py-2" style="color: #475569; font-size: 12px;">
                        <span>{{ $move->isCashIn() ? '+ Cash In' : '- Cash Out' }} ({{ $move->reason }})</span>
                        <span class="font-mono font-bold {{ $move->isCashIn() ? 'text-teal' : 'text-amber' }}">
                            {{ $move->isCashIn() ? '+' : '-' }}{{ $move->amountEtb() }} ETB
                        </span>
                    </div>
                @endforeach
            </div>

            {{-- Reconciliation Table --}}
            <div class="border-t py-2 mb-4">
                <h2 style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin: 8px 0;">{{ __('shifts.tender_reconciliation') }}</h2>
                <table>
                    <thead>
                        <tr class="border-b">
                            <th>{{ __('sales.method') }}</th>
                            <th class="text-right">{{ __('shifts.expected') }}</th>
                            <th class="text-right">{{ __('shifts.counted') }}</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono">
                        @php
                            $expected = $shift->expected_cash_per_payment_method ?? [];
                            $counted = $shift->counted_cash_per_payment_method ?? [];
                        @endphp
                        @foreach ($expected as $m => $exp)
                            <tr class="border-b">
                                <td style="font-family: inherit; font-weight: 500;">{{ ucfirst(str_replace('_', ' ', $m)) }}</td>
                                <td class="text-right">{{ number_format($exp / 100, 2) }}</td>
                                <td class="text-right font-bold">{{ number_format(($counted[$m] ?? 0) / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid #0f172a;" class="font-mono font-bold">
                            <td style="padding-top: 8px; font-family: inherit; text-transform: uppercase;">{{ __('sales.total') }}</td>
                            <td class="text-right" style="padding-top: 8px;">{{ number_format(array_sum($expected) / 100, 2) }}</td>
                            <td class="text-right text-teal" style="padding-top: 8px;">{{ number_format(array_sum($counted) / 100, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Variance --}}
            <div class="border-t py-2">
                <div class="flex justify-between items-center" style="font-size: 14px; font-weight: 700;">
                    <span>{{ __('shifts.variance') }}:</span>
                    <span class="font-mono {{ ($shift->variance ?? 0) === 0 ? 'text-teal' : (($shift->variance ?? 0) > 0 ? 'text-amber' : 'text-red') }}">
                        {{ ($shift->variance ?? 0) > 0 ? '+' : '' }}{{ $shift->varianceEtb() ?? '0.00' }} ETB
                    </span>
                </div>
                @if ($shift->variance_reason)
                    <div style="margin-top: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 12px;">
                        <strong>{{ __('shifts.variance_reason') }}:</strong> {{ $shift->variance_reason }}
                    </div>
                @endif
            </div>

            {{-- Signatures --}}
            <div class="grid-2 border-t mt-4" style="padding-top: 24px; text-align: center; font-size: 11px;">
                <div>
                    <div style="border-bottom: 1px solid #cbd5e1; height: 32px; margin-bottom: 4px;"></div>
                    <span class="text-slate-500">Staff Signature</span>
                </div>
                <div>
                    <div style="border-bottom: 1px solid #cbd5e1; height: 32px; margin-bottom: 4px;"></div>
                    <span class="text-slate-500">Manager Signature</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
