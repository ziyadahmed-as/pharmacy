# UC-41  Manage attendance and leave

| Field | Value |
|---|---|
| Stage | 7. Growth features (Phase 2) |
| Build order | 41 |
| Priority | Should (Phase 2) |
| Depends on | UC-39 |
| Suggested branch | `uc-41-manage-attendance-and-leave` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-41 Manage attendance and leave. Depends on: UC-39. BRD: FR-10.5, 10.6.

STEP 1 Data: `attendance` (staff, clock_in, clock_out, lateness, absence flag), `leave_types`, `leave_balances`, `leave_requests` (type, dates, status).
STEP 2 Staff clock in and out; the system records lateness against the roster (UC-40) and absence.
STEP 3 Staff submit a leave request; the PM approves or rejects through UC-34; the balance updates; E1: leave above the balance is flagged to the Manager.
STEP 4 Monthly attendance report (export).
STEP 5 Visibility: staff see their own data; PM and Owner see all; pay data stays restricted (BR-13).

TESTS: lateness and absence calculated; leave approval updates balance; over-balance flagged; own-data visibility; report totals.

DONE WHEN: all green.
````
