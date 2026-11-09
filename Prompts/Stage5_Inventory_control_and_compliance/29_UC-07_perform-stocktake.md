# UC-07  Perform stocktake

| Field | Value |
|---|---|
| Stage | 5. Inventory control and compliance |
| Build order | 29 |
| Priority | Must (Phase 1) |
| Depends on | UC-04, UC-06 |
| Suggested branch | `uc-07-perform-stocktake` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-07 Perform stocktake. Depends on: UC-04, UC-06. BRD: FR-2.9, 2.10; BR-07. AC-09.

STEP 1 Data: `stocktakes` (type full|partial, scope location or category, status draft|counting|review|approved|rejected, created_by, approved_by), `stocktake_lines` (batch, system_qty snapshot, counted_qty, variance, recount_qty, counted_by, second_person_id for controlled items).
STEP 2 Action `CreateStocktake` (PM): generate the count sheet for the scope; block a second active stocktake over the same items (precondition). Count sheet hides cost from staff.
STEP 3 Staff enter counts by batch (`RecordCount`). The system computes variances by quantity (FR-2.9; value shown only to owner and PM). A1: variance above `inventory.recount_threshold` forces a recount before approval. A3: controlled-drug counts need a second named person.
STEP 4 Action `ApproveStocktake` (PM): approve posts one adjustment per variance through `StockMovementService` (reusing the UC-06 posting path) and closes the stocktake; A2 reject posts nothing; E1 an unapproved count leaves stock unchanged. If the PM started it, the Owner approves.
STEP 5 Livewire: stocktake list, count entry (scanner friendly), variance review, approve and reject, printable count sheet.

TESTS: full and partial count sheets; variances correct; recount rule; approval posts adjustments and ledger; rejection and non-approval change nothing (AC-09); controlled count needs second person; two active stocktakes over one item refused.

DONE WHEN: all green.
````
