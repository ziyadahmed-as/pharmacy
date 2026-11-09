# AGENTS.md: Pharmacy Management System (PMS) Agent Guide

Put this file in the repository root. Every coding agent (Claude Code or similar) must read it before working on any prompt. It turns the Business Requirements Document (BRD) v2.0 and the Use Case List in Implementation Order v1.1 into engineering rules.

---

## 1. Mission

Build one integrated system for ONE pharmacy (about 10 users, 10 concurrent, 20,000 products, 200 sales a day, modest hardware). The system must:

- never sell expired, recalled, quarantined or inactive medicine (BO-1);
- show stock by batch and expiry in real time (BO-2, BO-3);
- sell fast, under 60 seconds for a typical sale (BO-4);
- give accurate daily sales and cash figures without manual work (BO-5);
- keep the registers a regulator may ask for (BO-6);
- prevent theft and fraud with roles, approvals and an unalterable audit trail (BO-7).

Stack: **Laravel 12, PHP 8.3 (or the minimum Laravel 12 allows), Livewire 3, Tailwind (Flux UI if the starter kit provides it), MySQL 8 in production, SQLite in tests, Pest.** Verify package versions with Composer before relying on anything written in these documents.

## 2. Source documents and precedence

| Document | Use it for |
|---|---|
| BRD v2.0 | Requirements, business rules (BR), acceptance criteria (AC), decisions (D), open questions (OQ). **Wins on any rule.** |
| Use Cases in Implementation Order v1.1 | Use case IDs, build order, actors and step-by-step scenarios. **Wins on order and screen flow.** |
| This guide | How to build. Wins on engineering conventions. |

Rules:
1. Cite BRD v2.0 requirement IDs (FR, BR, AC) in code docblocks and tests. The "Related requirements" lines inside Use Case v1.1 use a different numbering in places (see section 12, C-2), so do not copy them.
2. If two sources conflict, do NOT silently choose. Apply the default in section 12, record it in `docs/decisions/` and mention it in your final message.
3. If something is not covered anywhere, pick the safest option, make it a setting, record an assumption, and continue. Ask only when blocked.

## 3. Language and naming

- Roles in code (enum `App\Enums\Role`): `system_admin` (SA), `owner` (OW), `pharmacy_manager` (PM), `pharmacy_staff`.
- The use case file calls the shared pharmacist and technician role **PS**; the BRD calls it **PP**. They are the same role: `pharmacy_staff`. Pharmacists and technicians have identical permissions. Licence-dependent actions check the staff licence record, not the role.
- Use case IDs follow Use Case v1.1 (for example UC-34 is "Process approval request" and UC-35 is "Manage user accounts and roles"). Earlier prompt packs used a different numbering for UC-34, 35, 37 and 38; the v1.1 numbering replaces it.
- Audit action keys: `<module>.<entity>.<verb>` in lower case, for example `auth.login.failed`, `sale.completed`, `sale.fefo_override`, `price.changed`, `stock.adjustment.approved`.
- UI text: always `__('key')` with keys in `lang/en` and `lang/am`. No hard-coded strings in Blade or PHP.

## 4. Architecture conventions

One use case = one **Action class** + its **Livewire component(s)** + **Pest tests**.

```
app/
  Actions/<Module>/<UseCaseName>.php        business logic, authorises, transactional, audited
  Livewire/<Module>/...                     thin UI; calls Actions; no business rules
  Livewire/Forms/...                        Livewire 3 form objects with validation
  Models/                                   Eloquent; transactional models never hard-delete
  Enums/                                    Role, statuses, movement types, reasons
  Policies/                                 one per model
  Services/                                 shared building blocks (section 7)
  Console/Commands/                         scheduled jobs
database/migrations/                        never edit a merged migration; add a new one
lang/en, lang/am
tests/Feature/<Module>/, tests/Arch/
docs/decisions/                             one short markdown file per decision or assumption
```

Rules:
- Every Action starts with a docblock: `@uc UC-13 @fr FR-3.1,FR-3.2 @br BR-01 @ac AC-01`.
- Authorise in the Action (Gate or Policy) and again in the Livewire component. Never rely on hidden buttons.
- Livewire 3: use `#[Locked]` on ids and any value the user must not change, Form Objects for validation, `wire:key` in loops, `wire:loading` states. Never expose a model with sensitive attributes as a public property.
- Money: store as integer minor units (santim) or `decimal(12,2)` through one `Money` value object. No floats.
- Quantities: store in the product's smallest unit (for example tablets) as integers; convert for display through the unit conversion table.
- Time: store UTC, display `Africa/Addis_Ababa`.
- Use database transactions and `lockForUpdate()` for every posting that changes stock, cash or sequences.
- Queue only what can be slow (imports, backups, exports). The POS path must stay synchronous and fast.

## 5. Roles and permissions

Use `spatie/laravel-permission` (verify Laravel 12 support). Permission names are dotted: `catalogue.manage`, `price.set`, `stock.receive`, `stock.receipt.confirm`, `stock.adjust.request`, `sales.sell`, `sales.void.request`, `approvals.process`, `audit.view`, `users.manage`, `settings.edit`, `backup.run`, `reports.financial`, and so on. Seed permissions from the BRD permission matrix (section 4.3). A role gets only what the matrix allows.

Hard rules (enforce in code and test):
- **SA** is technical only. SA can never open patient, prescription, sales, cost or financial data. SA can never hold another role (see C-4).
- **PS** never approves, never sees cost or profit, never changes the selling price.
- One person may hold several roles; permissions add up, but nobody approves their own request.
- Every audit entry stores the **role used**. If a user holds several roles, they pick an acting role for the session.

## 6. Non-negotiable rules (BRD section 2.4 and section 7)

| # | Rule | How to implement |
|---|---|---|
| 1 | Never sell expired, recalled, quarantined or inactive stock (BR-01, BR-10) | One `SellabilityGuard` used by every sale, dispense and hold-resume. Check when a line is added and again at completion. Log blocked attempts. |
| 2 | FEFO by default (BR-02) | `FefoBatchResolver` picks earliest-expiry eligible batches, splits across batches. Override needs a reason and writes `sale.fefo_override`. |
| 3 | No negative stock; every change is a ledger movement with a source document (BR-04, BR-08) | Only `StockMovementService::post()` may change stock. Check balance under row lock. DB check constraint where possible. |
| 4 | Completed documents are immutable (BR-05, BR-16) | No update or delete route for completed sales, receipts, movements. Correct by void, refund, return, cancel-and-reissue or approved adjustment. No hard delete of transactional data. |
| 5 | Approver is never the initiator; sensitive approvals re-authenticate (BR-14) | `ApprovalService` enforces it. Never re-implement it in a component. |
| 6 | Audit log is append-only (BR-15) | `AuditLog` model throws on update and delete; DB trigger blocks UPDATE and DELETE. |
| 7 | Postings are atomic and idempotent | Transaction + unique `idempotency_key` on sales, payments, receipts. Disable the submit button after the first click as well. |
| 8 | Nothing legal or policy-based is hard-coded | Limits, thresholds, validity days, alert days, payment methods come from settings. A missing required setting means "approval required" or "feature blocked", never a guess. |
| 9 | Rx status comes only from product classification (FR-1.2) | A `classification` enum on the product drives every rule. |
| 10 | Cost and health data only for authorised roles (BR-06, FR-5.6) | Policies plus encrypted casts for health fields and ID numbers; log every view of health fields. |
| 11 | Dispensing needs a valid licence (BR-12, FR-10.3) | `LicenceGate::allows(User $user, string $action)` reads the staff licence and the licence-category setting. |
| 12 | Selling price is set only by OW or PM; no discounts, no price override, no tax (BR-20) | The till has no such fields. A test (AC-36) fails if one appears. |
| 13 | Received stock sellable only after PM confirmation, default on (BR-19) | Setting `inventory.receipt_requires_confirmation`, default true. |
| 14 | Controlled drugs: register entry for every receipt and sale; independent named witness on disposal (FR-4.4, BR-11) | `ControlledDrugRegister` service called inside the same transaction as the stock movement. |

## 7. Shared building blocks (build once, reuse)

| Service | Built in | Contract |
|---|---|---|
| `AuditLogger::record(action, subject?, old[], new[])` | Foundation | Fills user, role used, terminal. Masks `password`, `token`, `secret`, `id_number`, health fields. |
| `ConfirmWithPassword` Livewire modal | Foundation | Re-authentication with throttling. Used for every approval. |
| `SettingsRepository` and `setting('group.key')` | UC-38 | Typed, cached, changes audited. Until UC-38, read `config/pms.php` defaults. |
| `ApprovalService` + `ApprovalHandler` registry | UC-34 | `request()`, `approve()`, `reject()`, `cancel()`. Routing by role. Atomic and idempotent execution. |
| `DocumentSequence::next(type)` | UC-04 | Gapless sequential numbers (receipts, receipt numbers, disposals) using a locked row. |
| `StockMovementService::post()` | UC-04 | The only way to change stock. Writes `stock_movements`, updates `batch_stocks`, rejects negative. |
| `SellabilityGuard`, `FefoBatchResolver` | UC-13 (stub earlier in UC-04, UC-08) | See rules 1 and 2. |
| `PriceResolver::at(Product, time)` | UC-02 | Price effective at a time; sale line stores the price used. |
| `LicenceGate` | UC-39 (method), UC-20 (use) | See rule 11. |
| `Notifier` | UC-39 | Database notifications to roles. Used by alerts and approvals. |

## 8. Data model guidance (refine, but keep the invariants)

- `users`, `staff`, `staff_licences`, `roles/permissions`, `role_assignment_history`, `password_histories`
- `audit_logs` (append-only), `approval_requests`, `settings`, `document_sequences`
- `products`, `product_units`, `product_prices` (price history with effective time and reason), `categories`
- `batches` (product, batch no, manufacturing date, expiry, unit cost nullable, supplier name text), `batch_stocks` (batch, location nullable, status `sellable|quarantine`, quantity), `stock_movements` (append-only ledger: type, signed base-unit quantity, balance after, source type and id, user), `stock_receipts` and lines
- `quarantine_records`, `adjustment_requests`, `stocktakes` and lines, `disposals`, `recalls` and `recall_batches`
- `tills`, `shifts`, `sales`, `sale_lines`, `payments`, `held_sales`, `sale_returns`, `daily_closings`
- `customers`, `prescriptions`, `prescription_lines`, `dispensing_events`, `controlled_drug_register`
- `expenses`, `rosters`, later `attendance`, `leaves`, `payroll_entries`, `credit_accounts`, `reminders`

Supplier is a text field on a receipt (no supplier table). There is no purchase order, no discount and no tax table.

## 9. UI conventions

- Layout: sidebar by module, items shown only when the user has the permission.
- POS: keyboard first (search focus, quantity entry, shortcuts), barcode scanner works as keyboard input, large touch targets, totals always visible, errors in plain language in the user's language.
- Every list: search, filters, pagination, empty state. Every form: inline validation, loading state, confirmation for destructive steps.
- Printing: receipts for 80 mm thermal printers (print stylesheet or PDF), reprints marked COPY. Amharic must print correctly (Noto Sans Ethiopic).
- Accessibility: labels, focus order, contrast, no colour-only meaning.

## 10. Testing standard

- Pest feature tests per use case: one test for the main scenario, **one test per alternative (A) and exception (E) flow** from Use Case v1.1, plus the acceptance criteria listed in the prompt.
- Livewire component tests for permission-gated actions, and direct-URL denial tests for every restricted screen.
- Architecture tests in `tests/Arch` (Pest arch): no direct update or delete on `AuditLog` or `StockMovement`; no model for purchase orders or discounts exists; only `StockMovementService` writes `batch_stocks`; no hard delete on transactional models.
- Factories and seeders for every model. Tests must not depend on seed data except roles.
- Time-dependent rules use `travelTo()`.
- Run before every commit: `composer test`, `composer lint` (Pint), `composer analyse` (Larastan level 6 or higher).

Acceptance criteria to use case map (BRD section 14):

| AC | Proven by use case(s) |
|---|---|
| AC-01, 02 | UC-13 |
| AC-03, 12 | UC-21 |
| AC-04, 20, 22, 36 | UC-13 |
| AC-05, 06 | UC-04 |
| AC-07 | UC-08 |
| AC-08 | UC-06 |
| AC-09 | UC-07 |
| AC-10, 25 | UC-09 |
| AC-11 | UC-10 |
| AC-13 | UC-20, UC-22, UC-39 |
| AC-14 | UC-15 |
| AC-15 | UC-16 |
| AC-16, 17 | UC-02, UC-11 |
| AC-18 | UC-33, UC-36 |
| AC-19 | UC-31 |
| AC-21 | UC-37 |
| AC-23 | UC-14 |
| AC-24 | UC-12, UC-17, UC-18 |
| AC-26 | UC-25, UC-23 |
| AC-27 | UC-03 |
| AC-28 | UC-34, UC-35 |
| AC-29 | UC-30 |
| AC-30 | UC-29 (only if C-1 keeps cost) |
| AC-31 | UC-38, UC-13 |
| AC-32 | UC-33, UC-35, UC-39 |
| AC-33 | UC-05 |
| AC-34 | UC-22 and a role test in UC-35 |
| AC-35 | UC-31, UC-37 |

## 11. Definition of done (every use case)

1. Main scenario works in the browser in English and Amharic.
2. Every A and E flow in Use Case v1.1 has a passing test.
3. Authorisation tested for each role, including direct URL.
4. Every change is audited with action key, role used, old and new values.
5. Pint and Larastan pass; the full test suite is green (earlier use cases still pass).
6. No hard-coded policy values; new settings are added with defaults and documented.
7. Docblocks carry `@uc @fr @br @ac`.
8. `docs/decisions/` contains any assumption or conflict default you used.
9. Final message lists: files changed, tests added, assumptions, open questions.

## 12. Known conflicts between the two documents and the default to use

| ID | Conflict | Default to use | Who confirms |
|---|---|---|---|
| C-1 | Cost recording. BRD keeps unit cost at receipt, profit and loss (FR-6.3), valuation (FR-6.7), decision D-07, AC-05 and AC-30. Use Case v1.1 says cost of goods is not recorded and cites **OQ-11, which does not exist in BRD v2.0** (the BRD has OQ-01 to OQ-10). UC-29 delivers only cash and revenue summaries, so FR-6.3, 6.7 and 7.3 have no use case in v1.1. | Follow the BRD. `batches.unit_cost` is nullable, entered at receipt, visible only to OW and PM, isolated behind one `CostVisibility` policy. Build UC-29 as written; build the profit and loss and valuation extension only when the BA confirms cost is kept. | Business Analyst, Owner |
| C-2 | Requirement numbering. "Related requirements" in Use Case v1.1 do not match BRD v2.0 (for example it cites FR-3.15, FR-3.13 for daily closing, FR-2.20 for second named person, FR-6.7 for void and refund, BR-6 and BR-7 for price rules). | Cite BRD v2.0 IDs only. The BA should re-trace the use case file. | Business Analyst |
| C-3 | Receipt confirmation. BRD (FR-2.2, BR-19, matrix, section 4.4) needs PM confirmation before stock is sellable. UC-04 lets staff post directly. | Follow the BRD with setting `inventory.receipt_requires_confirmation` (default true). | Owner |
| C-4 | Role combination. BRD FR-8.8: SA can never hold another role. UC-35 E1 allows an Owner-recorded exception. | Hard block for SA. Other combinations allowed. | Owner |
| C-5 | Recall trace report. BRD FR-2.12 is Should (S). UC-10 includes it in Stage 5. | Build recall registration and blocking now (Must). Build the trace report as the last step of UC-10 behind a feature flag. | Business Analyst |
| C-6 | Extra rules only in Use Case v1.1: second named person for controlled-drug stock moves and counts (UC-22 E2, UC-07 A3), effective date and reason on price changes (UC-02), merging into an existing batch (UC-04 A2). | Implement them; they tighten control and do not contradict the BRD. Ask the BA to add them to the BRD. | Business Analyst |
| C-7 | Quarantine approval. BRD matrix shows quarantine and release as approved. UC-08 quarantines at once and approves only the release. | Follow UC-08 and BRD section 4.4: quarantine is immediate; release, disposal and supplier return are approved. | Business Analyst |
| C-8 | Return to supplier (FR-2.16, AC-25) has no use case in v1.1. | Build it inside UC-09 as the disposal method "return to supplier", with the supplier name and batch reduction. | Business Analyst |
| C-9 | PS and PP are the same role; the earlier Wave 1 prompt pack used the v1.0 numbering. | Use `pharmacy_staff` and the v1.1 numbering. | None |

Open questions that affect design (BRD section 16): OQ-01 controlled drugs, OQ-02 prescription rules, OQ-03 retention, OQ-04 recall and disposal duties, OQ-05 payment methods, OQ-06 value limits, OQ-07 patient data, OQ-08 offline POS, OQ-09 technician scope, OQ-10 tax and receipt. Each one is a setting or a feature switch with the BRD default. Never hard-code an answer.

## 13. Out of scope. Do not build

Purchasing (requisitions, purchase orders, supplier master and ordering, supplier invoices, supplier payments, payables). Pricing rules, margin rules, promotions, discounts of any kind, price override at the POS, VAT or any tax, tax summaries, fiscal or e-invoice links. Multi-branch and inter-branch transfer, online ordering and delivery, insurance claims, general ledger and tax filing, payroll tax, e-prescription exchange. If a task seems to need one of these, stop and raise it as a change request.

## 14. Working agreement for agents

1. Read this file and the prompt. State a short plan (5 to 10 lines) before coding.
2. Work in small commits on a branch named `uc-<id>-<slug>`. Commit message: `UC-13: sell OTC items (FR-3.1..3.5)`.
3. Never edit a merged migration; add a new one. Never run destructive commands against a shared database. Never commit secrets; read them from `.env`.
4. Do not build later use cases early. Build small hooks and interfaces where a later use case plugs in, and say so in the final message.
5. If blocked, make the safest assumption, record it, continue, and list it at the end.
6. Keep prompts' build order. If a prompt references a service that does not exist yet, create the minimum interface and a clearly marked temporary implementation.
7. Final message format: Summary, Files, Tests (with AC mapping), Assumptions and conflict defaults used, Open questions, Next use case.

## 15. Use case traceability (Use Case v1.1 to BRD v2.0)

| Seq | UC | Name | Stage | BRD requirements | BR | AC |
|---|---|---|---|---|---|---|
| 1 | UC-33 | Log in and log out | 1 | FR-8.1, 8.4, 8.6, 8.9 | | 18, 32 |
| 2 | UC-39 | Manage staff record and licence | 1 | FR-10.1 to 10.3, 9.1, 9.3 | 12, 13 | 13, 32 |
| 3 | UC-35 | Manage user accounts and roles | 1 | FR-8.1, 8.2, 8.5, 8.8, 8.9, 10.2 | 14 | 28, 32 |
| 4 | UC-36 | Reset password and unlock account | 1 | FR-8.6, 8.10 | | 18 |
| 5 | UC-34 | Process approval request | 1 | FR-8.7, 8.2, 8.8 | 14 | 28 |
| 6 | UC-31 | Review audit log | 1 | FR-7.5, 8.3, 8.9, 8.10 | 15 | 19, 35 |
| 7 | UC-38 | Configure settings and pharmacy profile | 1 | FR-9.4, 9.5, 9.7 | | 31 |
| 8 | UC-01 | Manage product catalogue | 2 | FR-1.1 to 1.4 | | |
| 9 | UC-02 | Maintain selling price | 2 | FR-1.5 | 20 | 16, 17 |
| 10 | UC-03 | Import products from file | 2 | FR-1.8 | | 27 |
| 11 | UC-04 | Receive stock | 2 | FR-2.1 to 2.3, 2.5 | 08, 19 | 05, 06, 20 |
| 12 | UC-11 | View stock and ledger | 2 | FR-2.10, 7.2 | 06 | 16 |
| 13 | UC-05 | Monitor stock alerts and reorder list | 2 | FR-2.6, 2.7, 9.1 | | 33 |
| 14 | UC-08 | Quarantine stock | 2 | FR-2.15, 2.5 | 01, 10 | 07 |
| 15 | UC-12 | Open shift | 3 | FR-3.9, 10.4 | | 24 |
| 16 | UC-13 | Sell OTC and non-drug items | 3 | FR-3.1 to 3.5, 3.11, 2.4 | 01 to 05, 20 | 01, 02, 04, 20, 22, 36 |
| 17 | UC-14 | Hold and resume a sale | 3 | FR-3.6 | | 23 |
| 18 | UC-17 | Close shift | 3 | FR-3.9 | | 24 |
| 19 | UC-15 | Void a sale | 3 | FR-3.7 | 05, 14 | 14 |
| 20 | UC-16 | Process customer return and refund | 3 | FR-3.8 | 18 | 15 |
| 21 | UC-18 | Close and lock the day | 3 | FR-3.10, 6.1, 6.6 | | 24 |
| 22 | UC-25 | Manage customer record | 4 | FR-5.1, 5.2, 5.6 | | 26 |
| 23 | UC-19 | Register prescription | 4 | FR-4.2, 4.8, 4.11 | | |
| 24 | UC-20 | Verify prescription | 4 | FR-4.3, 4.8 | 03, 12 | 13 |
| 25 | UC-21 | Dispense prescription | 4 | FR-4.1, 4.8 to 4.10, 3.4, 3.5, 2.4 | 03, 12, 17 | 03, 12 |
| 26 | UC-22 | Dispense controlled drug | 4 | FR-4.4, 4.3, 4.9 | 03, 12 | 13, 34 |
| 27 | UC-23 | Search prescription and patient history | 4 | FR-4.12, 5.6, 7.4 | | 26 |
| 28 | UC-06 | Request stock adjustment | 5 | FR-2.8 | 07, 14 | 08 |
| 29 | UC-07 | Perform stocktake | 5 | FR-2.9, 2.10 | | 09 |
| 30 | UC-09 | Dispose of stock (and return to supplier) | 5 | FR-2.16 to 2.19 | 07, 11 | 10, 25 |
| 31 | UC-10 | Manage recall | 5 | FR-2.11, 2.12 | 01 | 11 |
| 32 | UC-37 | Back up and restore data | 6 | FR-9.6, 8.10 | | 21, 35 |
| 33 | UC-28 | Record operating expense | 6 | FR-6.2 | | |
| 34 | UC-29 | View cash and revenue summaries | 6 | FR-6.1, 6.6, 7.1 | | 24 |
| 35 | UC-30 | Generate and export a report | 6 | FR-7.1 to 7.6 | | 29 |
| 36 | UC-40 | Manage roster and shifts | 6 | FR-10.4 | | |
| 37 | UC-24 | Dispense in parts and refill | 7 | FR-4.5 | | |
| 38 | UC-26 | Sell on credit and collect payment | 7 | FR-5.3, 6.4 | | |
| 39 | UC-27 | Send refill reminders | 7 | FR-5.5 | | |
| 40 | UC-32 | View owner dashboard | 7 | FR-7.7 | | |
| 41 | UC-41 | Manage attendance and leave | 7 | FR-10.5, 10.6 | | |
| 42 | UC-42 | Process basic payroll | 7 | FR-10.7 | 13 | |
