# UC-05  Monitor stock alerts and reorder list

| Field | Value |
|---|---|
| Stage | 2. Catalogue and stock base |
| Build order | 13 |
| Priority | Must (Phase 1) |
| Depends on | UC-04, UC-38 |
| Suggested branch | `uc-05-monitor-stock-alerts-and-reorder-list` |

**How to use this file**

1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-05 Monitor stock alerts and reorder list. Depends on: UC-04, UC-38. BRD: FR-2.6, 2.7, 9.1; D-06. AC-33.

GOAL: the Manager sees what to reorder and what is about to expire; no purchase order exists.

STEP 1 Command `stock:evaluate-alerts` (daily and after stock changes) producing alert rows: low stock (at reorder level), critical (below minimum, A1), near-expiry windows from `inventory.near_expiry_alert_days` (default 90, 60, 30), expired. Avoid duplicates per batch and window. Use `Notifier` for in-app alerts.
STEP 2 Expired handling (A2): the sellability guard already refuses expired batches; define the interface `ExpiredBatchQuarantiner` and leave the implementation to UC-08. Do not move stock here.
STEP 3 Livewire `Alerts\Index`: filter by type, mark seen, link to product and batch.
STEP 4 Reorder list: for items at or below reorder level show on hand, reorder level, maximum level, suggested quantity (maximum minus on hand), and preferred supplier name text. Print and export CSV/PDF. Warn that orders are placed outside the PMS. Add the receipt warning when a receipt would exceed the maximum level.
STEP 5 Near-expiry panel: batches by window with the decision options (sell first by FEFO, return to supplier, prepare disposal) as links to later use cases.

TESTS: alerts appear at the right thresholds and only once; critical flag below minimum (A1); reorder list math; export works; PM sees the screen, staff see alerts read-only; expired batch flagged (AC-33).

DONE WHEN: all green.
````
