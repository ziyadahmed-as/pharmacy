# UC-18  Close and lock the day

| Field | Value |
|---|---|
| Stage | 3. Sales and cash shifts |
| Build order | 21 |
| Priority | Must (Phase 1) |
| Depends on | UC-17 |
| Suggested branch | `uc-18-close-and-lock-the-day` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-18 Close and lock the day. Depends on: UC-17. BRD: FR-3.10, 6.1, 6.6. AC-24.

STEP 1 Data: `daily_closings` (date unique, status open|locked, system totals by method json, counted totals json, variances json, reasons, closed_by, locked_at, verified_by).
STEP 2 Action `LockDay` (pharmacy_manager or owner): refuse when any shift of the day is still open (E1); show the daily cash summary by payment method including refunds and voids; the user enters counted bank and mobile-money totals; the system compares by method and shows variances; a reason is required per variance; confirm with `ConfirmWithPassword` and lock.
STEP 3 A1: the Owner may confirm and lock instead of the Manager.
STEP 4 E2: after locking, no sale, void, refund or adjustment may change that day; later changes need an approved adjustment (stock and cash correction records dated on the day they are approved, referencing the locked day).
STEP 5 Livewire `Closing\Day`: summary, counted entry, variances, reasons, lock, printable closing report. List held sales left open for review.

TESTS: open shift blocks closing (E1); variances per method and reasons; lock prevents void and refund for that day (E2); Owner can lock (A1); closing report totals equal the shift totals; audit `day.locked`.

DONE WHEN: all green; Stage 3 milestone demonstrable end to end.
````
