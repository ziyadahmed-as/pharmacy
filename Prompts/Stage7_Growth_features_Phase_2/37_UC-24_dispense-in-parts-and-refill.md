# UC-24  Dispense in parts and refill

| Field | Value |
|---|---|
| Stage | 7. Growth features (Phase 2) |
| Build order | 37 |
| Priority | Should (Phase 2) |
| Depends on | UC-21 |
| Suggested branch | `uc-24-dispense-in-parts-and-refill` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-24 Dispense in parts and refill. Depends on: UC-21. BRD: FR-4.5, 4.8, 4.10; D-08.

STEP 1 Extend `prescription_lines` with remaining quantity, refills allowed, refills used, next refill date.
STEP 2 Action `DispensePartially`: reuse UC-21; dispense a chosen partial quantity or the next refill; show remaining quantity and next refill date; status stays Partially Dispensed until the full quantity is supplied.
STEP 3 E1: refuse a quantity above the remaining amount or a refill after expiry.
STEP 4 Livewire: remaining-quantity panel and refill button on the dispense screen.

TESTS: partial dispense updates remaining and next refill date; status transitions; excess quantity refused; expired refill refused; each event linked to batch and receipt.

DONE WHEN: all green.
````
