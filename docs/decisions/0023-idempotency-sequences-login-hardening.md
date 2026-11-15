# 0023. Idempotency, Sequences, and Login Hardening

## Context
1. Duplicate requests (network retries, double clicks) on critical operations such as `CompleteSale` and `SubmitStockReceipt` must be idempotent. When an idempotency key collides, rather than failing with a 500 `UniqueConstraintViolationException`, the existing record must be returned gracefully.
2. `DocumentSequence` previously generated sequence rows on demand (`firstOrCreate`), risking race conditions and sequence holes during concurrent execution.
3. Dispensing prescriptions via `CompleteSale` or dispensing actions must perform in-action verification of prescription status (verified, not expired, product present on prescription, quantity not exceeding remaining allowance, and dispensing user passing `LicenceGate`).
4. Login security must prevent account enumeration and timing attacks: password correctness is checked before disclosing account lock/deactivation, returning generic messages to the end user while capturing exact reasons in the audit log. A timed unlock (`security.lockout_minutes`) supplements manual SA unlock.

## Decision
- **Idempotency**: Wrapped transaction executions in `CompleteSale` and `SubmitStockReceipt` with a `catch (UniqueConstraintViolationException)` handler that re-fetches the record by `idempotency_key` and returns the existing entity.
- **DocumentSequence**: Pre-seeded sequence rows (`sale`, `receipt`, `stock_receipt`, `stocktake`, `disposal`, `adjustment`, `return`, `refund`, `prescription`, `credit_payment`) through migration `2026_11_15_000002_create_document_sequence_records.php` and `DocumentSequenceSeeder`. Removed `firstOrCreate` on demand; calling `DocumentSequence::next()` on an unconfigured type throws `\RuntimeException`.
- **Prescription Validation in Dispensing & Sales**: In `CompleteSale`, when `prescriptionId` is supplied, enforce that the prescription exists, is verified, not expired, contains all items, doesn't exceed remaining prescribed quantity (unless overridden), and the dispensing user satisfies `LicenceGate::allows($user, 'prescription.dispense')`. Throws `PrescriptionNotDispensableException`.
- **Login Hardening**: In `LogIn`, verify password with `Hash::check()` first. If the account was locked and `security.lockout_minutes` has elapsed, automatically reset `locked_at` and `failed_login_attempts` with an audit record (`auth.user.unlocked`). Always return generic validation error `__('auth.failed')` to the user and audit the actual reason (`bad_password`, `locked`, `deactivated`, `pending_approval`) in `audit_logs`.

## Status
Accepted and verified through automated tests (`tests/Feature/Auth/IdempotencySequencesLoginHardeningTest.php`).
