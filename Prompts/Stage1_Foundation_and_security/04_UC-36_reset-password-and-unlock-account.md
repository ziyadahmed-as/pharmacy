# UC-36  Reset password and unlock account

| Field | Value |
|---|---|
| Stage | 1. Foundation and security |
| Build order | 4 |
| Priority | Must (Phase 1) |
| Depends on | UC-35 |
| Suggested branch | `uc-36-reset-password-and-unlock-account` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-36 Reset password and unlock account. Depends on: UC-35. BRD: FR-8.6, 8.10. AC-18.

GOAL: the Administrator helps locked-out users safely, without seeing any business data.

STEP 1 Action `UnlockAccount` and `ResetPassword` (SA only). Both require a ticked "identity verified" box and a note (how it was verified); store both in the audit entry.
STEP 2 Reset: random temporary password shown once, never stored in plain text or logged; set must_change_password; push the old hash into password history; call `EndUserSessions`.
STEP 3 Unlock: clear locked_at and failed_attempts.
STEP 4 Notify owner and pharmacy_manager "password reset for <user> by <SA>" (in-app). Throttle: at most 10 resets per hour per SA.
STEP 5 Livewire: row actions Unlock and Reset on the users list (filter "Locked"), modal with identity checkbox, note and one-time result.
STEP 6 SA cannot reset or unlock their own account in the UI. Add artisan command `pms:reset-sa` (server-side, audited) for the break-glass case and document it.

TESTS: locked user unlocked and can log in with the same password (AC-18); reset invalidates the old password, forces change, kills old sessions; missing confirmation or note rejected (E1); non-SA denied incl. direct URL; audit has actor, target, note and no password; self-reset blocked; artisan command works and is audited.

DONE WHEN: all green.
````
