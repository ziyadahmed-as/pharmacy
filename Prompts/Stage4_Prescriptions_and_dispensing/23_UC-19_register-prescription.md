# UC-19  Register prescription

| Field | Value |
|---|---|
| Stage | 4. Prescriptions and dispensing |
| Build order | 23 |
| Priority | Must (Phase 1) |
| Depends on | UC-25, UC-01 |
| Suggested branch | `uc-19-register-prescription` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-19 Register prescription. Depends on: UC-25, UC-01. BRD: FR-4.2, 4.8, 4.11.

STEP 1 Data: `prescriptions` (customer, prescriber name, prescriber registration no, date, status enum registered|pending_verification|verified|partially_dispensed|fully_dispensed|rejected|cancelled|expired, scan path, replaced_by_id nullable, created_by), `prescription_lines` (product, dose, quantity prescribed, quantity dispensed), `prescription_status_history`.
STEP 2 Action `RegisterPrescription` (pharmacy_staff): validate mandatory fields (E2), prescriber number, validity period from `prescriptions.validity_days` (E1: older than the period is rejected; if the setting is "Not set" show a blocking message for the PM), and that it is not already fully dispensed. Save as Registered then Pending Verification; log every status change.
STEP 3 A1: link the patient to an existing customer or create one inline through UC-25 actions.
STEP 4 A2: `CancelAndReRegister`: a registered prescription is corrected by cancelling with a reason and registering a new one linked to the original (FR-4.11); the original is never edited.
STEP 5 Private scan upload (image or PDF, size limit).
STEP 6 Livewire: new prescription form (lines with product search restricted to Rx and controlled items), scan upload, status badge.

TESTS: valid prescription registers and moves to Pending Verification; too old rejected; missing field not saved; cancel and re-register keeps and links the original; status history complete; only products with Rx or controlled classification can be added.

DONE WHEN: all green.
````
