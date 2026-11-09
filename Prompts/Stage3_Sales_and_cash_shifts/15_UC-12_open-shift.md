# UC-12  Open shift

| Field | Value |
|---|---|
| Stage | 3. Sales and cash shifts |
| Build order | 15 |
| Priority | Must (Phase 1) |
| Depends on | UC-33, UC-38 |
| Suggested branch | `uc-12-open-shift` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-12 Open shift. Depends on: UC-33, UC-38. BRD: FR-3.9, 10.4. AC-24.

GOAL: every sale happens inside an open shift on a named till under a named person.

STEP 1 Data: `tills` (name, is_active) with a seeder and a small admin list for the PM; `shifts` (till_id, user_id, opened_at, opening_float, status open|closed, closed_at, expected and counted cash per payment method json, variance, variance_reason). Enforce ONE open shift per till with a unique partial index or a locked check.
STEP 2 Action `OpenShift` (pharmacy_staff): select till, count and enter the opening float, record user, till and time, audit `shift.opened`.
STEP 3 E1: if a shift is already open on that till, refuse a second one with a clear message.
STEP 4 Livewire `Shifts\Open` with till picker showing which tills are free; header badge showing the open shift on every sales screen.
STEP 5 Helper `CurrentShift::forUser()` used by the sell, dispense and refund use cases to require an open shift.

TESTS: open shift records user, till, time and float; second open shift on the same till refused (AC-24); a user cannot hold two open shifts; shift required helper throws without one; audit entry.

DONE WHEN: all green.
````
