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
                    $items[] = [
                        'label' => $item['label'],
                        'route' => $item['route'],
                        'badge' => $item['badge'] ?? null,
                    ];
                }
            }
            if ($items !== []) {
                $out[] = [
                    'key' => $group['key'],
                    'label' => $group['label'] ?? 'nav.group.'.$group['key'],
                    'items' => $items,
                ];
            }
        }

        return $out;
    }

    /** @return list<array{key: string, label?: string, items: list<array{label: string, route: string, any: list<string>, badge?: string}>}> */
    public static function definition(): array
    {
        return [
            // Job Group 1: Sell
            ['key' => 'sell', 'label' => 'nav.job.sell', 'items' => [
                ['label' => 'nav.pos', 'route' => 'pos.sale', 'any' => ['shifts.open']],
                ['label' => 'nav.open_shift', 'route' => 'shifts.open', 'any' => ['shifts.open']],
                ['label' => 'nav.tills', 'route' => 'tills.index', 'any' => ['tills.manage']],
            ]],
            // Job Group 2a: Product Management
            ['key' => 'products', 'label' => 'nav.job.products', 'items' => [
                ['label' => 'nav.catalogue', 'route' => 'catalogue.index', 'any' => ['catalogue.view']],
                ['label' => 'nav.categories', 'route' => 'catalogue.categories', 'any' => ['catalogue.manage']],
            ]],
            // Job Group 2b: Stock Management
            ['key' => 'stock', 'label' => 'nav.job.stock', 'items' => [
                ['label' => 'nav.receive_stock', 'route' => 'inventory.receive', 'any' => ['stock.receive']],
                ['label' => 'nav.stock_on_hand', 'route' => 'inventory.stock', 'any' => ['stock.view']],
                ['label' => 'nav.expiry_stock', 'route' => 'inventory.expiry-stock', 'any' => ['stock.view']],
                ['label' => 'nav.stock_alerts', 'route' => 'inventory.alerts', 'any' => ['stock.view']],
                ['label' => 'nav.stock_ledger', 'route' => 'inventory.ledger', 'any' => ['stock.view']],
                ['label' => 'nav.quarantine', 'route' => 'inventory.quarantine', 'any' => ['stock.view']],
                ['label' => 'nav.stock_adjustments', 'route' => 'inventory.adjustments.index', 'any' => ['stock.adjust.request']],
                ['label' => 'nav.stocktakes', 'route' => 'inventory.stocktakes.index', 'any' => ['stock.stocktake']],
                ['label' => 'nav.disposals', 'route' => 'inventory.disposals.index', 'any' => ['stock.dispose']],
            ]],
            // Job Group 3: Customers and Prescriptions
            ['key' => 'customers_prescriptions', 'label' => 'nav.job.customers_prescriptions', 'items' => [
                ['label' => 'nav.customers', 'route' => 'customers.index', 'any' => ['customers.view']],
                ['label' => 'nav.prescriptions', 'route' => 'prescriptions.index', 'any' => ['prescriptions.view']],
                ['label' => 'nav.prescriptions_search', 'route' => 'prescriptions.search', 'any' => ['prescriptions.view']],
                ['label' => 'nav.prescribers', 'route' => 'prescribers.index', 'any' => ['prescriptions.view']],
                ['label' => 'nav.controlled_drug_register', 'route' => 'controlled-drugs.register', 'any' => ['controlled_drugs.view']],
            ]],
            // Job Group 4: Money
            ['key' => 'money', 'label' => 'nav.job.money', 'items' => [
                ['label' => 'nav.cash_revenue', 'route' => 'finance.cash-revenue', 'any' => ['finance.expense.view']],
                ['label' => 'nav.expenses', 'route' => 'finance.expenses.index', 'any' => ['finance.expense.view']],
                ['label' => 'nav.credits', 'route' => 'credits.index', 'any' => ['customers.manage']],
                ['label' => 'nav.payroll', 'route' => 'payroll.index', 'any' => ['payroll.manage', 'payroll.view', 'payroll.view.own']],
            ]],
            // Job Group 5: Staff
            ['key' => 'staff', 'label' => 'nav.job.staff', 'items' => [
                ['label' => 'nav.staff', 'route' => 'staff.index', 'any' => ['staff.manage', 'staff.view.own']],
                ['label' => 'nav.roster', 'route' => 'roster.index', 'any' => ['staff.manage', 'staff.view.own']],
                ['label' => 'nav.attendance', 'route' => 'attendance.index', 'any' => ['attendance.manage', 'attendance.view', 'attendance.view.own']],
                ['label' => 'nav.leave', 'route' => 'attendance.leave', 'any' => ['leave.request', 'leave.manage']],
            ]],
            // Job Group 6: Reports
            ['key' => 'reports', 'label' => 'nav.job.reports', 'items' => [
                ['label' => 'nav.reports', 'route' => 'reports.index', 'any' => ['catalogue.view', 'stock.view', 'shifts.view', 'prescriptions.view', 'audit.view', 'finance.expense.view']],
            ]],
            // Job Group 7: Settings
            ['key' => 'settings', 'label' => 'nav.job.settings', 'items' => [
                ['label' => 'nav.approvals', 'route' => 'approvals.index', 'any' => ['approvals.request'], 'badge' => 'approvals'],
                ['label' => 'nav.users', 'route' => 'users.index', 'any' => ['users.view']],
                ['label' => 'nav.audit', 'route' => 'audit.index', 'any' => ['audit.view', 'audit.view.technical']],
                ['label' => 'nav.settings', 'route' => 'settings.index', 'any' => ['settings.view']],
                ['label' => 'nav.backups', 'route' => 'admin.backups', 'any' => ['backup.run']],
                ['label' => 'nav.restore_checklist', 'route' => 'backup.checklist', 'any' => ['audit.view']],
            ]],
        ];
    }
}
