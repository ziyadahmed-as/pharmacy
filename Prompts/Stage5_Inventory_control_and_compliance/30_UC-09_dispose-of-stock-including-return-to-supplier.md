# UC-09  Dispose of stock, including return to supplier

| Field | Value |
|---|---|
| Stage | 5. Inventory control and compliance |
| Build order | 30 |
| Priority | Must (Phase 1) |
| Depends on | UC-08, UC-34 |
| Suggested branch | `uc-09-dispose-of-stock-including-return-to-supplier` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-09 Dispose of stock. Depends on: UC-08, UC-34. BRD: FR-2.16 to 2.19; BR-07, BR-11. AC-10, AC-25. Conflict C-8: return to supplier (FR-2.16) is built here as a disposal method.

STEP 1 Data: `disposals` (batch, quantity, reason expired|damaged|recalled|contaminated|other, method return_to_supplier|incineration|authorised_collector|other, supplier_name when returning, status, requested_by, witness_id nullable, certificate_no, evidence path), linked to the `quarantine_records` of UC-08. Lists of methods and collectors are editable data (OQ-04).
STEP 2 Action `RequestDisposal` (pharmacy_staff): only quarantined stock may be disposed (precondition). A1: for controlled drugs record the licensed performing person AND an independent named witness who is a different person (E2).
STEP 3 Register approval type `stock.disposal` (UC-34): PM approves, Owner if the PM raised it; E1 the initiator cannot approve; approval refused for controlled drugs without a witness. A2 rejection leaves stock in quarantine.
STEP 4 Approval handler in one transaction: write the disposal record, remove stock through `StockMovementService` (type disposal or supplier_return), write controlled-drug register entries where applicable, generate the printable disposal certificate with signature lines (FR-2.19), allow attaching a photo or official document. For return to supplier, record batch, quantity, reason and supplier name; no supplier account or credit note (FR-2.16).
STEP 5 Disposal loss report (FR-2.20, Should): build only the query, expose it in UC-30 later.
STEP 6 Livewire: disposal request form, certificate view and print, list with statuses.

TESTS: only quarantined stock can be disposed; PM approval removes stock, writes record and certificate; initiator cannot approve (AC-10); controlled disposal without witness refused, witness equal to performer refused (AC-10); rejection keeps stock in quarantine; return to supplier reduces the specific batch and rejects quantity above the batch (AC-25); audit trail complete.

DONE WHEN: all green.
````
