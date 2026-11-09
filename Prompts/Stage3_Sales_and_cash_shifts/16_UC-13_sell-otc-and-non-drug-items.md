# UC-13  Sell OTC and non-drug items

| Field | Value |
|---|---|
| Stage | 3. Sales and cash shifts |
| Build order | 16 |
| Priority | Must (Phase 1) |
| Depends on | UC-02, UC-04, UC-12 |
| Suggested branch | `uc-13-sell-otc-and-non-drug-items` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-13 Sell OTC and non-drug items. Depends on: UC-02, UC-04, UC-12. BRD: FR-3.1 to 3.5, 3.11, 2.4; BR-01 to BR-05, BR-20. AC-01, AC-02, AC-04, AC-20, AC-22, AC-31, AC-36.

GOAL: a fast, safe, one-time sale that reduces stock by batch and prints a receipt. This is the core of the system: keep the POS path synchronous and quick.

STEP 1 Data: `sales` (receipt_no unique, shift_id, user_id, status completed|voided, total, idempotency_key unique, completed_at), `sale_lines` (product, unit, batch_id, quantity in base units, unit_price from `PriceResolver` stored on the line), `payments` (sale, method, amount, reference). Receipt numbers from `DocumentSequence` (unique, sequential, gapless).
STEP 2 `SellabilityGuard` (refuses expired, recalled, quarantined, inactive; audit `sale.blocked`) and `FefoBatchResolver` (earliest-expiry eligible batches, splits across batches). Use them when a line is added AND again at completion.
STEP 3 Action `CompleteSale` in one transaction: re-validate every line, check that payments equal the total (E3), post stock through `StockMovementService` (type sale), write sale, lines, payments, ledger and audit. Refuse quantity above stock (E2). Idempotency key and disabled submit (E5).
STEP 4 Livewire `Pos\Sale`: keyboard-first screen; scan or search; shows available stock, FEFO batch, expiry and price; pack or loose unit where `loose_allowed`; running total; payment panel with only active methods from settings, split payment (A1), reference for non-cash; A2 batch override with a reason (audit `sale.fefo_override`).
STEP 5 Receipt: 80 mm thermal print view with unique number, pharmacy name, date-time, items, quantities, unit prices, total, payments, served-by, footer from settings; reprints marked COPY (E4). It is an operational receipt, not a tax invoice. English and Amharic.
STEP 6 E6: there is no discount, price-override or tax field, API or setting. Add an arch/feature test for it (AC-36).
STEP 7 A3: when the cart contains a prescription or controlled product, show a message and hand over to UC-21 (stub link until built). A4: hold button calls UC-14 (stub).

TESTS: expired, recalled, quarantined, inactive blocked with message and audit (AC-01); FEFO picks the earlier batch and an override needs a reason (AC-02); a 5-item cash sale completes in under 60 seconds of scripted steps and reduces stock (AC-04); split payment and mismatch refused (AC-22); double click creates one sale (AC-20); printer failure reprint is COPY; no discount or price override exists (AC-36); receipt prints Amharic correctly (AC-31); pharmacy_staff cannot see cost.

DONE WHEN: all green; NFR-1 targets measured with 20,000 products and recorded.
````
