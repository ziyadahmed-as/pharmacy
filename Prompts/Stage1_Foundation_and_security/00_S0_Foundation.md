# S0  Foundation

| Field | Value |
|---|---|
| Stage | 1. Foundation and security |
| Build order | Before everything |
| Priority | Run once before UC-33 |
| Depends on | None |
| Suggested branch | `s0-foundation` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Read AGENTS.md completely. Create the project foundation. Do not build any use case yet.

STEP 1 Project: Laravel 12 with the Livewire starter kit. Add Pest, Pint, Larastan (level 6+), composer scripts `test`, `lint`, `analyse`. Timezone Africa/Addis_Ababa. Locales `en` and `am`. Add spatie/laravel-permission. Use database sessions and database queue.
STEP 2 Roles: enum `Role` with system_admin, owner, pharmacy_manager, pharmacy_staff. `RolePermissionSeeder` seeds permission names from the BRD permission matrix (section 4.3), grouped by module, with only Stage 1 permissions active for now. Create `RoleCombinationRule` (SA cannot combine with any role).
STEP 3 Audit: table `audit_logs` (user_id, role_used, terminal, action, auditable_type, auditable_id, old_values json, new_values json, created_at; no updated_at). Model `AuditLog` throws on update/delete; add MySQL triggers blocking UPDATE and DELETE. `AuditLogger::record()` fills user, acting role and terminal (IP + short user agent) and masks password, token, secret, id_number and health fields. Trait `Auditable` logging created/updated/deactivated with old and new values.
STEP 4 Localisation: `lang/en`, `lang/am`, language switcher component, `users.locale` column, Noto Sans Ethiopic font in the layout.
STEP 5 Layout: authenticated layout with sidebar; items shown only if the user has the permission; empty groups for Catalogue, Inventory, Sales, Prescriptions, Customers, Finance, Reports, Staff, Administration.
STEP 6 Livewire `ConfirmWithPassword` modal (password re-entry, `Hash::check`, throttled), reusable by any component through an event.
STEP 7 `config/pms.php` with temporary defaults (max failed logins 5, idle timeout 15, approval expiry 72h, licence alert days 60). These move to the settings table in UC-38.
STEP 8 `BootstrapSeeder`: one SA, one Owner, one Pharmacy Manager from `.env` values (never hard-code passwords), `must_change_password` true.
STEP 9 Pest arch tests: AuditLog cannot be updated/deleted; no model named PurchaseOrder, Supplier, Discount or Tax exists.
STEP 10 README (run, seed, test) and `docs/decisions/0000-conflict-defaults.md` copying AGENTS.md section 12.

DONE WHEN: app boots, seeders run, tests green, audit immutability proven, language switch works, final message in the AGENTS.md format.
````
