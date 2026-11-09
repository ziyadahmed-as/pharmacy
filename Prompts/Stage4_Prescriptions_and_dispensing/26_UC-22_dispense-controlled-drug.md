# UC-22  Dispense controlled drug

| Field | Value |
|---|---|
| Stage | 4. Prescriptions and dispensing |
| Build order | 26 |
| Priority | Must (Phase 1) |
| Depends on | UC-21 |
| Suggested branch | `uc-22-dispense-controlled-drug` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-22 Dispense controlled drug. Depends on: UC-21. BRD: FR-4.4, 4.3, 4.9; BR-03, BR-12. AC-13, AC-34. OQ-01: the whole register module is switchable (`pharmacy.handles_controlled_drugs`, default on). Conflict C-6.

STEP 1 Data: `controlled_drug_register` (product, batch, entry type receipt|dispense|adjustment|disposal, quantity, running balance, patient, prescriber, user, second_person_id nullable, sale_id, stock_movement_id).
STEP 2 Service `ControlledDrugRegister::record()` called INSIDE the same transaction as the stock movement so stock and register can never disagree. A database-level or service-level check keeps the running balance equal to the stock ledger.
STEP 3 Action `DispenseControlledDrug`: follows UC-21 steps; detects controlled classification; checks the dispensing user's licence (E1: expired refused); staff confirm patient and prescriber details; payment and receipt as UC-13; register entry with patient, prescriber, dispensing user, quantity and running balance.
STEP 4 E2: any other stock movement of a controlled drug (adjustment, count, quarantine, disposal) requires a second named person recorded in the register; wire this into `StockMovementService` for controlled products.
STEP 5 Receipts of controlled stock (UC-04) also create register entries.
STEP 6 Livewire: controlled-dispense confirmation panel, register viewer (read-only, exportable by PM and Owner).

TESTS: controlled dispensing creates a register entry and the running balance equals stock; expired licence refused (AC-13); non-dispensing movement without a second person refused; register is append-only; pharmacy_staff can dispense but not approve or see cost (AC-34); switch off hides the module.

DONE WHEN: all green.
````
