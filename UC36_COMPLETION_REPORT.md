# Pharmacy Management System (PMS) — UC-36 Completion Report
## Use Case 36: Reset Password and Unlock Account

---

### Executive Summary
**UC-36: Reset Password and Unlock Account** has been designed, implemented, and verified in the Pharmacy Management System using **Laravel 12**, **Livewire 3**, **Tailwind CSS**, and **Pest**. 

This IAM capability enables the **System Administrator (SA)** to verify identities and execute controlled account recovery operations (Unlock Account, Password Reset with Forced Change, and Atomic Combined Reset & Unlock) while strictly integrating with **UC-33 (Authentication & Session Invalidation)** and maintaining boundaries with **UC-34/UC-35 (User Lifecycle & Approvals)** and **UC-39 (Staff)**.

---

### 1. Technology & Package Stack
- **Framework:** Laravel 12.71.0 (PHP 8.2.12)
- **Frontend / Components:** Livewire v3.8.11 with Tailwind CSS UI
- **Test Framework:** Pest 3.8.7 & pest-plugin-laravel 3.2.0 (33 passed tests, 125 assertions)
- **Database Engine:** MySQL 8+ compatibility with transactions and row locking
- **Security / IAM:** Cryptographically secure temporary password generation (`Str::password`), Laravel Hash (`bcrypt`), atomic DB transactions, session invalidation across devices, and single-reveal visual delivery.

---

### 2. Architecture & Compatibility Analysis

See [`UC36_COMPATIBILITY_ANALYSIS.md`](file:///c:/Users/hp/Pictures/project/pharmacy/PMS12/UC36_COMPATIBILITY_ANALYSIS.md) for full pre-implementation analysis.

#### System Boundaries & State Machine
The implementation respects canonical account states without bypassing UC-34/UC-35:
- **`active`**: Eligible for Password Reset.
- **`locked`**: Eligible for Unlock, Reset, and Combined Reset & Unlock.
- **`pending_approval`**: Cannot be unlocked or reset via UC-36.
- **`deactivated`**: Cannot be unlocked or reactivated via UC-36. (Reactivation belongs to UC-34/UC-35).

---

### 3. Implementation Deliverables

1. **Database Migration:**
   - [`2026_11_06_000004_add_uc36_fields_to_users_and_verifications.php`](file:///c:/Users/hp/Pictures/project/pharmacy/PMS12/database/migrations/2026_11_06_000004_add_uc36_fields_to_users_and_verifications.php):
     - Added `password_reset_at` and `account_status` index to `users`.
     - Created `identity_verifications` table tracking `user_id`, `admin_id`, `verification_method`, `outcome`, `notes`, and `verified_at`.

2. **Domain Models:**
   - [`User.php`](file:///c:/Users/hp/Pictures/project/pharmacy/PMS12/app/Models/User.php): Added `status` attribute accessor, `isEligibleForPasswordReset()`, `isEligibleForUnlock()`, and `identityVerifications()` relationship.
   - [`IdentityVerification.php`](file:///c:/Users/hp/Pictures/project/pharmacy/PMS12/app/Models/IdentityVerification.php): Relates target user, admin, method, outcome, and verification status.

3. **Application Service:**
   - [`AccountRecoveryService.php`](file:///c:/Users/hp/Pictures/project/pharmacy/PMS12/app/Services/AccountRecoveryService.php):
     - `recordIdentityVerification()`: Mandates verification method and outcome logging. Refusal halts all operations.
     - `unlockAccount()`: Clears `locked_at` and resets `failed_login_attempts = 0` while preserving password.
     - `resetPassword()`: Generates a 14-character cryptographically secure temporary password, sets `must_change_password = true`, invalidates database sessions, and records audit log.
     - `resetPasswordAndUnlock()`: Atomic transaction executing combined reset and unlock.

4. **Livewire 3 Interfaces:**
   - [`AccountRecoveryDashboard.php`](file:///c:/Users/hp/Pictures/project/pharmacy/PMS12/app/Livewire/Admin/AccountRecoveryDashboard.php) & [`account-recovery-dashboard.blade.php`](file:///c:/Users/hp/Pictures/project/pharmacy/PMS12/resources/views/livewire/admin/account-recovery-dashboard.blade.php):
     - Displays locked accounts, recent identity verifications, and recovery audit logs.
   - [`PerformAccountRecovery.php`](file:///c:/Users/hp/Pictures/project/pharmacy/PMS12/app/Livewire/Admin/PerformAccountRecovery.php) & [`perform-account-recovery.blade.php`](file:///c:/Users/hp/Pictures/project/pharmacy/PMS12/resources/views/livewire/admin/perform-account-recovery.blade.php):
     - 4-step wizard: Account search & selection -> Mandatory identity verification -> Authorized operation selection -> Single-reveal temporary credential delivery.

5. **Routes & Navigation:**
   - Added `/admin/account-recovery` and `/admin/account-recovery/create/{userId?}` guarded by `role:system_administrator`.
   - Updated [`app.blade.php`](file:///c:/Users/hp/Pictures/project/pharmacy/PMS12/resources/views/layouts/app.blade.php) with administrator link to Account Recovery.

---

### 4. Verification & Automated Test Results

Automated test suite executed using **Pest 3.8.7**:

```text
PASS  Tests\Feature\AccountRecoveryTest
✓ SA can view account recovery dashboard
✓ Non-admin users cannot access account recovery dashboard
✓ Identity verification can be recorded successfully
✓ Failed identity verification refuses operation and logs security audit event
✓ Admin can unlock a locked account after identity verification
✓ Admin cannot unlock an active or deactivated account
✓ Admin can reset password, which forces password change on next login
✓ Admin can perform atomic combined reset and unlock
✓ Livewire recovery wizard handles verification failure and stops operation

PASS  Tests\Feature\Authentication\AuthenticationFeatureTest
✓ auth 001 valid active user login
✓ auth 002 invalid password rejected
✓ auth 003 invalid username rejected
✓ auth 004 deactivated account rejected
✓ auth 005 five consecutive failed attempts locks account
✓ auth 006 locked account rejected
✓ auth 007 successful login regenerates session
✓ auth 008 user manually logs out
✓ auth 009 inactivity timeout expires session
✓ auth 010 manager mfa enabled requires challenge
✓ auth 011 admin mfa enabled requires challenge
✓ auth 012 mfa disabled allows normal authentication
✓ auth 013 password change enforced
✓ auth 014 individual account authentication enforced
✓ auth 015 successful authentication audit event recorded
✓ auth 016 failed authentication audit event recorded
✓ auth 017 unauthorized route access denied
✓ auth 018 successful logout audit event recorded

PASS  Tests\Feature\Authentication\SecurityControlsTest
✓ audit logs cannot be updated
✓ audit logs cannot be deleted
✓ audit sanitizes passwords and secrets
✓ system administrator cannot access business data

PASS  Tests\Feature\ExampleTest
✓ the application returns a successful response

PASS  Tests\Unit\ExampleTest
✓ that true is true

Tests:    33 passed (125 assertions)
Duration: 2.53s
```
