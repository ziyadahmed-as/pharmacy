# UC-04  Receive stock

| Field | Value |
|---|---|
| Stage | 2. Catalogue and stock base |
| Build order | 11 |
| Priority | Must (Phase 1) |
| Depends on | UC-01, UC-34, UC-38 |
| Suggested branch | `uc-04-receive-stock` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-04 Receive stock. Depends on: UC-01, UC-34, UC-38. BRD: FR-2.1 to 2.3, 2.5; BR-08, BR-19. AC-05, AC-06, AC-20. Conflicts C-1 (cost) and C-3 (confirmation): follow the BRD defaults.

GOAL: the only way stock enters the system, by batch, safely and once.

STEP 1 Data: `batches` (product, batch_no, manufacturing date, expiry date, unit_cost nullable, supplier_name text), `batch_stocks` (batch, location nullable, status sellable|quarantine, quantity), `stock_movements` (append-only ledger: type, signed quantity in base units, balance_after, source type and id, user, created_at), `stock_receipts` (reference, supplier text, delivery-note no, date, status draft|pending_confirmation|posted|rejected, idempotency_key unique, created_by) and `stock_receipt_lines` (product, batch_no, mfg, expiry, quantity, unit_cost nullable, flag short|over|damaged|ok).
STEP 2 `DocumentSequence::next()` (locked row) and `StockMovementService::post()` (the only writer of `batch_stocks`; rejects negative; writes the ledger). Add the arch test that enforces this.
STEP 3 Action `SubmitStockReceipt` (pharmacy_staff): validate batch and expiry present, expiry later than manufacturing date, remaining shelf life against `inventory.minimum_shelf_life_days` (E1). A1: below minimum shelf life rejects the line unless the PM overrides with a reason through an approval request (UC-34). A2: an existing batch gets the quantity added. E2: inactive product refused. Unit cost is optional input visible only to owner and PM (C-1).
STEP 4 Posting: if `inventory.receipt_requires_confirmation` is true (default) the receipt waits for the PM (BR-19); on confirmation, in ONE transaction, good quantities go to sellable stock, damaged quantities to quarantine, ledger rows are written (E3: all-or-nothing). Setting false posts immediately.
STEP 5 Idempotency (E4): unique idempotency key and a disabled submit button; a repeat creates no second receipt.
STEP 6 Livewire: receipt form (supplier, reference, date, scan or search lines, batch fields, short/over/damaged marks), draft save, confirmation screen for the PM through the approval queue.
STEP 7 Post-posting corrections only by approved adjustment (UC-06): no edit route for a posted receipt.

TESTS: receipt creates correct batches, expiry and quantity and is sellable only after PM confirms (AC-05); damaged goes to quarantine, short/over flagged, short-dated line rejected unless PM overrides with reason (AC-06); same batch adds quantity; inactive product refused; failure mid-post leaves no stock; double submit makes one receipt (AC-20); posted receipt cannot be edited; cost hidden from staff.

DONE WHEN: all green; `StockMovementService` documented for later use cases.
````
