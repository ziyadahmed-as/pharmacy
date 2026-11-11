<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('sales.refund_receipt') }} - {{ $refund->refund_no }}</title>
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
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .badge {
            display: inline-block;
            font-weight: 900;
            font-size: 12px;
            letter-spacing: 2px;
            border: 2px solid #000;
            padding: 2px 8px;
            margin: 4px 0 2px 0;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 6px;
            font-size: 10px;
        }
        .meta-table td {
            padding: 1px 0;
        }
        .meta-table .label {
            color: #333;
            width: 40%;
        }
        .meta-table .val {
            font-weight: 700;
            text-align: right;
            font-family: monospace;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }
        .items-table th {
            font-size: 9px;
            text-transform: uppercase;
            padding: 3px 0;
            border-bottom: 1px solid #000;
        }
        .items-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: 700; }
        .total-row {
            font-size: 13px;
            font-weight: 900;
            border-top: 1px solid #000;
            padding-top: 4px;
            margin-top: 4px;
        }
        .footer {
            border-top: 1px dashed #000;
            padding-top: 6px;
            margin-top: 8px;
            text-align: center;
            font-size: 9px;
        }
        .actions {
            margin-bottom: 12px;
            text-align: center;
        }
        .btn {
            background: #0d9488;
            color: white;
            border: none;
            padding: 6px 14px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        .btn-secondary {
            background: #64748b;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0 4px; }
        }
    </style>
</head>
<body>
    <div class="actions no-print">
        <button class="btn" onclick="window.print()">{{ __('sales.print_receipt') }}</button>
        <a href="{{ route('pos.sale') }}" class="btn btn-secondary">{{ __('sales.return_to_pos') }}</a>
    </div>

    <div class="header">
        <div class="pharmacy-name">{{ setting('pharmacy.name', 'PMS Pharmacy') }}</div>
        <div>{{ setting('pharmacy.address', 'Addis Ababa, Ethiopia') }}</div>
        <div>Tel: {{ setting('pharmacy.phone', '+251 11 000 0000') }}</div>
        <div class="badge">REFUND / ተመላሽ</div>
    </div>

    <table class="meta-table">
        <tr>
            <td class="label">{{ __('sales.refund_number') }}:</td>
            <td class="val">{{ $refund->refund_no }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('sales.original_receipt') }}:</td>
            <td class="val">{{ $refund->sale->receipt_no }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('sales.date_time') }}:</td>
            <td class="val">{{ $refund->completed_at ? $refund->completed_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('sales.served_by') }}:</td>
            <td class="val">{{ $refund->user->name }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('sales.till') }}:</td>
            <td class="val">{{ $refund->shift->till->name }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('sales.method') }}:</td>
            <td class="val">{{ ucfirst(str_replace('_', ' ', $refund->payment_method)) }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="text-align: left; width: 50%;">{{ __('sales.item') }}</th>
                <th class="text-right" style="width: 15%;">{{ __('sales.qty') }}</th>
                <th class="text-right" style="width: 35%;">{{ __('sales.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($refund->lines as $line)
                <tr>
                    <td>
                        <div class="font-bold">{{ $line->saleLine->product?->brand ?? 'Item' }}</div>
                        <div style="font-size: 8px; color: #555;">
                            Batch: {{ $line->batch->batch_no }} · {{ $line->isResaleable() ? 'Resaleable' : 'Quarantined' }}
                        </div>
                    </td>
                    <td class="text-right font-mono">{{ $line->quantity }}</td>
                    <td class="text-right font-mono font-bold">{{ $line->subtotalEtb() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-row">
        <table style="width: 100%;">
            <tr>
                <td>TOTAL REFUND / ጠቅላላ ተመላሽ:</td>
                <td class="text-right font-mono">{{ $refund->totalRefundEtb() }} ETB</td>
            </tr>
        </table>
    </div>

    @if ($refund->reason)
        <div style="margin-top: 6px; font-size: 9px; border-top: 1px dotted #ccc; padding-top: 4px;">
            <strong>{{ __('common.reason') }}:</strong> {{ $refund->reason }}
        </div>
    @endif

    <div class="footer">
        <div>{{ __('sales.refund_footer_note', ['app' => 'PMS v1.0']) }}</div>
    </div>
</body>
</html>

