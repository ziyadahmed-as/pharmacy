# UC-29  View cash and revenue summaries

| Field | Value |
|---|---|
| Stage | 6. Finance, reporting and go-live readiness |
| Build order | 34 |
| Priority | Must (Phase 1) |
| Depends on | UC-18, UC-28 |
| Suggested branch | `uc-29-view-cash-and-revenue-summaries` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-29 View cash and revenue summaries. Depends on: UC-18, UC-28. BRD: FR-6.1, 6.6, 7.1. AC-24. Conflict C-1: build exactly what Use Case v1.1 says; the profit and loss extension is conditional.

STEP 1 Query objects `CashSummaryQuery` and `RevenueSummaryQuery`: for a day or period show cash by payment method, gross sales, refunds, net sales (refunds reduce revenue) and recorded expenses; daily reconciliation figures from locked days (FR-6.6).
STEP 2 Livewire `Finance\CashRevenue`: day or period picker, tables, export and print (PDF, Excel). Only owner and PM (E: not visible to staff or SA).
STEP 3 E1 label on screen: "This is not a profit and loss statement." State that cost of goods is not part of this view.
STEP 4 CONDITIONAL EXTENSION, build only after the BA confirms cost is kept (C-1, FR-6.3, 6.7, 7.3, D-07, AC-30): profit and loss with revenue, cost of goods at batch cost, gross profit, expenses and net profit; inventory valuation at batch cost with quarantine shown separately. Cost data is visible only to owner and PM through `CostVisibility`. If the BA says no, skip this step and record the decision.

TESTS: totals equal the sum of completed sales, refunds, voids and expenses for the period; refunds reduce net sales; locked-day figures match the closing record (AC-24); export equals screen; staff and SA denied; (if the extension is built) P&L and valuation reconcile with ledger and batch costs (AC-30).

DONE WHEN: all green.
````
