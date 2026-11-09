<?php

namespace App\Approvals;

use App\Enums\Role;
use App\Models\User;

/**
 * Routing rules of UC-34 step 4, shared by handlers so no handler reinvents them.
 */
final class ApprovalRouter
{
    /**
     * Default routing by the initiator's acting role:
     * PS -> PM, PM -> Owner, Owner -> PM (A2). SA requests are routed by their handler.
     *
     * @return list<Role>
     */
    public static function byInitiator(User $initiator): array
    {
        $roles = $initiator->roleEnums();
        $out = [];

        foreach ($roles as $role) {
            $out = array_merge($out, match ($role) {
                Role::PharmacyStaff => [Role::PharmacyManager],
                Role::PharmacyManager => [Role::Owner],
                Role::Owner => [Role::PharmacyManager],
                Role::SystemAdmin => [],
            });
        }

        return self::unique($out);
    }

    /**
     * Account and role changes raised by the SA: PM approves; the Owner approves when
     * the change concerns the PM; the PM approves when it concerns the Owner.
     *
     * @return list<Role>
     */
    public static function forAccountChange(?User $concerned, ?array $futureRoles = null): array
    {
        $roles = $concerned ? $concerned->roleEnums() : [];
        foreach ($futureRoles ?? [] as $r) {
            $roles[] = $r instanceof Role ? $r : Role::from($r);
        }

        $out = [];
        foreach ($roles as $role) {
            if ($role === Role::PharmacyManager) {
                $out[] = Role::Owner;
            } elseif ($role === Role::Owner) {
                $out[] = Role::PharmacyManager;
            }
        }

        return $out === [] ? [Role::PharmacyManager] : self::unique($out);
    }

    /** Settings and restore requests: PM or Owner. @return list<Role> */
    public static function managementEither(): array
    {
        return [Role::PharmacyManager, Role::Owner];
    }

    /** @param  list<Role>  $roles @return list<Role> */
    private static function unique(array $roles): array
    {
        $seen = [];
        foreach ($roles as $r) {
            $seen[$r->value] = $r;
        }

        return array_values($seen);
    }
}
