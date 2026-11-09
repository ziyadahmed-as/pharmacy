# UC-02  Maintain selling price

| Field | Value |
|---|---|
| Stage | 2. Catalogue and stock base |
| Build order | 9 |
| Priority | Must (Phase 1) |
| Depends on | UC-01 |
| Suggested branch | `uc-02-maintain-selling-price` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-02 Maintain selling price. Depends on: UC-01. BRD: FR-1.5; BR-20. AC-16, AC-17. Conflict C-6 (effective date and reason are extra in the use case; implement them).

GOAL: a plain, audited selling price; no discounts, margin rules or tax.

STEP 1 Data: `product_prices` (product_id, unit_id, price in minor units, effective_from, reason, set_by, created_at). Append-only history; never update a row.
STEP 2 `PriceResolver::at(Product, Unit, CarbonInterface)` returning the latest price effective at that time; sales will store the price used on every sale line.
STEP 3 Action `SetSellingPrice`: only owner and pharmacy_manager; price must be a positive amount (E2); effective date/time and reason required; audit `price.changed` with old and new values.
STEP 4 Bulk prices arrive through UC-03 (A1): expose `BulkSetSellingPrices` used by the importer later.
STEP 5 Livewire: price panel on the product page (current price, history, "Change price" form). Pharmacy_staff see the price read-only (E1).
STEP 6 Guard: no discount, price-override or tax column or screen anywhere.

TESTS: PM and Owner set a price; zero or negative refused; staff change denied incl. direct call (AC-16); history preserved and resolver returns the right price before and after the effective time (AC-17); audit entry has old and new; arch test: no discount/tax model.

DONE WHEN: all green.
````
