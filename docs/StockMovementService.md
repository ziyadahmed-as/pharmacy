# StockMovementService Documentation

The `StockMovementService` is the **only authoritative writer** of inventory stock quantities and movements across the PMS.

## Architectural Rules (AGENTS.md Section 6 Rule 3 & BR-08)
1. **Direct edits to `batch_stocks` are prohibited**: All modifications to stock balances must go through `StockMovementService::post()`.
2. **Negative Stock Refused**: The service locks the batch row with `lockForUpdate()` and checks the available balance. If deduction exceeds the available stock, it aborts and throws `RuntimeException`.
3. **Immutable Ledger**: Every invocation creates an append-only row in `stock_movements`. The `StockMovement` model refuses `updating` and `deleting`.

## Usage Reference for Subsequent Use Cases

```php
use App\Services\StockMovementService;

StockMovementService::post(
    batch: $batch,                     // App\Models\Batch
    signedQuantity: 100,               // Positive for additions, negative for deductions
    type: 'receipt',                   // 'receipt' | 'sale' | 'adjustment' | 'quarantine' | 'return' | 'dispense'
    source: $receiptOrSaleModel,       // Originating Eloquent model
    user: $actorUser,                  // App\Models\User
    status: 'sellable',                // 'sellable' | 'quarantine'
    location: null,                    // Optional location string
    description: 'Received shipment'   // Audit memo
);
```

