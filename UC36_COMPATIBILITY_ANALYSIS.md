# COMPATIBILITY & ARCHITECTURAL ANALYSIS: UC-36 INTEGRATION

## 1. Executive Summary
This document provides the mandatory pre-implementation compatibility analysis for **UC-36: Reset Password and Unlock Account** in the Pharmacy Management System (PMS). 

UC-36 is an administrative operational capability assigned strictly to the **System Administrator (SA)**. It allows an SA to assist users who are either locked out due to security threshold breaches (5 failed attempts from UC-33) or have forgotten/compromised passwords, following a mandatory identity verification step.

---

## 2. Existing Infrastructure Inspection & Reusability Matrix

| Component | Existing Implementation | Reusability / Extension Strategy for UC-36 |
|---|---|---|
| **User Model (`App\Models\User`)** | Fields: `username`, `password`, `is_active`, `failed_login_attempts`, `locked_at`, `must_change_password`, `two_factor_enabled`, `last_login_at` | Fully reusable. Will add `status` accessor (`active`, `locked`, `pending_approval`, `deactivated`), `password_reset_at`, and staff relationship. |
| **Authentication Service (`App\Services\AuthenticationService`)** | Handles login, lockout threshold (5), session fixation prevention, MFA challenge, and session invalidation on logout. | **Do not duplicate.** UC-36 will leverage `AuthenticationService` and `UserManagementService` for lockout clearance, password hashing, and session invalidation. |
| **Account Deactivation (`App\Services\UserManagementService`)** | `deactivateUser(User, reason)` sets `is_active = false` and terminates sessions. | **Preserved intact.** UC-36 respects that deactivated or pending accounts CANNOT be unlocked or reactivated through UC-36. |
| **Forced Password Change (`EnsurePasswordIsChanged` & `ForcePasswordChange`)** | Middleware traps `must_change_password = true`, redirects to `/force-password-change`, validates new password, and resets flag. | **100% Reusable.** When UC-36 resets a password, it sets `must_change_password = true`. The user is seamlessly caught by UC-33's existing flow upon login. |
| **Audit Service (`App\Services\AuditService`)** | Reusable `AuditService::record()` with metadata sanitization (scrubbing `password`, `token`, `secret`) and immutable `AuditLog` model. | **100% Reusable.** All identity verification attempts (success & failure), unlocks, password resets, and combined actions will be logged via `AuditService::record()`. |
| **Role & Authorization (`EnsureRoleAccess` / `AppServiceProvider`)** | Role checking for `system_administrator`. SA is blocked from business/clinical data (`canAccessBusinessData()`). | UC-36 routes (`/admin/account-recovery/*`) are protected by `role:system_administrator`. |

---

## 3. Account Status State Machine in UC-36

The system evaluates account statuses as follows:
1. **`deactivated`**: `is_active === false`. Cannot be unlocked, cannot be reactivated via UC-36. Refuse operation.
2. **`pending_approval`**: (Future UC-34/UC-35 state flag). Cannot be unlocked. Refuse operation.
3. **`locked`**: `is_active === true && locked_at !== null`. Eligible for **Unlock Account** and **Reset Password and Unlock**.
4. **`active`**: `is_active === true && locked_at === null`. Eligible for **Reset Password**.

---

## 4. Workflows & Architecture Design

```
System Administrator (SA)
           |
           v
[/admin/account-recovery] (Livewire Recovery Dashboard & User Selector)
           |
           +---> 1. Mandatory Identity Verification Form
           |         - Target user, verified contact method, outcome (verified / failed), notes
           |         - If failed: AuditLog::record('identity_verification_failed'), HALT with refusal message.
           |
           +---> 2. Select Authorized Operation:
                     |
                     +---> [A] Unlock Account Only
                     |         - Target must be status == 'locked'
                     |         - Clear `locked_at = null`, `failed_login_attempts = 0`
                     |         - Keep existing password
                     |         - AuditLog::record('account_unlocked')
                     |
                     +---> [B] Reset Password Only
                     |         - Target must be eligible ('active' or 'locked')
                     |         - Generate cryptographically secure temporary password (e.g., 14 chars alphanumeric + symbols)
                     |         - Hash with Hash::make()
                     |         - Set `must_change_password = true`, `password_reset_at = now()`
                     |         - Invalidate active database sessions
                     |         - Store temporary password in session flash for ONE-TIME visual reveal to SA
                     |         - AuditLog::record('password_reset_by_admin') [NEVER logging the password]
                     |
                     +---> [C] Combined Reset Password & Unlock
                               - Atomic DB transaction performing [A] + [B]
                               - Clears lock + sets new temp password + forces change at login + terminates sessions
```

---

## 5. Security Invariants
- Plaintext temporary passwords are NEVER persisted in the database, audit logs, application logs, URLs, or Livewire public properties.
- A secure random generator (`Str::password()`) produces temporary passwords exceeding complexity standards.
- One-time reveal mechanism displays the generated credential to the SA once on the screen; refreshing the page or leaving it permanently clears it from memory.
- Sessions are invalidated across devices for the affected user upon password reset.
- Deactivated accounts or accounts linked to ended employment cannot be reactivated or unlocked via UC-36.

