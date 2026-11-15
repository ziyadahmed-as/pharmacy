# 0022. Database-Level Immutability and Constraints

## Context

Prior to this migration:
- Audit logs and controlled drug register triggers only covered SQLite and MySQL.
- Stock movements ledger and completed sales/lines/payments were protected solely at the application layer through Eloquent model events. Raw SQL updates or deletes could bypass these protections.
- Crucial database-level invariants (such as `batch_stocks.quantity >= 0`, `sale_lines.quantity > 0`, `payments.amount > 0`, and enum-like status/type value boundaries) were not enforced by `CHECK` constraints on all database engines.
- Multiple transactional and audit-linked foreign keys relied on `cascadeOnDelete()` or `nullOnDelete()`, risking accidental deletion of audit and operational history.

## Decision

1. **`App\Support\AppendOnly` Helper Class**:
   - Supports SQLite, MySQL/MariaDB, and PostgreSQL (using PL/pgSQL functions raising exceptions).
   - Generates and executes database-specific triggers:
     - Pure append-only (`audit_logs`, `controlled_drug_register`, `stock_movements`): both UPDATE and DELETE rejected.
     - Delete-protected (`approval_requests`): DELETE rejected, UPDATE permitted.
     - Sales immutability (`sales`): Once completed, DELETE is rejected; UPDATE is restricted exclusively to `status` and `reprint_count`.
     - Completed sale dependents (`sale_lines`, `payments`): UPDATE and DELETE rejected once the referenced sale is completed or voided.
   - Cross-driver CHECK constraint abstraction:
     - Uses `ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)` for MySQL 8 and PostgreSQL.
     - Uses BEFORE INSERT and BEFORE UPDATE triggers with `WHEN NOT (...)` for SQLite.

2. **CHECK Constraints Enforced**:
   - `batch_stocks.quantity >= 0`
   - `batch_stocks.status IN ('sellable', 'quarantine')`
   - `sale_lines.quantity > 0` and `sale_lines.subtotal >= 0`
   - `payments.amount > 0`
   - `sales.total >= 0` and `sales.status IN ('completed', 'voided')`
   - `stock_movements.type` and `stock_status`
   - `shifts.status IN ('open', 'closed')` and `opening_float >= 0`
   - `daily_closings.status IN ('open', 'locked')`
   - `approval_requests.status` and `prescriptions.status` allowed states

3. **Referential Integrity (`restrictOnDelete`)**:
   - Replaced all `cascadeOnDelete` and `nullOnDelete` foreign keys with `restrictOnDelete` across audit logs, sales, sale lines, payments, batch stocks, batches, stock receipts, quarantine records, prescriptions, dispensing events, shift cash movements, password history, and identity verifications.
   - Deleting parent entities (such as users with audit logs or products with batches) fails with a foreign key constraint violation.

4. **Supported Production Database**:
   - Documented in `README.md` and `.env.example`: **MySQL 8** (as mandated by AGENTS.md; PostgreSQL 14+ is also fully supported by all DDL and trigger definitions; SQLite is preserved for fast in-memory testing).
