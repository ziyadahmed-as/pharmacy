# UC-11  View stock and ledger

| Field | Value |
|---|---|
| Stage | 2. Catalogue and stock base |
| Build order | 12 |
| Priority | Must (Phase 1) |
| Depends on | UC-04 |
| Suggested branch | `uc-11-view-stock-and-ledger` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-11 View stock and ledger. Depends on: UC-04. BRD: FR-2.10, 7.2; BR-06. AC-16.

GOAL: everyone can see stock and history; nobody can edit it; staff never see cost.

STEP 1 Query objects `StockOnHandQuery` (by product and batch with expiry, location, status sellable or quarantine) and `LedgerQuery` (date-time, user, type, in or out, balance, source document with link).
STEP 2 Livewire `Stock\Index`: search by name, generic or barcode; expand a product to its batches; quarantine quantity shown separately; filters (low stock, near expiry, expired).
STEP 3 Livewire `Stock\Ledger`: per product or batch, paginated, filter by type and date; each row links to its source document.
STEP 4 Export (CSV, PDF) only for owner and PM (A: "if allowed"). Unit cost and valuation columns only for owner and PM (BR-06); hidden from pharmacy_staff in the query, not just the view.
STEP 5 E1: the ledger is read-only: no route, ability or method to change it.

TESTS: stock by batch is correct after receipts; quarantine shown separately; ledger balances reconcile with stock; staff do not receive cost in data or HTML (AC-16); export denied to staff; attempts to edit the ledger fail; 500,000 ledger rows still page under 2 s (seed and measure).

DONE WHEN: all green.
````
