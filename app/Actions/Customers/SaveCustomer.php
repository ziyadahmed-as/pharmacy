<?php

namespace App\Actions\Customers;

use App\Enums\Role;
use App\Exceptions\ConsentRequiredException;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Save (create or update) customer record with consent checking for health data.
 *
 * @uc UC-25
 *
 * @fr FR-5.1, FR-5.6
 *
 * @ac AC-26
 */
class SaveCustomer
{
    /**
     * @param  array{name: string, phone: string, address?: string|null, birth_date?: string|null, consent_given?: bool, allergies?: string|null, chronic_conditions?: string|null}  $data
     *
     * @throws AuthorizationException
     * @throws ConsentRequiredException
     * @throws InvalidArgumentException
     */
    public function execute(User $user, array $data, ?Customer $customer = null): Customer
    {
        // 1. Authorisation: SA cannot manage customers; Staff, PM, Owner can
        if ($user->hasRole(Role::SystemAdmin) || ! $user->hasPermission('customers.manage')) {
            throw new AuthorizationException(__('customers.unauthorized_to_manage'));
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException(__('customers.name_required'));
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        if ($phone === '') {
            throw new InvalidArgumentException(__('customers.phone_required'));
        }

        $birthDate = ! empty($data['birth_date']) ? Carbon::parse($data['birth_date'])->toDateString() : null;
        if ($birthDate && Carbon::parse($birthDate)->isFuture()) {
            throw new InvalidArgumentException(__('customers.birth_date_in_future'));
        }

        $allergies = isset($data['allergies']) ? trim((string) $data['allergies']) : null;
        $chronicConditions = isset($data['chronic_conditions']) ? trim((string) $data['chronic_conditions']) : null;
        $hasHealthData = (! empty($allergies)) || (! empty($chronicConditions));

        $consentGiven = isset($data['consent_given']) ? (bool) $data['consent_given'] : ($customer ? $customer->consent_given : false);

        // E1: Health data requires recorded consent
        if ($hasHealthData && ! $consentGiven) {
            throw new ConsentRequiredException(__('customers.consent_required_for_health_data'));
        }

        return DB::transaction(function () use ($user, $customer, $name, $phone, $data, $birthDate, $consentGiven, $allergies, $chronicConditions) {
            $isNew = $customer === null;

            if ($isNew) {
                $customer = new Customer;
            }

            $oldValues = $isNew ? [] : [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'birth_date' => $customer->birth_date?->toDateString(),
                'consent_given' => $customer->consent_given,
            ];

            $customer->name = $name;
            $customer->phone = $phone;
            $customer->address = isset($data['address']) ? trim((string) $data['address']) : null;
            $customer->birth_date = $birthDate;

            // Update consent timestamp when newly consented
            if ($consentGiven && ! $customer->consent_given) {
                $customer->consent_at = now();
            } elseif (! $consentGiven) {
                $customer->consent_at = null;
            }
            $customer->consent_given = $consentGiven;

            // Only update health data if consent is provided; if consent revoked, clear them
            if ($consentGiven) {
                $customer->allergies = $allergies !== '' ? $allergies : null;
                $customer->chronic_conditions = $chronicConditions !== '' ? $chronicConditions : null;
            } else {
                $customer->allergies = null;
                $customer->chronic_conditions = null;
            }

            $customer->save();

            $newValues = [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'birth_date' => $customer->birth_date?->toDateString(),
                'consent_given' => $customer->consent_given,
            ];

            AuditLogger::record(
                action: $isNew ? 'customer.created' : 'customer.updated',
                subject: $customer,
                old: $oldValues,
                new: $newValues,
                user: $user,
                description: $isNew
                    ? "Customer {$customer->name} created by {$user->name}"
                    : "Customer {$customer->name} updated by {$user->name}"
            );

            return $customer;
        });
    }
}
