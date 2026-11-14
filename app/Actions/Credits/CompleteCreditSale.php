<?php

namespace App\Actions\Credits;

use App\Models\CreditAccount;
use App\Models\CreditPayment;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Charge a completed sale to the customer's credit account.
 *
 * Called from within CompleteSale's transaction when payment method is 'credit'.
 *
 * @uc UC-26
 *
 * @fr FR-5.3
 */
class CompleteCreditSale
{
    public function execute(User $actor, Sale $sale, CreditAccount $account): CreditPayment
    {
        return DB::transaction(function () use ($actor, $sale, $account) {
            $account = CreditAccount::lockForUpdate()->findOrFail($account->id);

            if (! $account->hasRoom($sale->total)) {
                throw new \RuntimeException(__('credits.credit_limit_exceeded', [
                    'available' => number_format($account->availableCredit() / 100, 2),
                    'requested' => number_format($sale->total / 100, 2),
                ]));
            }

            $account->increment('balance', $sale->total);
            $account->update(['updated_by' => $actor->id]);

            $creditPayment = CreditPayment::create([
                'credit_account_id' => $account->id,
                'sale_id' => $sale->id,
                'amount' => $sale->total,
                'direction' => 'charge',
                'method' => 'credit',
                'user_id' => $actor->id,
                'notes' => "Sale {$sale->receipt_no}",
            ]);

            AuditLogger::record(
                action: 'credit.sale.charged',
                subject: $creditPayment,
                old: ['balance' => $account->balance - $sale->total],
                new: ['balance' => $account->balance],
                metadata: [
                    'sale_id' => $sale->id,
                    'receipt_no' => $sale->receipt_no,
                    'amount_etb' => number_format($sale->total / 100, 2),
                    'customer_id' => $account->customer_id,
                ],
                user: $actor,
                description: "Credit sale {$sale->receipt_no} charged ".number_format($sale->total / 100, 2).' ETB'
            );

            return $creditPayment;
        });
    }
}
