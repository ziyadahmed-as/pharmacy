# UC-20  Verify prescription

| Field | Value |
|---|---|
| Stage | 4. Prescriptions and dispensing |
| Build order | 24 |
| Priority | Must (Phase 1) |
| Depends on | UC-19, UC-39 |
| Suggested branch | `uc-20-verify-prescription` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-20 Verify prescription. Depends on: UC-19, UC-39. BRD: FR-4.3, 4.8; BR-03, BR-12; OQ-09. AC-13.

STEP 1 Action `VerifyPrescription`: allowed only if `LicenceGate::allows($user, 'prescription.verify')` is true: valid, unexpired licence on the staff record and, when `prescriptions.licence_categories_allowed` is set, a licence category in that list (OQ-09 default: any valid licence).
STEP 2 On verify: record who and when and set status Verified. A1: Reject with a reason sets Rejected and logs a suspect-prescription event (FR-4.12).
STEP 3 E1: an expired licence or a disallowed category refuses the action with a clear message and an audit entry `prescription.verify.refused`.
STEP 4 Livewire: pending-verification queue, detail view with scan, checks list (authenticity, completeness, drug, dose, quantity), Verify and Reject buttons.
STEP 5 Both pharmacists and technicians use the same role; only the licence record differs.

TESTS: licensed staff verifies; expired licence refused (AC-13); disallowed category refused when the setting is on; reject with reason; who and when recorded; status history; staff without a staff record cannot verify.

DONE WHEN: all green.
````
