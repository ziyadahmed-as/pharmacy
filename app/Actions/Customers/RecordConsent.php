<?php

namespace App\Actions\Customers;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Record or revoke customer consent for health data storage.
 *
 * @uc UC-25
 *
 * @fr FR-5.1, FR-5.6
 *
 * @ac AC-26
 */
class RecordConsent
{
    /**
     * @throws AuthorizationException
     */
    public function execute(User $user, Customer $customer, bool $consentGiven): Customer
    {
        if ($user->hasRole(Role::SystemAdmin) || ! $user->hasPermission('customers.manage')) {
            throw new AuthorizationException(__('customers.unauthorized_to_manage'));
        }

        $oldConsent = $customer->consent_given;
        $customer->consent_given = $consentGiven;
        $customer->consent_at = $consentGiven ? now() : null;

        if (! $consentGiven) {
            $customer->allergies = null;
            $customer->chronic_conditions = null;
        }

        $customer->save();

        AuditLogger::record(
            action: $consentGiven ? 'customer.consent.recorded' : 'customer.consent.revoked',
            subject: $customer,
            old: ['consent_given' => $oldConsent],
            new: ['consent_given' => $consentGiven, 'consent_at' => $customer->consent_at?->toIso8601String()],
            user: $user,
            description: "Consent for customer {$customer->name} was ".($consentGiven ? 'granted' : 'revoked')." by {$user->name}"
        );

        return $customer;
    }
}
