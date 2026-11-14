<?php

namespace App\Actions\Credits;

use App\Enums\Role;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Set or update the credit limit for a customer.
 *
 * @uc UC-26
 *
 * @fr FR-5.3, FR-6.4
 */
class SetCreditLimit
{
    public function execute(User $actor, Customer $customer, int $limitSantim, ?string $notes = null): CreditAccount
    {
        if (! $actor->hasAnyRole([Role::PharmacyManager, Role::Owner])) {
            throw new AuthorizationException(__('credits.staff_no_access'));
        }

        if ($limitSantim <= 0) {
            throw new \InvalidArgumentException(__('credits.limit_must_be_positive'));
        }

        return DB::transaction(function () use ($actor, $customer, $limitSantim, $notes) {
            $account = CreditAccount::where('customer_id', $customer->id)->lockForUpdate()->first();

            $old = $account ? ['credit_limit' => $account->credit_limit] : [];

            if ($account) {
                $account->update([
                    'credit_limit' => $limitSantim,
                    'notes' => $notes,
                    'updated_by' => $actor->id,
                ]);
            } else {
                $account = CreditAccount::create([
                    'customer_id' => $customer->id,
                    'credit_limit' => $limitSantim,
                    'balance' => 0,
                    'notes' => $notes,
                    'created_by' => $actor->id,
                ]);
            }

            AuditLogger::record(
                action: 'credit.limit.set',
                subject: $account,
                old: $old,
                new: ['credit_limit' => $limitSantim],
                metadata: [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                ],
                user: $actor,
                description: 'Credit limit set to '.number_format($limitSantim / 100, 2)." ETB for {$customer->name}"
            );

            return $account->refresh();
        });
    }
}
