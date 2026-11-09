# UC-31  Review audit log

| Field | Value |
|---|---|
| Stage | 1. Foundation and security |
| Build order | 6 |
| Priority | Must (Phase 1) |
| Depends on | UC-33 |
| Suggested branch | `uc-31-review-audit-log` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-31 Review audit log. Depends on: UC-33. BRD: FR-7.5, 8.3, 8.9, 8.10; BR-15. AC-19, AC-35.

GOAL: Owner and Manager can find unusual activity; nobody can change a log entry.

STEP 1 Query object `AuditLogQuery` with filters: user, role used, action key, date range, subject type. Index the columns used. Document the action keys in `docs/audit-actions.md` (void, refund, adjustment, disposal, FEFO override, price change, login failures, health-data views).
STEP 2 Livewire `Audit\Index`: filters, paginated list showing user, role used, time, terminal, old and new values; detail drawer; export CSV and PDF (log the export as `audit.export`).
STEP 3 Authorisation: owner and PM full view and export; SA view of technical entries only, with patient, sales, cost and financial values masked (E2); pharmacy_staff none.
STEP 4 No update or delete anywhere: no routes, no policy ability, no model method (E1).

TESTS: filters return correct rows; export contains the same rows and is itself audited; SA sees masked values; staff denied incl. direct URL; attempts to update or delete via Eloquent, query builder and Livewire call fail (AC-19); arch test stays green.

DONE WHEN: all green; AC-19 passes.
````
