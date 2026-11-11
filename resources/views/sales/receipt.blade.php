<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('sales.receipt') }} - {{ $sale->receipt_no }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        body {
            font-family: 'Noto Sans Ethiopic', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px;
            line-height: 1.35;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 8px 6px;
            width: 80mm;
            max-width: 80mm;
            box-sizing: border-box;
        }
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }
        .pharmacy-name {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 9px;
            color: #333;
        }
        .reprint-badge {
            display: block;
            margin: 4px auto;
            padding: 2px 4px;
            border: 2px solid #000;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            letter-spacing: 1px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 6px;
            font-size: 10px;
        }
        .meta-table td {
            padding: 1px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            margin: 6px 0;
        }
        .items-table th {
            text-align: left;
            font-size: 10px;
            padding: 4px 1px;
            border-bottom: 1px solid #000;
        }
        .items-table td {
            padding: 3px 1px;
            vertical-align: top;
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-table {
            width: 100%;
            margin-top: 4px;
            font-size: 11px;
        }
        .totals-table td {
            padding: 2px 0;
        }
        .grand-total {
            font-size: 13px;
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }
        .payments-section {
            margin-top: 6px;
            border-top: 1px dashed #000;
            padding-top: 4px;
            font-size: 10px;
        }
        .footer {
            margin-top: 8px;
            text-align: center;
            font-size: 9px;
            border-top: 1px dashed #000;
            padding-top: 6px;
        }
        .no-print {
            margin-bottom: 10px;
            text-align: center;
        }
        .print-btn {
            background: #0f766e;
            color: #fff;
            padding: 6px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 4px 2px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">{{ __('sales.print_receipt') }}</button>
        <a href="{{ route('pos.sale') }}" style="margin-left: 8px; font-size: 11px; color: #0f766e; text-decoration: underline;">{{ __('sales.return_to_pos') }}</a>
    </div>

    <div class="header">
        <div class="pharmacy-name">{{ setting('profile.name') ?: config('app.name', 'Pharmacy Management System') }}</div>
        @if (setting('profile.address'))
            <div class="subtitle">{{ setting('profile.address') }}</div>
        @endif
        @if (setting('profile.phone'))
            <div class="subtitle">Tel: {{ setting('profile.phone') }}</div>
        @endif

        <div style="margin-top: 4px; font-weight: bold;">
            {{ __('sales.operational_receipt') }} / የክወና ደረሰኝ
        </div>
        <div class="subtitle" style="font-style: italic;">
            {{ __('sales.not_a_tax_invoice') }} / የታክስ ደረሰኝ አይደለም
        </div>

        @if ($isReprint)
            <div class="reprint-badge">
                *** {{ __('sales.copy') }} / ቅጂ ***
            </div>
        @endif
    </div>

    <table class="meta-table">
        <tr>
            <td><strong>{{ __('sales.receipt_no') }}:</strong></td>
            <td class="text-right">{{ $sale->receipt_no }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('sales.date_time') }}:</strong></td>
            <td class="text-right">{{ $sale->completed_at->format('Y-m-d H:i') }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('sales.served_by') }}:</strong></td>
            <td class="text-right">{{ $sale->user->name }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('sales.till') }}:</strong></td>
            <td class="text-right">{{ $sale->shift->till->name }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 48%;">{{ __('sales.item') }}</th>
                <th class="text-right" style="width: 14%;">{{ __('sales.qty') }}</th>
                <th class="text-right" style="width: 18%;">{{ __('sales.price') }}</th>
                <th class="text-right" style="width: 20%;">{{ __('sales.subtotal') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->lines as $line)
                <tr>
                    <td>
                        <div><strong>{{ $line->product->brand }}</strong></div>
                        <div class="subtitle">{{ $line->product->generic_name }} ({{ $line->batch->batch_no }})</div>
                    </td>
                    <td class="text-right">
                        {{ $line->quantity }}
                        @if ($line->unit)
                            <div class="subtitle">{{ $line->unit->name }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ $line->unitPriceEtb() }}</td>
                    <td class="text-right"><strong>{{ $line->subtotalEtb() }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr class="grand-total">
            <td style="padding: 4px 0;"><strong>{{ __('sales.total') }} / ድምር (ETB):</strong></td>
            <td class="text-right" style="padding: 4px 0;"><strong>{{ $sale->totalEtb() }}</strong></td>
        </tr>
    </table>

    <div class="payments-section">
        <div><strong>{{ __('sales.payments') }} / ክፍያዎች:</strong></div>
        @foreach ($sale->payments as $payment)
            <div style="display: flex; justify-content: space-between; margin-top: 1px;">
                <span>
                    {{ $payment->methodLabel() }}
                    @if ($payment->reference)
                        <span class="subtitle">({{ $payment->reference }})</span>
                    @endif
                </span>
                <span>{{ $payment->amountEtb() }} ETB</span>
            </div>
        @endforeach
    </div>

    <div class="footer">
        <div>{{ setting('sales.receipt_footer') ?: __('sales.receipt_footer_default') }}</div>
        <div style="margin-top: 4px; font-size: 8px; color: #555;">
            {{ __('sales.fast_accurate_safe') }} • PMS v1.0
        </div>
    </div>
</body>
</html>
