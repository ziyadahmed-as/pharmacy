# 0024. Storage Locations

## Context
Previously, `batch_stocks.location` was free-form text. To meet pharmacy regulatory and tracking requirements:
1. Locations must be modeled as structured, typed entities: `storage_locations` (`id`, `code` unique, `name`, `type`, `is_active`).
2. Supported types are: `shelf`, `fridge`, `controlled-cabinet`, `quarantine`, `dispensary`.
3. Storage locations must never be hard-deleted, only deactivated.
4. Existing text values in `batch_stocks.location` must be migrated to structured rows, replacing the text column with a foreign key `location_id`.
5. Controlled drug products in sellable status must strictly be stored in a `controlled-cabinet` location.
6. Stock movements, receipts, stocktakes, quarantine, adjustments, and reports must reference `location_id`.
7. Stock movement between locations must create two balanced ledger entries (`out` and `in`) with type `transfer`, fully audited.

## Decision
- **Schema & Migration**: Created `storage_locations` with a CHECK constraint on `type`. Added `location_id` foreign key referencing `storage_locations` with `restrictOnDelete` to `batch_stocks`, `stock_movements`, and `stock_receipt_lines`. Dropped the legacy text column `location` and migrated historical text values into normalized rows. Seeded 5 standard default locations: `DISP-MAIN` (dispensary), `SHELF-GEN` (shelf), `FRIDGE-MAIN` (fridge), `SAFE-MAIN` (controlled-cabinet), and `QUAR-MAIN` (quarantine).
- **Model Rules**: `StorageLocation` model enforces immutability on deletion via model boot hooks (`deleting` event throws `\RuntimeException`).
- **Controlled Substance Enforcement**: Enforced both in `StockMovementService::post()` and `MoveStock::execute()`; any sellable movement of a controlled product must target a `controlled-cabinet` type location or throws a validation/runtime error.
- **Stock Movement Service**: `StockMovementService` is the sole writer of `batch_stocks` and `stock_movements`. The signature was updated to accept `int|string|null $locationId`. Location is resolved by integer ID, string code/name, existing batch stock location, or appropriate default.
- **Move Stock Action**: Created `App\Actions\Inventory\MoveStock` which verifies positive quantity, distinct source/destination locations, active status of both locations, available stock at source, and controlled product cabinet requirement. It writes two balanced movements (signed negative for out, signed positive for in) with type `transfer` inside a database transaction, and logs a `stock.location.moved` audit entry.
- **Livewire UI**: Created `App\Livewire\Settings\Locations` for managing locations (list, add, edit, toggle active status, never delete). Added location filtering and indicators across Current Stock (`Stock\Index`), Stock Ledger (`Stock\Ledger`), Stock Receipt (`Inventory\Receive`), Stocktake (`Inventory\Stocktakes\Index`), and Inventory Current Stock Report (`InventoryCurrentStockReport`).
- **Translations & Tests**: Provided comprehensive bilingual translations (English and Amharic) across all location screens and errors. Verified through `tests/Feature/Inventory/StorageLocationsTest.php` and the full suite of 538 feature tests.

## Status
Accepted and verified through automated tests (`tests/Feature/Inventory/StorageLocationsTest.php`).
