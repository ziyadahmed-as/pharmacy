<?php

namespace App\Actions\Credits;

use App\Enums\Role;
use App\Models\CreditAccount;
use App\Models\CreditPayment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

/**
 * Generate receivables ageing report with 0-30, 31-60, 61-90, 90+ day buckets.
 *
 * @uc UC-26
 *
 * @fr FR-6.4
 */
class ReceivablesAgeing
{
    public function execute(User $actor): Collection
    {
        if (! $actor->hasAnyRole([Role::PharmacyManager, Role::Owner])) {
            throw new AuthorizationException(__('credits.staff_no_access'));
        }

        $accounts = CreditAccount::with('customer')
            ->where('balance', '>', 0)
            ->get();

        return $accounts->map(function (CreditAccount $account) {
            $oldestCharge = CreditPayment::where('credit_account_id', $account->id)
                ->where('direction', 'charge')
                ->orderBy('created_at', 'asc')
                ->first();

            $daysSinceOldest = $oldestCharge
                ? (int) $oldestCharge->created_at->diffInDays(now())
                : 0;

            $bucket = match (true) {
                $daysSinceOldest <= 30 => 'current',
                $daysSinceOldest <= 60 => '31_60',
                $daysSinceOldest <= 90 => '61_90',
                default => '90_plus',
            };

            return [
                'account_id' => $account->id,
                'customer_id' => $account->customer_id,
                'customer_name' => $account->customer->name,
                'customer_phone' => $account->customer->phone,
                'credit_limit' => $account->credit_limit,
                'balance' => $account->balance,
                'days_outstanding' => $daysSinceOldest,
                'bucket' => $bucket,
            ];
        });
    }
}
