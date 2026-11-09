# UC-28  Record operating expense

| Field | Value |
|---|---|
| Stage | 6. Finance, reporting and go-live readiness |
| Build order | 33 |
| Priority | Must (Phase 1) |
| Depends on | UC-33 |
| Suggested branch | `uc-28-record-operating-expense` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-28 Record operating expense. Depends on: UC-33. BRD: FR-6.2. OQ-06 (expense limit is a setting).

STEP 1 Data: `expenses` (category rent|salaries|utilities|transport|other, amount, date, description, proof path nullable, status posted|pending_approval, created_by, approved_by).
STEP 2 Action `RecordExpense` (PM, Owner): category and amount mandatory (E1). Amounts above `finance.expense_approval_limit` need the Owner's approval through UC-34; if the limit is "Not set", follow the default policy of approval required for the PM's entries.
STEP 3 Expenses are never deleted; corrections by a reversing entry with a reason. Audit `expense.recorded`.
STEP 4 Livewire: expense list with filters, new expense form with proof upload, status.
STEP 5 Pharmacy_staff and SA have no access.

TESTS: save with all fields; missing category or amount refused; above-limit goes to Owner approval and PM cannot approve their own; reversal entry; staff and SA denied; entries appear in the UC-29 summary.

DONE WHEN: all green.
````
