# UC-06  Request stock adjustment

| Field | Value |
|---|---|
| Stage | 5. Inventory control and compliance |
| Build order | 28 |
| Priority | Must (Phase 1) |
| Depends on | UC-04, UC-34 |
| Suggested branch | `uc-06-request-stock-adjustment` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-06 Request stock adjustment. Depends on: UC-04, UC-34. BRD: FR-2.8; BR-07, BR-14. AC-08.

STEP 1 Data: `adjustment_requests` (batch, reason type damage|loss|expiry_write_off|found_stock, quantity signed, written reason, status, requested_by, approval_request_id, recount_done).
STEP 2 Register approval type `stock.adjustment` (UC-34): pharmacy_staff initiates, PM approves; if the PM initiates (A2) the Owner approves. Approval runs `StockMovementService::post()` (type adjustment) in a transaction and writes the audit entry.
STEP 3 Rules: reason type and written reason mandatory; the adjustment may not make stock negative (E2); when the value is above `inventory.adjustment_value_threshold` (or the threshold is "Not set" and policy says so) the request needs a recount before approval (BR-07).
STEP 4 A1: rejection leaves stock unchanged and notifies the requester. E1: the initiator cannot approve. E3: an unapproved request expires with no stock change (UC-34 expiry).
STEP 5 Controlled products: a second named person is recorded (UC-22 rule).
STEP 6 Livewire: request form (product and batch picker, reason type, quantity, reason), request list with status, link to the approval.
STEP 7 Posted receipts are corrected only through this use case.

TESTS: staff requests, PM approves, ledger shows the adjustment (AC-08); initiator cannot approve; rejection and expiry leave stock unchanged; negative stock refused; recount required above threshold; Owner approves a PM request; controlled item needs second person.

DONE WHEN: all green.
````
