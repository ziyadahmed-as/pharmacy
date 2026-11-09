# UC-15  Void a sale

| Field | Value |
|---|---|
| Stage | 3. Sales and cash shifts |
| Build order | 19 |
| Priority | Must (Phase 1) |
| Depends on | UC-13, UC-34 |
| Suggested branch | `uc-15-void-a-sale` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-15 Void a sale. Depends on: UC-13, UC-34. BRD: FR-3.7; BR-05, BR-14. AC-14.

STEP 1 Register approval type `sale.void` in the UC-34 engine. Payload: sale id, reason. Initiator pharmacy_staff, approver PM (Owner if the PM raised it).
STEP 2 Action `RequestVoid`: find the sale by receipt number, reason required, day must not be locked (E2: if locked, refuse and point to approved adjustment). Creates the approval request.
STEP 3 Handler `onApproved`: in one transaction mark the sale voided, keep the original rows, create reversal ledger movements returning stock to the SAME batches, reverse payments, update shift and cash totals; audit shows initiator and approver.
STEP 4 A1: rejection leaves the sale valid and shows the comment to the requester. E1: staff cannot approve their own void.
STEP 5 Livewire: "Request void" on the sale view; status of the request; voided sales shown with a Voided mark in lists and reports.

TESTS: request, approve, stock and cash reversed to the same batches (AC-14); original sale kept; rejection leaves everything unchanged; initiator cannot approve; locked day refused; both users in audit; a voided sale cannot be voided again.

DONE WHEN: all green.
````
