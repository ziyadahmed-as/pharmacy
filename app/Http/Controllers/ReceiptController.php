<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    /**
     * Show the 80mm thermal receipt for a sale.
     * Reprints are marked COPY and audited (E4).
     */
    public function show(Request $request, Sale $sale): View
    {
        $sale->load(['lines.product', 'lines.batch', 'lines.unit', 'payments', 'shift.till', 'user']);

        $isReprint = $sale->reprint_count > 0 || $request->boolean('reprint');

        if ($request->boolean('reprint')) {
            $sale->increment('reprint_count');

            AuditLogger::record(
                action: 'sale.reprinted',
                subject: $sale,
                old: ['reprint_count' => $sale->reprint_count - 1],
                new: ['reprint_count' => $sale->reprint_count],
                metadata: [
                    'receipt_no' => $sale->receipt_no,
                ],
                user: $request->user(),
                description: "Receipt {$sale->receipt_no} reprinted (copy #{$sale->reprint_count})"
            );
        }

        return view('sales.receipt', [
            'sale' => $sale,
            'isReprint' => $isReprint,
        ]);
    }
}
