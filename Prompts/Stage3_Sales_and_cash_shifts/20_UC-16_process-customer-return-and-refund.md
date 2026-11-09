# UC-16  Process customer return and refund

| Field | Value |
|---|---|
| Stage | 3. Sales and cash shifts |
| Build order | 20 |
| Priority | Must (Phase 1) |
| Depends on | UC-13, UC-08, UC-34 |
| Suggested branch | `uc-16-process-customer-return-and-refund` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-16 Process customer return and refund. Depends on: UC-13, UC-08, UC-34. BRD: FR-3.8; BR-18. AC-15.

STEP 1 Data: `sale_returns` and `sale_return_lines` (sale line, quantity, reason, assessment resaleable|not_resaleable, refund amount, method).
STEP 2 Action `ProcessReturn`: find the sale by receipt number (E1: no receipt, refuse), check the return window from `sales.return_window_days` (A1: outside window refuses, or refers to the PM when policy allows), quantity cannot exceed what was sold (E2).
STEP 3 Staff assess each line: resaleable goes back to the same batch's sellable stock; not resaleable goes to quarantine via UC-08 (BR-18). All through `StockMovementService`.
STEP 4 Refund = original unit price x quantity, paid by the original method. Within `sales.refund_limit` staff complete it; above it (or when the limit is "Not set") an approval `sale.refund` goes to the PM (UC-34) before any money or stock moves.
STEP 5 Print a refund receipt (marked REFUND, sequential number); update ledger, revenue and cash for the shift.
STEP 6 Livewire: return screen with line selection, assessment, refund preview and status.

TESTS: resaleable item returns to stock, non-resaleable goes to quarantine (AC-15); refund above limit waits for PM approval; no receipt refused; quantity above sold refused; outside window refused; refund reduces revenue and cash; audit records the assessment.

DONE WHEN: all green.
````
