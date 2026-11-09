# UC-01  Manage product catalogue

| Field | Value |
|---|---|
| Stage | 2. Catalogue and stock base |
| Build order | 8 |
| Priority | Must (Phase 1) |
| Depends on | UC-33 |
| Suggested branch | `uc-01-manage-product-catalogue` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-01 Manage product catalogue. Depends on: UC-33. BRD: FR-1.1 to 1.4.

GOAL: every sellable item exists once, classified, with units and stock levels.

STEP 1 Data: `categories`; `products` (brand, generic name INN, strength, dosage form, pack size, unit, manufacturer, category, barcode unique nullable, storage condition, classification enum rx|otc|controlled|non_drug, loose_allowed default false, reorder_level, max_level, min_level, preferred_supplier_name text, is_active); `product_units` (name, factor to base unit). Do not add price here (UC-02).
STEP 2 Actions `SaveProduct`, `DeactivateProduct`, `ReactivateProduct`. Duplicate check on barcode and on brand+strength+form+pack (A1: show the existing product and let the user cancel or edit it). Deactivation blocks future sale and receipt and keeps history (A2).
STEP 3 Permissions: PM creates and edits everything; pharmacy_staff may create and edit basic data but cannot change levels or deactivate (E2); owner as PM; SA none.
STEP 4 Classification changes are audited. Show a note that defaults come from verified lists and the pharmacist in charge confirms.
STEP 5 Livewire: list with fast search (name, generic, barcode), filters, create/edit form object, units editor, deactivate action. Index for search performance with 20,000 rows.

TESTS: create with all fields; duplicate barcode shows existing product (A1); missing mandatory field blocked (E1); staff cannot change levels or deactivate (E2); deactivated product is hidden from sale and receipt lookups; audit entries; search under 1 s with 20,000 seeded rows.

DONE WHEN: all green.
````
