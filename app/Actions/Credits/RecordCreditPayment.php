<?php

namespace App\Actions\Credits;

use App\Enums\Role;
use App\Models\CreditAccount;
use App\Models\CreditPayment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentSequence;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Record a payment against a customer's credit account (collection).
 *
 * @uc UC-26
 *
 * @fr FR-6.4
 */
class RecordCreditPayment
{
    public function execute(
        User $actor,
        CreditAccount $account,
        int $amountSantim,
        string $method,
        ?string $reference = null,
        ?string $notes = null
    ): CreditPayment {
        if (! $actor->hasAnyRole([Role::PharmacyManager, Role::Owner])) {
            throw new AuthorizationException(__('credits.staff_no_access'));
        }

        if ($amountSantim <= 0) {
            throw new \InvalidArgumentException(__('credits.payment_amount_invalid'));
        }

        return DB::transaction(function () use ($actor, $account, $amountSantim, $method, $reference, $notes) {
            $account = CreditAccount::lockForUpdate()->findOrFail($account->id);

            if ($amountSantim > $account->balance) {
                throw new \RuntimeException(__('credits.payment_exceeds_balance'));
            }

            $receiptNo = DocumentSequence::next('credit_payment', 'CPR');

            $account->decrement('balance', $amountSantim);
            $account->update(['updated_by' => $actor->id]);

            $creditPayment = CreditPayment::create([
                'credit_account_id' => $account->id,
                'amount' => $amountSantim,
                'direction' => 'payment',
                'method' => $method,
                'receipt_no' => $receiptNo,
                'reference' => $reference,
                'user_id' => $actor->id,
                'notes' => $notes,
            ]);

            AuditLogger::record(
                action: 'credit.payment.recorded',
                subject: $creditPayment,
                old: ['balance' => $account->balance + $amountSantim],
                new: ['balance' => $account->balance],
                metadata: [
                    'receipt_no' => $receiptNo,
                    'method' => $method,
                    'amount_etb' => number_format($amountSantim / 100, 2),
                    'customer_id' => $account->customer_id,
                ],
                user: $actor,
                description: "Credit payment {$receiptNo} received: ".number_format($amountSantim / 100, 2).' ETB'
            );

            return $creditPayment;
        });
    }
}
