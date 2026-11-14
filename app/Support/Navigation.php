<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar by module; an item shows only when the user holds one of its permissions
 * and its route exists (S0 step 5, AGENTS.md section 9). Groups with no visible item
 * are hidden, so later stages only add rows here.
 */
final class Navigation
{
    /** @return list<array{key: string, items: list<array{label: string, route: string, params?: array}>}> */
    public static function forUser(User $user): array
    {
        $out = [];
        foreach (self::definition() as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if (! Route::has($item['route'])) {
                    continue;
                }
                if ($item['route'] === 'controlled-drugs.register' && ! (bool) setting('pharmacy.handles_controlled_drugs', true)) {
                    continue;
                }
                $allowed = false;
                foreach ($item['any'] as $permission) {
                    if ($user->hasPermission($permission)) {
                        $allowed = true;
                        break;
                    }
                }
                if ($allowed) {
                    $items[] = ['label' => $item['label'], 'route' => $item['route']];
                }
            }
            if ($items !== []) {
                $out[] = ['key' => $group['key'], 'items' => $items];
            }
        }

        return $out;
    }

    /** @return list<array{key: string, items: list<array{label: string, route: string, any: list<string>}>}> */
    public static function definition(): array
    {
        return [
            ['key' => 'catalogue', 'items' => [
                ['label' => 'nav.catalogue', 'route' => 'catalogue.index', 'any' => ['catalogue.view']],
            ]],
            ['key' => 'inventory', 'items' => [
                ['label' => 'nav.stock_alerts', 'route' => 'inventory.alerts', 'any' => ['stock.view']],
                ['label' => 'nav.stock_on_hand', 'route' => 'inventory.stock', 'any' => ['stock.view']],
                ['label' => 'nav.stock_ledger', 'route' => 'inventory.ledger', 'any' => ['stock.view']],
                ['label' => 'nav.quarantine', 'route' => 'inventory.quarantine', 'any' => ['stock.view']],
                ['label' => 'nav.stock_adjustments', 'route' => 'inventory.adjustments.index', 'any' => ['stock.adjust.request']],
                ['label' => 'nav.stocktakes', 'route' => 'inventory.stocktakes.index', 'any' => ['stock.stocktake']],
                ['label' => 'nav.disposals', 'route' => 'inventory.disposals.index', 'any' => ['stock.dispose']],
                ['label' => 'nav.receive_stock', 'route' => 'inventory.receive', 'any' => ['stock.receive']],
            ]],
            ['key' => 'sales', 'items' => [
                ['label' => 'nav.pos', 'route' => 'pos.sale', 'any' => ['shifts.open']],
                ['label' => 'nav.open_shift', 'route' => 'shifts.open', 'any' => ['shifts.open']],
                ['label' => 'nav.tills', 'route' => 'tills.index', 'any' => ['tills.manage']],
            ]],
            ['key' => 'prescriptions', 'items' => [
                ['label' => 'nav.prescriptions', 'route' => 'prescriptions.index', 'any' => ['prescriptions.view']],
                ['label' => 'nav.prescriptions_search', 'route' => 'prescriptions.search', 'any' => ['prescriptions.view']],
                ['label' => 'nav.controlled_drug_register', 'route' => 'controlled-drugs.register', 'any' => ['controlled_drugs.view']],
            ]],
            ['key' => 'customers', 'items' => []],
            ['key' => 'finance', 'items' => [
                ['label' => 'nav.expenses', 'route' => 'finance.expenses.index', 'any' => ['finance.expense.view']],
            ]],
            ['key' => 'reports', 'items' => []],
            ['key' => 'staff', 'items' => [
                ['label' => 'nav.staff', 'route' => 'staff.index', 'any' => ['staff.manage', 'staff.view.own']],
            ]],
            ['key' => 'administration', 'items' => [
                ['label' => 'nav.approvals', 'route' => 'approvals.index', 'any' => ['approvals.request']],
                ['label' => 'nav.users', 'route' => 'users.index', 'any' => ['users.view']],
                ['label' => 'nav.audit', 'route' => 'audit.index', 'any' => ['audit.view', 'audit.view.technical']],
                ['label' => 'nav.settings', 'route' => 'settings.index', 'any' => ['settings.view']],
                ['label' => 'nav.backups', 'route' => 'admin.backups', 'any' => ['backup.run']],
                ['label' => 'nav.restore_checklist', 'route' => 'backup.checklist', 'any' => ['audit.view']],
            ]],
        ];
    }
}
