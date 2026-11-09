# UC-10  Manage recall

| Field | Value |
|---|---|
| Stage | 5. Inventory control and compliance |
| Build order | 31 |
| Priority | Must (Phase 1) |
| Depends on | UC-08, UC-09 |
| Suggested branch | `uc-10-manage-recall` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-10 Manage recall. Depends on: UC-08, UC-09. BRD: FR-2.11, 2.12; D-11; BR-01. AC-11. Conflict C-5: the trace report is Should; build it last behind a feature flag.

STEP 1 Data: `recalls` (source supplier|authority, reference, date, reason, status open|closed, closed_by, evidence path), `recall_batches` (recall, product, batch_no, batch_id nullable).
STEP 2 Action `RegisterRecall` (PM or staff): enter batch(es), source, reference, date, reason. The batch is blocked from sale immediately: set a `recalled` flag read by `SellabilityGuard` (BR-01). E1: if the batch is not found, log an unknown-batch recall and alert the PM.
STEP 3 The system lists stock on hand for the batch and moves it to quarantine through UC-08 (reason recalled). A recalled batch can never be released from quarantine.
STEP 4 Return or dispose recalled stock through UC-09. The PM notifies affected customers where required (a checklist item, no automatic messaging).
STEP 5 Action `CloseRecall` (PM): requires evidence upload and confirmation that no stock remains sellable; if the PM raised it the Owner confirms closing.
STEP 6 LAST, behind feature flag `recall.trace_report` (default off): trace report with receipts and supplier reference, stock on hand, sales and recorded customers (A1). Enable only when the BA confirms C-5.
STEP 7 Livewire: recall register, recall detail (batches, stock, actions), closing form.

TESTS: recall blocks sale at once (AC-11); stock moves to quarantine; recalled batch cannot be released; unknown batch logged and PM alerted (E1); close needs evidence; trace report appears only when the flag is on and lists customers who bought the batch (A1).

DONE WHEN: all green; Stage 5 milestone demonstrable.
````
