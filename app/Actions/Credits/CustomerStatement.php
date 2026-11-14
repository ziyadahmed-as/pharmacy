<?php

namespace App\Actions\Credits;

use App\Enums\Role;
use App\Models\CreditAccount;
use App\Models\CreditPayment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Generate a customer credit statement for a date range.
 *
 * @uc UC-26
 *
 * @fr FR-6.4
 */
class CustomerStatement
{
    /**
     * @return array{account: CreditAccount, transactions: Collection, opening_balance: int, closing_balance: int}
     */
    public function execute(User $actor, CreditAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if (! $actor->hasAnyRole([Role::PharmacyManager, Role::Owner])) {
            throw new AuthorizationException(__('credits.staff_no_access'));
        }

        $from = $from ?? $account->created_at->startOfDay();
        $to = $to ?? now()->endOfDay();

        // Opening balance: sum of charges minus payments before the period
        $chargesBefore = CreditPayment::where('credit_account_id', $account->id)
            ->where('direction', 'charge')
            ->where('created_at', '<', $from)
            ->sum('amount');

        $paymentsBefore = CreditPayment::where('credit_account_id', $account->id)
            ->where('direction', 'payment')
            ->where('created_at', '<', $from)
            ->sum('amount');

        $openingBalance = $chargesBefore - $paymentsBefore;

        // Transactions in the period
        $transactions = CreditPayment::where('credit_account_id', $account->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        // Closing balance
        $chargesInPeriod = $transactions->where('direction', 'charge')->sum('amount');
        $paymentsInPeriod = $transactions->where('direction', 'payment')->sum('amount');
        $closingBalance = $openingBalance + $chargesInPeriod - $paymentsInPeriod;

        return [
            'account' => $account->load('customer'),
            'transactions' => $transactions,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
        ];
    }
}
