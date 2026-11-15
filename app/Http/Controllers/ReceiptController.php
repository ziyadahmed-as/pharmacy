<?php

namespace App\Http\Controllers;

use App\Models\ReceiptPrintLog;
use App\Models\Sale;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    /**
     * Show the 80mm thermal receipt for a sale.
     * Reprints are marked COPY, logged in receipt_print_logs, and audited (E4).
     */
    public function show(Request $request, Sale $sale): View
    {
        $sale->load(['lines.product', 'lines.batch', 'lines.unit', 'payments', 'shift.till', 'user']);

        $user = $request->user();

        // Check if there are existing print logs or reprint_count > 0
        $existingPrintsCount = $sale->receiptPrintLogs()->count();
        $isReprintRequested = $request->boolean('reprint') || $request->filled('reprint_reason');
        $isReprint = $sale->reprintCount() > 0 || $existingPrintsCount > 0 || $isReprintRequested;

        if ($isReprintRequested) {
            $reason = trim((string) $request->input('reason', $request->input('reprint_reason', 'Customer requested duplicate copy')));
            if ($reason === '') {
                $reason = 'Customer requested duplicate copy';
            }

            // 1. Create ReceiptPrintLog
            ReceiptPrintLog::create([
                'sale_id' => $sale->id,
                'user_id' => $user?->id ?? $sale->user_id,
                'printed_at' => now(),
                'is_reprint' => true,
                'reason' => $reason,
            ]);

            // 2. Increment reprint_count on sale
            $sale->increment('reprint_count');

            // 3. Audit log
            AuditLogger::record(
                action: 'sale.reprinted',
                subject: $sale,
                old: ['reprint_count' => $sale->reprint_count - 1],
                new: ['reprint_count' => $sale->reprint_count],
                metadata: [
                    'receipt_no' => $sale->receipt_no,
                    'reason' => $reason,
                ],
                user: $user,
                description: "Receipt {$sale->receipt_no} reprinted (copy #{$sale->reprint_count}): {$reason}"
            );
        } else {
            // First print log if none exists yet
            if ($existingPrintsCount === 0 && $sale->reprint_count === 0) {
                ReceiptPrintLog::create([
                    'sale_id' => $sale->id,
                    'user_id' => $user?->id ?? $sale->user_id,
                    'printed_at' => now(),
                    'is_reprint' => false,
                    'reason' => null,
                ]);
            }
        }

        return view('sales.receipt', [
            'sale' => $sale,
            'isReprint' => $isReprint,
        ]);
    }
}
