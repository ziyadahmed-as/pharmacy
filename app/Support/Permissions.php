<?php

namespace App\Support;

use App\Enums\Role;

/**
 * Permission registry seeded from the BRD permission matrix (section 4.3).
 *
 * Dotted permission names, grouped by module. Only Stage 1 permissions are
 * `active`; later stages flip the flag by adding their module here, so a
 * later use case can never be reached before it exists.
 *
 * Decision 0001: spatie/laravel-permission could not be installed in the build
 * environment (no Packagist access). This registry exposes the same
 * contract (`can('x.y')`, role to permission map) so it can be swapped.
 */
final class Permissions
{
    /**
     * permission => [module, active, roles]
     *
     * @return array<string, array{module: string, active: bool, roles: list<Role>}>
     */
    public static function matrix(): array
    {
        $SA = Role::SystemAdmin;
        $OW = Role::Owner;
        $PM = Role::PharmacyManager;
        $PS = Role::PharmacyStaff;

        return [
            // --- Administration (Stage 1, active) ---
            'users.manage' => ['module' => 'administration', 'active' => true, 'roles' => [$SA]],
            'users.view' => ['module' => 'administration', 'active' => true, 'roles' => [$SA, $OW, $PM]],
            'users.recover' => ['module' => 'administration', 'active' => true, 'roles' => [$SA]],
            'approvals.request' => ['module' => 'administration', 'active' => true, 'roles' => [$SA, $OW, $PM, $PS]],
            'approvals.process' => ['module' => 'administration', 'active' => true, 'roles' => [$OW, $PM]],
            'audit.view' => ['module' => 'administration', 'active' => true, 'roles' => [$OW, $PM]],
            'audit.view.technical' => ['module' => 'administration', 'active' => true, 'roles' => [$SA]],
            'audit.export' => ['module' => 'administration', 'active' => true, 'roles' => [$OW, $PM, $SA]],
            'settings.view' => ['module' => 'administration', 'active' => true, 'roles' => [$SA, $OW, $PM]],
            'settings.edit' => ['module' => 'administration', 'active' => true, 'roles' => [$SA]],
            // --- Staff (Stage 1, active) ---
            'staff.manage' => ['module' => 'staff', 'active' => true, 'roles' => [$OW, $PM]],
            'staff.view.own' => ['module' => 'staff', 'active' => true, 'roles' => [$PS]],
            // --- Catalogue & Prices (Stage 2) ---
            'catalogue.manage' => ['module' => 'catalogue', 'active' => true, 'roles' => [$OW, $PM]],
            'catalogue.edit_basic' => ['module' => 'catalogue', 'active' => true, 'roles' => [$OW, $PM, $PS]],
            'catalogue.view' => ['module' => 'catalogue', 'active' => true, 'roles' => [$OW, $PM, $PS]],
            'price.set' => ['module' => 'catalogue', 'active' => true, 'roles' => [$OW, $PM]],
            'price.view' => ['module' => 'catalogue', 'active' => true, 'roles' => [$OW, $PM, $PS]],
            // --- Inventory & Receipts (Stage 2) ---
            'stock.receive' => ['module' => 'inventory', 'active' => true, 'roles' => [$PM, $PS, $OW]],
            'stock.receipt.confirm' => ['module' => 'inventory', 'active' => true, 'roles' => [$PM, $OW]],
            'stock.view' => ['module' => 'inventory', 'active' => true, 'roles' => [$PM, $PS, $OW]],
            'cost.view' => ['module' => 'inventory', 'active' => true, 'roles' => [$PM, $OW]],
            'stock.adjust.request' => ['module' => 'inventory', 'active' => true, 'roles' => [$PM, $PS, $OW]],
            'stock.stocktake' => ['module' => 'inventory', 'active' => true, 'roles' => [$PM, $PS, $OW]],
            'stock.dispose' => ['module' => 'inventory', 'active' => true, 'roles' => [$PM, $PS, $OW]],
            // --- Sales & Shifts (Stage 3) ---
            'shifts.open' => ['module' => 'sales', 'active' => true, 'roles' => [$PM, $PS, $OW]],
            'shifts.view' => ['module' => 'sales', 'active' => true, 'roles' => [$PM, $PS, $OW]],
            'tills.manage' => ['module' => 'sales', 'active' => true, 'roles' => [$PM, $OW]],
            'sales.sell' => ['module' => 'sales', 'active' => false, 'roles' => [$PM, $PS]],
            'sales.void.request' => ['module' => 'sales', 'active' => true, 'roles' => [$PM, $PS]],
            // --- Customers & Prescriptions (Stage 4) ---
            'customers.manage' => ['module' => 'customers', 'active' => true, 'roles' => [$PS, $PM, $OW]],
            'customers.view' => ['module' => 'customers', 'active' => true, 'roles' => [$PS, $PM, $OW]],
            'customers.health.view' => ['module' => 'customers', 'active' => true, 'roles' => [$PS, $PM, $OW]],
            'prescriptions.register' => ['module' => 'prescriptions', 'active' => true, 'roles' => [$PS, $PM, $OW]],
            'prescriptions.verify' => ['module' => 'prescriptions', 'active' => true, 'roles' => [$PS, $PM, $OW]],
            'prescriptions.dispense' => ['module' => 'prescriptions', 'active' => true, 'roles' => [$PS, $PM, $OW]],
            'prescriptions.view' => ['module' => 'prescriptions', 'active' => true, 'roles' => [$PS, $PM, $OW]],
            'controlled_drugs.view' => ['module' => 'prescriptions', 'active' => true, 'roles' => [$OW, $PM]],
            'controlled_drugs.export' => ['module' => 'prescriptions', 'active' => true, 'roles' => [$OW, $PM]],
            'backup.run' => ['module' => 'administration', 'active' => false, 'roles' => [$SA]],
            'reports.financial' => ['module' => 'reports', 'active' => false, 'roles' => [$OW, $PM]],
        ];
    }

    /** @return list<string> */
    public static function activeNames(): array
    {
        return array_keys(array_filter(self::matrix(), fn ($p) => $p['active']));
    }

    /** @return list<string> */
    public static function forRole(Role $role, bool $activeOnly = true): array
    {
        $out = [];
        foreach (self::matrix() as $name => $def) {
            if ($activeOnly && ! $def['active']) {
                continue;
            }
            if (in_array($role, $def['roles'], true)) {
                $out[] = $name;
            }
        }

        return $out;
    }
}
