# UC-17  Close shift

| Field | Value |
|---|---|
| Stage | 3. Sales and cash shifts |
| Build order | 18 |
| Priority | Must (Phase 1) |
| Depends on | UC-12, UC-13 |
| Suggested branch | `uc-17-close-shift` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-17 Close shift. Depends on: UC-12, UC-13. BRD: FR-3.9. AC-24.

STEP 1 Action `CloseShift`: compute expected cash by payment method from completed sales, refunds, voids and recorded cash in and out; staff count cash and enter the amount; compute variance; a reason is required when variance is not zero; close and store the figures.
STEP 2 Add cash in and cash out entries during a shift (`shift_cash_movements`: type, amount, reason) because FR-3.9 needs them.
STEP 3 E1: a shift cannot be closed twice and a closed shift cannot be edited. A shift with a held sale warns the user.
STEP 4 Livewire `Shifts\Close`: shows expected totals by method, count entry, variance, reason, confirm. Print a shift summary.
STEP 5 Staff see only their own shift figures.

TESTS: expected totals match sales, refunds, voids and cash movements; variance and mandatory reason; closing twice refused; closed shift immutable; shift totals feed daily closing (AC-24); staff cannot see another user's shift.

DONE WHEN: all green.
````
