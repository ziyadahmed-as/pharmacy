# 0028. Integer Base-Unit Rule for Inventory Quantities

**Date:** 2026-11-15  
**Status:** Accepted  
**Context:** BRD v2.0 Section 4, AGENTS.md Section 4 & 8, UC-01, UC-13  

## Context

A architectural question was raised regarding whether pharmaceutical products with liquid or powdered loose measures (e.g. millilitres `ml`, grams `g`) should introduce fractional decimal columns (`decimal(12,3)`) across all stock and transactional ledgers (`batch_stocks.quantity`, `stock_movements.quantity`, `stock_movements.balance_after`, `sale_lines.quantity`, `prescription_lines.quantity`, `stock_receipt_lines.quantity`, `stocktake_lines.quantity`, `quarantine_records.quantity`).

## Decision

The business and system architecture retain the **strict integer-base-unit rule**:

1. **Smallest Base Unit as Integer**: All inventory quantities and transactional movements are stored in integer units corresponding to the item's smallest dispensation unit:
   - Liquids/Syrups: Stored in integer millilitres (`ml`) (e.g. a 100 ml bottle represents 100 units of 1 ml, or a single unit bottle).
   - Injectables: Stored in integer ampoules, vials, or International Units (`IU`).
   - Powders/Bulk: Stored in integer grams (`g`) or milligrams (`mg`).
   - Solid forms: Stored in integer tablets, capsules, or suppositories.

2. **Packaging Unit Conversion via `ProductUnit`**: Packaging multiples (packs, boxes, bottles, strips, blister packs) convert dynamically to base integer units via `ProductUnit.conversion_factor` during receipt and dispensing.

3. **Loose Dispensation**: The existing `loose_allowed` flag on `products` governs whether packaging units can be opened and dispensed in fractional pack amounts (which are whole base unit quantities, e.g. 10 tablets from a box of 100).

## Consequences

### Positive
- **Exact Accounting**: Eliminates IEEE-754 binary floating-point rounding errors and multi-column decimal rounding discrepancies across the immutable `stock_movements` ledger.
- **Performance & Simplicity**: Integer operations on `batch_stocks` and SQLite/MySQL indexes remain fast and lightweight.
- **Physical Auditability**: Physical counts during stocktaking match discrete base unit counts without fractional remainder ambiguities.

### Negative / Trade-offs
- Compound formulations requiring sub-millilitre or microgram precision (e.g. 0.25 ml pediatric drops from a bulk bottle) must be defined with a smaller base unit (such as `0.1 ml` or drops) or packaged as individual units if extemporaneous compounding is introduced in a future stage.
