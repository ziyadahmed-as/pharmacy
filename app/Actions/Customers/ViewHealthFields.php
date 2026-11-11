<?php

namespace App\Actions\Customers;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\CustomerHealthView;
use App\Models\User;
use App\Services\ActingRole;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Access sensitive customer health fields with mandatory access tracking.
 *
 * @uc UC-25
 *
 * @fr FR-5.6
 *
 * @ac AC-26
 */
class ViewHealthFields
{
    /**
     * @return array{allergies: ?string, chronic_conditions: ?string, consent_given: bool, consent_at: ?string}
     *
     * @throws AuthorizationException
     */
    public function execute(User $user, Customer $customer, ?string $ipAddress = null): array
    {
        // 1. Authorisation: SA is strictly forbidden; Only roles with customers.health.view
        if ($user->hasRole(Role::SystemAdmin) || ! $user->hasPermission('customers.health.view')) {
            throw new AuthorizationException(__('customers.health_view_unauthorized'));
        }

        $actingRole = ActingRole::for($user)?->value ?? $user->roles->first()?->slug;

        // 2. Log access to customer_health_views table (FR-5.6, AC-26)
        CustomerHealthView::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'role_used' => $actingRole,
            'viewed_at' => now(),
            'ip_address' => $ipAddress,
        ]);

        // 3. Log to immutable central audit trail (E2)
        AuditLogger::record(
            action: 'customer.health.viewed',
            subject: $customer,
            old: [],
            new: [],
            metadata: [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'role_used' => $actingRole,
            ],
            user: $user,
            description: "Sensitive health fields for {$customer->name} viewed by {$user->name}"
        );

        return [
            'allergies' => $customer->allergies,
            'chronic_conditions' => $customer->chronic_conditions,
            'consent_given' => $customer->consent_given,
            'consent_at' => $customer->consent_at?->toIso8601String(),
        ];
    }
}
