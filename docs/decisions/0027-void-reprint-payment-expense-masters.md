# ADR-0027 — Void Record, Receipt Reprint Log, Payment Method and Expense Category Masters

**Date:** 2026-11-15  
**Branch:** `uc-masters-voids-reprints`  
**Status:** Accepted  
**UC refs:** UC-15, UC-28, UC-30  
**FR refs:** FR-3.7, FR-6.2, FR-7.1  
**BR refs:** BR-05, BR-14  

---

## Context

Before this change:

| Concern | State |
|---|---|
| Void reason | Stored only in `ApprovalRequest.payload`; no first-class audit row |
| Receipt reprints | Tracked by `sales.reprint_count` integer — no who, when, or why |
| Payment method | Free-text `payments.method` and `sale_returns.payment_method` strings |
| Expense category | Free-text `expenses.category` string from a PHP `const` list |

Each weakness created reporting gaps and compliance risks: voids could not be traced to an approver without joining through the approval engine, reprints had no paper trail, and payment / category data could drift as the business added new methods or renamed categories.

---

## Decision

### STEP 1 — `sale_voids` (void record)

A new append-only table captures every approved void:

```
sale_voids (id, sale_id UNIQUE, reason, requested_by, approved_by, approval_request_id, voided_at)
```

- Populated by `VoidSaleHandler::onApproved()` after the existing stock-reversal logic.  
- Protected by `AppendOnly::create('sale_voids', blockUpdate: true, blockDelete: true)` SQLite triggers.  
- `Sale::voidRecord()` hasOne relation; `livewire/sales/show` and `AuditVoidsReport` read from it directly.

### STEP 2 — `receipt_print_logs` (reprint log)

A new append-only table replaces the bare `reprint_count` integer as the source of truth:

```
receipt_print_logs (id, sale_id, user_id, printed_at, is_reprint, reason)
```

- `ReceiptController::show` writes one row on every render: `is_reprint = false` on first view, `is_reprint = true` with a mandatory reason on reprint.  
- `Sale::reprintCount()` counts `is_reprint = true` rows; `reprint_count` column is incremented in parallel for backward compatibility with existing queries.  
- Receipt view receives `$isReprint` boolean and shows `*** COPY / ቅጂ ***` header when true.

### STEP 3 — `payment_methods` and `expense_categories` masters

Two new lookup tables replace embedded string enums:

**`payment_methods`** (code, name, is_cash, is_active, requires_reference)  
Seeded with: `cash`, `mobile_money`, `telebirr`, `cbe_birr`, `card`, `bank_transfer`, `credit`.

**`expense_categories`** (code, name, is_active)  
Seeded with: `rent`, `salaries`, `utilities`, `transport`, `maintenance`, `supplies`, `other`.

Both tables are connected via foreign keys:
- `payments.payment_method_id` → `payment_methods.id`
- `sale_returns.payment_method_id` → `payment_methods.id`
- `expenses.expense_category_id` → `expense_categories.id`

Existing string columns (`method`, `payment_method`, `category`) are **kept** for backward compatibility and snapshot purposes; the FK columns are populated via a data migration backfill.

Owner and PharmacyManager can manage both masters through new Livewire settings screens (`Settings\PaymentMethods`, `Settings\ExpenseCategories`) with full audit logging. PharmacyStaff and SystemAdmin cannot access these screens.

---

## Consequences

### Positive

- **Compliance:** Every void, reprint, payment method and expense category is now a first-class, immutable or controlled record.
- **Auditability:** Void reason, approver identity, reprint user/time/reason are all queryable without touching the approval engine.
- **Reporting:** `SalesByPaymentMethodReport` resolves method labels from the master (`PaymentMethod.name`) rather than string formatting; `RevenueSummaryQuery` aggregates expenses by active `ExpenseCategory` records.
- **Extensibility:** New payment methods (e.g., QR code wallets) can be added in settings without code changes.

### Neutral / Trade-offs

- `payments.method` and `expenses.category` free-text columns are retained to avoid a breaking data migration; they are still populated by `CompleteSale` and `RecordExpense` for backward-compat queries.
- `reprint_count` is still incremented alongside the log table to avoid changing existing dashboard queries in one shot.

### Negative / Risks

- Two sources of reprint truth (`reprint_count` + log) must be kept in sync. A future cleanup task should drop `reprint_count` once all callers switch to `reprintCount()`.

---

## Migration path

| Migration file | Description |
|---|---|
| `2026_11_15_000006_create_sale_voids_and_receipt_print_logs_tables.php` | Creates `sale_voids` and `receipt_print_logs` with append-only triggers |
| `2026_11_15_000007_create_payment_methods_and_expense_categories_tables.php` | Creates and seeds masters; adds FK columns; backfills existing rows; re-applies expenses no-delete trigger |

---

## Alternatives considered

- **Storing void reason in `ApprovalRequest.payload` only:** Rejected — requires joining through the approval engine for every report and complicates immutability enforcement.
- **Dropping `payments.method` string immediately:** Rejected — too disruptive across existing reports; phased approach preferred.
- **Hardcoded PHP enums for payment methods:** Rejected — business requirements change (new mobile money operators); a managed table is more future-proof.
