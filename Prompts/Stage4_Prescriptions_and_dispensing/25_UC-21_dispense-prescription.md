# UC-21  Dispense prescription

| Field | Value |
|---|---|
| Stage | 4. Prescriptions and dispensing |
| Build order | 25 |
| Priority | Must (Phase 1) |
| Depends on | UC-20, UC-13 |
| Suggested branch | `uc-21-dispense-prescription` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-21 Dispense prescription. Depends on: UC-20, UC-13. BRD: FR-4.1, 4.8 to 4.10, 3.4, 3.5, 2.4; BR-03, BR-12, BR-17. AC-03, AC-12.

GOAL: dispense only verified, valid prescriptions, linked to batch, user and receipt.

STEP 1 Data: `dispensing_events` (prescription_line, batch, quantity, user, sale_id, dispensed_at).
STEP 2 Extend the UC-13 sale flow: Rx and controlled products cannot be sold without a linked, Verified prescription (E1, AC-03). Reuse `SellabilityGuard`, `FefoBatchResolver`, payment and receipt code; do not duplicate them.
STEP 3 Action `DispensePrescriptionLine`: needs an open shift and `LicenceGate` (expired or disallowed refused); prescription must be Verified and in validity (E2: expired or fully dispensed refused); show remaining quantity per line; propose the FEFO batch.
STEP 4 Quantity control: block quantity above the prescribed remaining quantity unless a licensed staff member overrides with a reason (A2, BR-17); warn when the same patient received the same medicine within `prescriptions.repeat_purchase_warning_days` and allow a reasoned override (A1).
STEP 5 In one transaction create the sale, dispensing event, stock movements and update the status to Partially or Fully Dispensed. A3: a controlled product hands over to UC-22.
STEP 6 E3: a dispensed prescription cannot be edited; correct by cancel and re-register (UC-19).
STEP 7 Livewire `Dispense\Show`: lines with remaining quantities, batch proposal, override dialogs, payment panel from UC-13.

TESTS: unverified prescription blocked (AC-03); fully dispensed or expired refused (AC-12); quantity above prescription blocked, allowed only with licensed override and reason; repeat warning and override; event links line, batch, quantity, user, time and receipt; status updates; expired licence refused; double submit creates one dispensing.

DONE WHEN: all green.
````
