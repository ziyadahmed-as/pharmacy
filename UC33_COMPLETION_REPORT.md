# Pharmacy Management System (PMS) – UC-33 Completion Report
## Use Case 33: Log in and Log out

---

### Executive Summary
The authentication and session lifecycle foundation (**UC-33**) for the **Pharmacy Management System (PMS)** has been analyzed, designed, implemented, and verified using **Laravel 12**, **Livewire 3**, and **Tailwind CSS**. The implementation follows a **Modular Monolith** architecture with clean separation of concerns across service, domain, middleware, and presentation layers.

---

### 1. Technology & Package Stack
- **Framework:** Laravel 12.71.0 (PHP 8.2.12)
- **Frontend / Components:** Livewire v3.8.11 with Tailwind CSS UI
- **Database Engine:** MySQL 8+ compatibility (with SQLite test environment support)
- **Security / Hashing:** Laravel Hash (`bcrypt`), atomic row locking (`lockForUpdate`), encrypted secrets
- **Testing Suite:** PHPUnit 11 with 100% test passage (24 tests, 85 assertions)

---

### 2. Database Schema & Migration Foundation

Three new production-grade migrations have been created and executed:

1. **`2026_11_06_000001_update_users_table_for_uc33.php`**:
   - `username`: Unique login identifier.
   - `is_active`: Boolean flag indicating if account is active.
   - `failed_login_attempts`: Integer counter for failed authentication attempts.
   - `locked_at`: Timestamp indicating when an account was locked after 5 consecutive failures.
   - `must_change_password`: Boolean flag enforcing temporary password change before access.
   - `two_factor_enabled` & `two_factor_secret`: Encrypted MFA credentials.
   - `last_login_at` & `last_login_ip`: Auditing metadata.

2. **`2026_11_06_000002_create_roles_and_user_roles_tables.php`**:
   - `roles`: `id`, `name`, `slug` (unique), `description`.
   - `user_roles`: Pivot table with unique composite index `[user_id, role_id]` and `assigned_at` timestamp.
   - Seeded with the 4 principal roles:
     - **Pharmacy Staff (`pharmacy_staff` / PS)**
     - **Pharmacy Manager (`pharmacy_manager` / PM)**
     - **Owner (`owner` / OW)**
     - **System Administrator (`system_administrator` / SA)**

3. **`2026_11_06_000003_create_audit_logs_table.php`**:
   - `id`, `user_id` (nullable), `action`, `event_category`, `description`, `ip_address`, `user_agent`, `session_identifier`, `metadata` (JSON), `created_at`.
   - Built with application-level immutability enforcement preventing any `update` or `delete` operations.

---

### 3. Application Architecture & Services

```
app/
├── Enums/
│   └── UserRole.php              # Enum with role codes, labels, MFA & data access boundaries
├── Models/
│   ├── User.php                  # User entity, relationship methods, role checks
│   ├── Role.php                  # Role model with isRole() check
│   └── AuditLog.php              # Immutable audit entity
├── Services/
│   ├── AuthenticationService.php # Core login, failed attempts, atomic locking, MFA, session management
│   ├── AuthorizationService.php  # Role-checking and business data access resolution
│   └── AuditService.php          # Reusable audit logger with automatic metadata sanitization
├── Http/
│   ├── Controllers/
│   │   └── LogoutController.php  # POST /logout handler with session invalidation
│   └── Middleware/
│       ├── EnsureAccountIsActive.php     # Terminates deactivated or locked accounts
│       ├── EnsurePasswordIsChanged.php   # Enforces password reset redirect
│       ├── EnsureRoleAccess.php          # Role-based route authorization
│       └── EnsureSessionNotExpired.php   # 15-minute server-enforced inactivity timeout
├── Livewire/
│   ├── Auth/
│   │   ├── Login.php                 # Livewire 3 login component
│   │   ├── TwoFactorChallenge.php    # Livewire 3 MFA challenge component
│   │   └── ForcePasswordChange.php   # Livewire 3 temporary password update component
│   └── Dashboard.php                 # Role-aware PMS landing dashboard
```

---

### 4. Key Security Controls Implemented

1. **Five-Failure Account Locking (Atomic Concurrency Safe):**
   `AuthenticationService` uses `DB::transaction()` and `lockForUpdate()` to prevent race conditions during simultaneous brute-force attempts. Accounts lock at 5 consecutive failures and can only be unlocked by a System Administrator (UC-36).
2. **Generic Error Messages:**
   Failed logins return `"These credentials do not match our records."` without disclosing username existence.
3. **Session Fixation Prevention:**
   Calls `session()->regenerate()` immediately upon successful authentication.
4. **Server-Enforced 15-Minute Inactivity Timeout:**
   `EnsureSessionNotExpired` middleware checks `auth.last_activity` timestamp on the server side on every request, invalidates expired sessions, and creates an audit entry.
5. **System Administrator Boundary Enforcement:**
   System Administrator role is strictly restricted from accessing patient, prescription, dispensing, or sales business data via Gate `'access-business-data'`.
6. **Immutable Audit Logging:**
   `AuditLog::save()` and `AuditLog::delete()` throw `RuntimeException` if modified. Metadata is scrubbed of sensitive fields (`password`, `two_factor_secret`, `token`).

---

### 5. Automated Test Suite (All 18 Scenarios + Security Tests)

All automated feature tests in `tests/Feature/Authentication` pass cleanly:

| Test ID | Scenario | Result |
|---|---|---|
| **AUTH-001** | Valid active user login | **PASS** |
| **AUTH-002** | Invalid password rejection & attempt counter | **PASS** |
| **AUTH-003** | Invalid username rejection | **PASS** |
| **AUTH-004** | Deactivated account authentication blocked | **PASS** |
| **AUTH-005** | 5 consecutive failed attempts locks account | **PASS** |
| **AUTH-006** | Locked account login rejected | **PASS** |
| **AUTH-007** | Successful login session ID regenerated | **PASS** |
| **AUTH-008** | Manual logout session invalidation | **PASS** |
| **AUTH-009** | 15-minute inactivity session expiration | **PASS** |
| **AUTH-010** | Pharmacy Manager MFA challenge triggered | **PASS** |
| **AUTH-011** | System Administrator MFA challenge triggered | **PASS** |
| **AUTH-012** | MFA disabled allows normal flow | **PASS** |
| **AUTH-013** | Forced password change enforced on access | **PASS** |
| **AUTH-014** | Shared accounts prohibited (individual identity) | **PASS** |
| **AUTH-015** | Successful authentication audit log recorded | **PASS** |
| **AUTH-016** | Failed authentication audit log recorded | **PASS** |
| **AUTH-017** | Unauthorized route access returned 403 Forbidden | **PASS** |
| **AUTH-018** | Logout audit event recorded | **PASS** |
| **SEC-001** | Audit log immutability (updates prevented) | **PASS** |
| **SEC-002** | Audit log immutability (deletions prevented) | **PASS** |
| **SEC-003** | Audit metadata secret sanitization | **PASS** |
| **SEC-004** | System Administrator excluded from business data | **PASS** |

**Summary: 24 tests passed, 85 assertions, 0 failures.**

