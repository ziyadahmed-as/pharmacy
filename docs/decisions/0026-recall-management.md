# 0026. Recall Management

## Status
Accepted

## Context
Previously, a recall was merely represented as a row in `quarantine_records` with reason `'recalled'`.
Under BRD FR-2.11 and FR-2.12 (UC-10), dedicated recall tracking is required:
- Recalls need their own lifecycle tracking: sequential `recall_no` (prefix `RCL`), product link, reason, recall date, severity classification (`class_i`, `class_ii`, `class_iii`), and status (`open`, `closed`).
- Affected batches must be linked via `recall_batches` (`recall_id`, `batch_id`, `quantity_found`, `quantity_quarantined`, `action`).
- Stock on hand across storage locations must immediately move into quarantine via `StockMovementService::post()`, refusing any subsequent sales.
- Sales and dispensing events that dispensed or sold the affected batch must be traced (`TraceRecallSales`) to provide customer and prescriber contact information for follow-up callbacks.
- Closing a recall must proceed through the approval engine (`CloseRecallHandler` on `stock.recall.close`) requiring Pharmacy Manager or Owner sign-off with reason/evidence.
- Compliance recall reports must report on both structured `Recall` entities and any legacy quarantine records.

## Decision
1. **Database Schema**:
   - Created `recalls` table with `status IN ('open', 'closed')` check constraint and `recall_no` unique sequence.
   - Created `recall_batches` table with unique constraint on `(recall_id, batch_id)`.
   - Seeded `recall` sequence with prefix `RCL` in `document_sequences` via migration and `DocumentSequenceSeeder`.
2. **Actions**:
   - `StartRecall`: Validates user authority (`stock.dispose` or `catalogue.manage`), generates sequential `RCL-xxx` identifier, creates `QuarantineRecord`s and transfers all sellable on-hand stock per location to quarantine via `StockMovementService::post()`, records `stock.recall.started` in audit log, and notifies Owner & Pharmacy Manager.
   - `TraceRecallSales`: Collects customer names, phone numbers, quantities, receipt numbers, and prescriber details across completed sales and dispensing events that used any of the recalled batches.
   - `RequestCloseRecall`: Routes closure through the generic approval engine (`ApprovalService::request`) targeting `stock.recall.close`.
3. **Approval Handler**:
   - `CloseRecallHandler`: Registered in `AppServiceProvider`. Eligible approvers are Owner and Pharmacy Manager. On approval, marks the recall as `closed` with `closed_at` timestamp, updates batch actions to `completed`, and writes `stock.recall.closed` audit log.
4. **Sellability & FEFO Enforcement**:
   - `SellabilityGuard` and `FefoBatchResolver` immediately block and exclude batches linked to open `QuarantineRecord` with `reason = 'recalled'` or open `RecallBatch` entries.
5. **Compliance Reporting**:
   - `ComplianceRecallsReport` reports structured recall events with recall numbers, products, batches, quantities quarantined/disposed, remaining balances, and statuses.

## Consequences
- Product and batch recalls are strictly tracked, gapless sequential numbers are maintained, affected stock is atomically quarantined, customer contacts are promptly traced, and closures are auditable and require dual-control approval.
