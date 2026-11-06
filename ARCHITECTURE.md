# ARCHITECTURAL DESIGN DOCUMENT & IMPLEMENTATION PLAN
## Pharmacy Management System (PMS) – UC-33: Log in and Log out

### 1. Architectural Overview
The Pharmacy Management System adopts a **Modular Monolith** architecture with strict separation of concerns, built upon Laravel 12 and Livewire 3:
- **Presentation Layer**: Livewire 3 Components (`Login`, `TwoFactorChallenge`, `ForcePasswordChange`) using Tailwind CSS.
- **Application & Service Layer**: `AuthenticationService`, `AuthorizationService`, `AuditService` orchestrating business workflows.
- **Domain & Data Layer**: Eloquent Models (`User`, `Role`, `AuditLog`) with database transactions, atomic operations, and strict constraints.
- **Middleware & Security Layer**: Dedicated HTTP Middleware (`EnsureAccountIsActive`, `EnsurePasswordIsChanged`, `EnsureRoleAccess`) and session lifecycle guards.

### 2. Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    USERS ||--o{ USER_ROLES : has
    ROLES ||--o{ USER_ROLES : assigned_to
    USERS ||--o{ AUDIT_LOGS : triggers

    USERS {
        bigint id PK
        string name
        string username UK
        string email UK "nullable"
        string password
        boolean is_active "default true"
        integer failed_login_attempts "default 0"
        timestamp locked_at "nullable"
        boolean must_change_password "default false"
        boolean two_factor_enabled "default false"
        text two_factor_secret "nullable, encrypted"
        timestamp last_login_at "nullable"
        string last_login_ip "nullable"
        timestamp email_verified_at "nullable"
        string remember_token "nullable"
        timestamp created_at
        timestamp updated_at
    }

    ROLES {
        bigint id PK
        string name
        string slug UK
        string description "nullable"
        timestamp created_at
        timestamp updated_at
    }

    USER_ROLES {
        bigint id PK
        bigint user_id FK
        bigint role_id FK
        timestamp assigned_at
        timestamp created_at
        timestamp updated_at
    }

    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK "nullable"
        string action
        string event_category
        text description
        string ip_address "nullable"
        text user_agent "nullable"
        string session_identifier "nullable"
        json metadata "nullable"
        timestamp created_at
    }
```

### 3. System Roles & Access Matrix
The system enforces 4 principal roles (slugs):
- `pharmacy_staff` (PS): Frontline operations (dispensing, sales).
- `pharmacy_manager` (PM): Pharmacy operations supervision, staff monitoring. MFA configurable.
- `owner` (OW): Business owner, executive authority.
- `system_administrator` (SA): Technical administration, security management. MFA configurable. Strictly denied access to patient, prescription, sales, or financial data.

### 4. Authentication Workflow
```mermaid
sequenceDiagram
    actor User as User / Actor
    participant View as Livewire Login Component
    participant AuthSvc as AuthenticationService
    participant Audit as AuditService
    participant DB as MySQL Database / Users Table
    participant Session as Laravel Session

    User->>View: Enters username & password
    View->>AuthSvc: attempt(username, password, remember)
    AuthSvc->>DB: Query user by username
    alt User not found
        AuthSvc->>Audit: record(login_failed, invalid_credentials)
        AuthSvc-->>View: Error: Invalid credentials
    else User is Deactivated (!is_active)
        AuthSvc->>Audit: record(login_rejected_deactivated)
        AuthSvc-->>View: Error: Account is deactivated
    else User is Locked (locked_at is not null)
        AuthSvc->>Audit: record(login_rejected_locked)
        AuthSvc-->>View: Error: Account is locked
    else Password invalid
        AuthSvc->>DB: Atomic increment failed_login_attempts
        alt failed_login_attempts >= 5
            AuthSvc->>DB: Set locked_at = now()
            AuthSvc->>Audit: record(account_locked)
        else
            AuthSvc->>Audit: record(login_failed)
        end
        AuthSvc-->>View: Error: Invalid credentials
    else Password valid
        AuthSvc->>DB: Reset failed_login_attempts = 0
        alt MFA enabled & (Role PM or SA)
            AuthSvc->>Session: Store temporary auth context (user_id)
            AuthSvc-->>View: Redirect to /two-factor-challenge
        else MFA not required / disabled
            AuthSvc->>Session: Login user, regenerate session ID
            AuthSvc->>DB: Update last_login_at, last_login_ip
            AuthSvc->>Audit: record(login_success)
            alt must_change_password == true
                AuthSvc-->>View: Redirect to /force-password-change
            else
                AuthSvc-->>View: Redirect to /dashboard
            end
        end
    end
```

### 5. Implementation Phases
1. **Database Schema & Migrations**: Update `users` table, create `roles`, `user_roles`, and `audit_logs` tables.
2. **Domain Models & Enums**: `UserRole` Enum, `User`, `Role`, `AuditLog` models with immutable safeguards.
3. **Application Services**: `AuthenticationService`, `AuthorizationService`, `AuditService`.
4. **Middleware & Gates**: `EnsureAccountIsActive`, `EnsurePasswordIsChanged`, `EnsureRoleAccess`, and Gates.
5. **Livewire 3 UI Components**:
   - `App\Livewire\Auth\Login`
   - `App\Livewire\Auth\TwoFactorChallenge`
   - `App\Livewire\Auth\ForcePasswordChange`
   - Authenticated Layout & Role-aware navigation
   - Access Denied view
6. **Automated Feature & Security Test Suite**: Testing all 18 specified scenarios + security controls.
