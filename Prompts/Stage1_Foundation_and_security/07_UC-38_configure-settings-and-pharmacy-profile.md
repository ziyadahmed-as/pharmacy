# UC-38  Configure settings and pharmacy profile

| Field | Value |
|---|---|
| Stage | 1. Foundation and security |
| Build order | 7 |
| Priority | Must (Phase 1) |
| Depends on | UC-33, UC-34 |
| Suggested branch | `uc-38-configure-settings-and-pharmacy-profile` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-38 Configure settings and pharmacy profile. Depends on: UC-33, UC-34. BRD: FR-9.4, 9.5, 9.7; NFR-12. AC-31.

GOAL: no policy value is hard-coded; the SA enters values and the PM or Owner approves them.

STEP 1 Data: `settings` (group, key unique, value json, type, description, is_sensitive, updated_by). `SettingsRepository` with cache and helper `setting('group.key', default)`. Replace the `config/pms.php` reads from earlier use cases (login lockout, idle timeout, approval expiry, licence alert days) and keep config as seed defaults.
STEP 2 Seed keys with BRD defaults: profile (name, address, phone, licence number, logo, currency, receipt footer); security (max_failed_logins 5, idle_timeout_minutes 15, password_history 5); approvals.expiry_hours 72; staff.licence_alert_days 60; inventory (near_expiry_alert_days [90,60,30], minimum_shelf_life_days, adjustment_value_threshold, recount_threshold, receipt_requires_confirmation true); sales (refund_limit, return_window_days, active_payment_methods [cash, mobile_money, card, bank_transfer]); prescriptions (validity_days, repeat_purchase_warning_days, licence_categories_allowed, empty means any valid licence, for OQ-09); finance.expense_approval_limit; receipt format and default language; backup (opening hours, retention).
Keys without a BRD default show "Not set"; later use cases treat "Not set" as approval required.
STEP 3 Saving creates an approval request `settings.update` with old and new values (A: SA enters, PM or Owner approves). On approval write the value, clear the cache, audit old and new.
STEP 4 Livewire: settings tabs by group, "Request change" button, pending indicator, read-only view for PM and Owner, change history per key, logo upload (image only, size limit).
STEP 5 Validation per type (positive integers, descending alert-day lists, valid currency code, non-empty name). Dashboard banner for Owner and PM listing required settings still "Not set".
STEP 6 Language: default language setting and Amharic/English receipt option.

TESTS: change not live until approved; initiator cannot approve; after approval `setting()` returns the new value and audit holds old and new; lockout now uses the database value; validation rejects bad values (E1); staff denied; banner appears for missing required keys; language setting works (AC-31).

DONE WHEN: all green; every key documented in README.
````
