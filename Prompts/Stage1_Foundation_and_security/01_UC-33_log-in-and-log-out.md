# UC-33  Log in and log out

| Field | Value |
|---|---|
| Stage | 1. Foundation and security |
| Build order | 1 |
| Priority | Must (Phase 1) |
| Depends on | S0 |
| Suggested branch | `uc-33-log-in-and-log-out` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-33 Log in and log out. Depends on: S0. BRD: FR-8.1, 8.4, 8.6, 8.9; NFR-2. AC-18, AC-32.

GOAL: every user signs in as one named person and gets only the menu their role allows.

STEP 1 Data: migration adding to users: username (unique), is_active, locked_at, failed_attempts, must_change_password, password_changed_at, last_login_at, locale. Table `password_histories`.
STEP 2 Action `LogIn`: authenticate by username + password; refuse deactivated users; count failures; lock at 5 (read from config now, settings later); log `auth.login.success|failed|locked`. Generic error text that does not reveal whether the username exists.
STEP 3 Livewire `Auth\Login` replacing the starter kit login. Session regeneration, rate limiting, CSRF.
STEP 4 Idle logout: server middleware checking last activity (15 minutes) plus a client-side Alpine warning timer. Log `auth.logout.idle`.
STEP 5 Forced password change page (A1). Policy: minimum 8 characters, last 5 passwords not reusable.
STEP 6 Optional 2FA for system_admin, owner and pharmacy_manager (use the starter kit's two-factor support); not forced; pharmacy_staff has none.
STEP 7 Acting role selector when a user holds several roles; store in session; used by AuditLogger.
STEP 8 Deactivate service `EndUserSessions` (delete rows from `sessions`) used later by UC-35 and UC-39.
STEP 9 Dashboard placeholder with role-based menu.

TESTS: success and logout audited; 5 wrong passwords lock the account and the right password is then refused (AC-18); idle timeout (AC-18); deactivated user refused and open session ended (AC-32); reused password rejected; 7-character password rejected; forced change page; menu per role; shared accounts impossible (unique username).

DONE WHEN: all green; AC-18 and the login part of AC-32 pass.
````
