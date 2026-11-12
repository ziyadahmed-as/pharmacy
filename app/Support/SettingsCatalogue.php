<?php

namespace App\Support;

/**
 * Every setting the system knows (UC-38 step 2, README "Settings reference").
 *
 * `default` is the BRD default; null means "Not set": later use cases must treat
 * a missing required setting as "approval required" or "feature blocked",
 * never guess (AGENTS.md rule 8).
 *
 * `required` keys appear in the Owner/PM dashboard banner while "Not set".
 */
final class SettingsCatalogue
{
    /**
     * @return array<string, array{group: string, type: string, default: mixed, required: bool, sensitive?: bool, description: string}>
     */
    public static function all(): array
    {
        return [
            // profile
            'profile.name' => self::def('profile', 'string', null, true, 'Pharmacy name'),
            'profile.address' => self::def('profile', 'string', null, false, 'Pharmacy address'),
            'profile.phone' => self::def('profile', 'string', null, false, 'Pharmacy phone'),
            'profile.licence_number' => self::def('profile', 'string', null, true, 'Pharmacy licence number'),
            'profile.logo' => self::def('profile', 'image', null, false, 'Logo (image, private storage)'),
            'profile.currency' => self::def('profile', 'currency', 'ETB', true, 'ISO 4217 currency code'),
            'profile.receipt_footer' => self::def('profile', 'string', null, false, 'Receipt footer text'),
            // security
            'security.max_failed_logins' => self::def('security', 'pos_int', 5, true, 'Failed logins before an account locks'),
            'security.idle_timeout_minutes' => self::def('security', 'pos_int', 15, true, 'Idle minutes before automatic logout'),
            'security.password_history' => self::def('security', 'pos_int', 5, true, 'Previous passwords that cannot be reused'),
            // approvals
            'approvals.expiry_hours' => self::def('approvals', 'pos_int', 72, true, 'Hours before a pending approval expires'),
            // staff
            'staff.licence_alert_days' => self::def('staff', 'pos_int', 60, true, 'Days before expiry to warn about a licence'),
            // inventory
            'inventory.near_expiry_alert_days' => self::def('inventory', 'desc_int_list', [90, 60, 30], true, 'Near-expiry alert thresholds in days (descending)'),
            'inventory.minimum_shelf_life_days' => self::def('inventory', 'pos_int', null, false, 'Minimum shelf life accepted at receipt'),
            'inventory.adjustment_value_threshold' => self::def('inventory', 'money', null, false, 'Adjustment value that needs extra approval'),
            'inventory.recount_threshold' => self::def('inventory', 'pos_int', null, false, 'Stocktake variance that triggers a recount'),
            'inventory.receipt_requires_confirmation' => self::def('inventory', 'bool', true, true, 'PM must confirm a receipt before stock is sellable (BR-19)'),
            // sales
            'sales.refund_limit' => self::def('sales', 'money', null, false, 'Refund value above which approval is needed (OQ-06)'),
            'sales.return_window_days' => self::def('sales', 'pos_int', null, false, 'Days within which a return is accepted (OQ-06)'),
            'sales.active_payment_methods' => self::def('sales', 'payment_methods', ['cash', 'mobile_money', 'card', 'bank_transfer'], true, 'Payment methods offered at the till (OQ-05)'),
            // prescriptions
            'prescriptions.validity_days' => self::def('prescriptions', 'pos_int', null, false, 'Days a prescription stays valid (OQ-02)'),
            'prescriptions.repeat_purchase_warning_days' => self::def('prescriptions', 'pos_int', null, false, 'Repeat purchase warning window (OQ-02)'),
            'prescriptions.licence_categories_allowed' => self::def('prescriptions', 'string_list', [], false, 'Licence categories allowed to dispense; empty means any valid licence (OQ-09)'),
            'pharmacy.handles_controlled_drugs' => self::def('pharmacy', 'bool', true, true, 'Whether the pharmacy handles and records controlled drugs in a register (OQ-01)'),
            // finance
            'finance.expense_approval_limit' => self::def('finance', 'money', null, false, 'Expense amount above which approval is needed (OQ-06)'),
            // receipt and language
            'general.default_language' => self::def('general', 'locale', 'en', true, 'Default language (en or am)'),
            'general.receipt_language' => self::def('general', 'receipt_language', 'default', false, 'Receipt language: default, en or am'),
            'general.receipt_format' => self::def('general', 'string', null, false, 'Receipt format notes (OQ-10)'),
            // backup
            'backup.opening_hours' => self::def('backup', 'string', null, false, 'Pharmacy opening hours (backup window)'),
            'backup.retention_days' => self::def('backup', 'pos_int', null, false, 'Backup retention period (OQ-03)'),
        ];
    }

    /** @return list<string> */
    public static function groups(): array
    {
        return array_values(array_unique(array_map(fn ($d) => $d['group'], self::all())));
    }

    /** @return array{group: string, type: string, default: mixed, required: bool, description: string}|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    private static function def(string $group, string $type, mixed $default, bool $required, string $description, bool $sensitive = false): array
    {
        return compact('group', 'type', 'default', 'required', 'description') + ['sensitive' => $sensitive];
    }
}
