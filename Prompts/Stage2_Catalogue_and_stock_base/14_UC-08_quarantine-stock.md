# UC-08  Quarantine stock

| Field | Value |
|---|---|
| Stage | 2. Catalogue and stock base |
| Build order | 14 |
| Priority | Must (Phase 1) |
| Depends on | UC-04 |
| Suggested branch | `uc-08-quarantine-stock` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-08 Quarantine stock. Depends on: UC-04. BRD: FR-2.15, 2.5; BR-01, BR-10. AC-07. Conflict C-7: quarantine is immediate, release is approved.

GOAL: bad stock leaves sellable stock at once and can only leave quarantine by an approved route.

STEP 1 Data: `quarantine_records` (batch, quantity, reason expired|damaged|recalled|contaminated|other, status open|released|disposed|returned, created_by, note).
STEP 2 Action `QuarantineStock` (pharmacy_staff, PM): move quantity from sellable to quarantine through `StockMovementService` (ledger rows), reason required, audit `stock.quarantine.created`.
STEP 3 Implement `ExpiredBatchQuarantiner` (A1) and command `batches:quarantine-expired` (daily, and on expiry date): moves expired quantities automatically; system user in the audit.
STEP 4 Release (A2): `RequestRelease` by staff with a reason creates an approval (UC-34) to the PM; on approval the stock returns to sellable; a recalled batch cannot be released.
STEP 5 E1: update the sellability guard so quarantined stock cannot be sold, held or dispensed; blocked attempts write an audit entry.
STEP 6 Livewire: quarantine list (separate quantity display), "Quarantine" form, release request, status of each record. Leave disposal and supplier return to UC-09.

TESTS: manual quarantine moves stock and writes the ledger; expiry command quarantines automatically and the batch cannot be sold (AC-07); release needs PM approval and initiator cannot approve; recalled batch cannot be released; sale attempt on quarantined stock blocked and logged (E1); quarantine quantity excluded from sellable totals and valuation.

DONE WHEN: all green; Stage 2 milestone demonstrable with opening stock loaded through UC-03 and UC-04.
````
