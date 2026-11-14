<?php

use App\Actions\Inventory\ApproveStocktake;
use App\Actions\Inventory\CreateStocktake;
use App\Actions\Inventory\RecordStocktakeCount;
use App\Enums\Role as RoleEnum;
use App\Livewire\Inventory\Stocktakes\Index;
use App\Livewire\Inventory\Stocktakes\Show;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ControlledDrugRegister;
use App\Models\Product;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Models\StockMovement;
use App\Services\SettingsRepository;
use App\Services\StockMovementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('pharmacy.handles_controlled_drugs', true);
    app(SettingsRepository::class)->put('inventory.recount_threshold', 500); // 500 ETB
});

function makeStocktakeProduct(array $attr = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'classification' => 'rx',
        'is_active' => true,
        'reorder_level' => 10,
    ], $attr));
}

function makeStocktakeBatch(Product $product, array $attr = []): Batch
{
    return Batch::create(array_merge([
        'product_id' => $product->id,
        'batch_no' => 'STK-'.strtoupper(bin2hex(random_bytes(3))),
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 500, // 5.00 ETB
        'supplier_name' => 'EPSS',
    ], $attr));
}

// ── STEP 2: Count sheet generation ───────────────────────────────────────────

test('full stocktake creates a count sheet with every sellable batch', function () {
    $pm = pm();
    $product1 = makeStocktakeProduct();
    $batch1 = makeStocktakeBatch($product1);
    $product2 = makeStocktakeProduct(['brand' => 'Ibuprofen 400mg', 'generic_name' => 'Ibuprofen']);
    $batch2 = makeStocktakeBatch($product2);

    StockMovementService::post($batch1, 30, 'receipt', $product1, $pm, 'sellable');
    StockMovementService::post($batch2, 50, 'receipt', $product2, $pm, 'sellable');

    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, ['type' => 'full']);

    expect($stocktake->type)->toBe('full')
        ->and($stocktake->status)->toBe(Stocktake::STATUS_COUNTING)
        ->and($stocktake->lines)->toHaveCount(2);

    $line1 = $stocktake->lines->where('batch_id', $batch1->id)->first();
    $line2 = $stocktake->lines->where('batch_id', $batch2->id)->first();

    expect($line1->system_qty)->toBe(30)
        ->and($line2->system_qty)->toBe(50)
        ->and($line1->counted_qty)->toBeNull()
        ->and($line2->counted_qty)->toBeNull();
});

test('partial stocktake scoped by category creates lines only for that category', function () {
    $pm = pm();

    $catProduct = makeStocktakeProduct(['category_id' => 7]);
    $catBatch = makeStocktakeBatch($catProduct);
    StockMovementService::post($catBatch, 20, 'receipt', $catProduct, $pm, 'sellable');

    $otherProduct = makeStocktakeProduct(['brand' => 'Other Med', 'generic_name' => 'Other', 'category_id' => 99]);
    $otherBatch = makeStocktakeBatch($otherProduct);
    StockMovementService::post($otherBatch, 40, 'receipt', $otherProduct, $pm, 'sellable');

    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, [
        'type' => 'partial',
        'scope_type' => 'category',
        'scope_value' => '7',
    ]);

    expect($stocktake->type)->toBe('partial')
        ->and($stocktake->lines)->toHaveCount(1)
        ->and($stocktake->lines->first()->batch_id)->toBe($catBatch->id);
});

test('a second active stocktake covering the same items is refused', function () {
    $pm = pm();
    $product = makeStocktakeProduct();
    $batch = makeStocktakeBatch($product);
    StockMovementService::post($batch, 20, 'receipt', $product, $pm, 'sellable');

    actAs($pm);
    app(CreateStocktake::class)->execute($pm, ['type' => 'full']);

    // A second full stocktake covers the same batch → conflict
    expect(fn () => app(CreateStocktake::class)->execute($pm, ['type' => 'full']))
        ->toThrow(ValidationException::class);
});

// ── STEP 3: Staff enter counts ───────────────────────────────────────────────

test('staff enter counts and variances are computed correctly', function () {
    $pm = pm();
    $staff = ps();
    $product = makeStocktakeProduct();
    $batch = makeStocktakeBatch($product);
    StockMovementService::post($batch, 30, 'receipt', $product, $pm, 'sellable');

    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, ['type' => 'full']);

    $line = $stocktake->lines->first();

    // Staff enters count
    actAs($staff);
    $action = app(RecordStocktakeCount::class);
    $updated = $action->execute($staff, [
        'line_id' => $line->id,
        'counted_qty' => 25,
    ]);

    expect($updated->counted_qty)->toBe(25)
        ->and($updated->variance)->toBe(-5) // 25 - 30 = -5
        ->and($updated->counted_by)->toBe($staff->id);
});

test('controlled drug count requires a second named person (A3)', function () {
    $pm = pm();
    $staff = ps();
    $witness = ps();

    $product = makeStocktakeProduct(['classification' => 'controlled']);
    $batch = makeStocktakeBatch($product);
    StockMovementService::post($batch, 10, 'receipt', $product, $pm, 'sellable', secondPerson: $witness);

    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, ['type' => 'full']);
    $line = $stocktake->lines->first();

    // Without second person → fails
    actAs($staff);
    expect(fn () => app(RecordStocktakeCount::class)->execute($staff, [
        'line_id' => $line->id,
        'counted_qty' => 10,
    ]))->toThrow(ValidationException::class);

    // With same user as second person → fails
    expect(fn () => app(RecordStocktakeCount::class)->execute($staff, [
        'line_id' => $line->id,
        'counted_qty' => 10,
        'second_person_id' => $staff->id,
    ]))->toThrow(ValidationException::class);

    // With distinct second person → succeeds
    $updated = app(RecordStocktakeCount::class)->execute($staff, [
        'line_id' => $line->id,
        'counted_qty' => 10,
        'second_person_id' => $witness->id,
    ]);

    expect($updated->second_person_id)->toBe($witness->id);
});

// ── STEP 4: Approval ────────────────────────────────────────────────────────

test('approval posts adjustments and the ledger reflects them (AC-09)', function () {
    $pm = pm();
    $staff = ps();
    $owner = owner();

    $product = makeStocktakeProduct();
    $batch = makeStocktakeBatch($product);
    StockMovementService::post($batch, 30, 'receipt', $product, $pm, 'sellable');

    // Create and count
    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, ['type' => 'full']);
    $line = $stocktake->lines->first();

    actAs($staff);
    app(RecordStocktakeCount::class)->execute($staff, [
        'line_id' => $line->id,
        'counted_qty' => 25, // -5 variance, value = 5 × 500 santim = 2500 santim = 25 ETB < 500 ETB threshold → no recount
    ]);

    // Submit for review
    actAs($pm);
    $approveAction = app(ApproveStocktake::class);
    $approveAction->submitForReview($pm, $stocktake->fresh());

    expect($stocktake->fresh()->status)->toBe(Stocktake::STATUS_REVIEW);

    // Owner approves (PM created it → Owner must approve)
    actAs($owner);
    $approveAction->approve($owner, $stocktake->fresh());

    $stocktake->refresh();
    expect($stocktake->status)->toBe(Stocktake::STATUS_APPROVED)
        ->and($stocktake->approved_by)->toBe($owner->id);

    // Ledger has the adjustment
    $movement = StockMovement::where('batch_id', $batch->id)
        ->where('type', 'adjustment')
        ->latest('id')
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->quantity)->toBe(-5)
        ->and($movement->balance_after)->toBe(25);

    // Stock actually updated
    expect($batch->fresh()->sellableStockQuantity())->toBe(25);

    // Audit recorded
    expect(AuditLog::where('action', 'stock.stocktake.approved')->count())->toBe(1);
});

test('rejection leaves stock unchanged (A2)', function () {
    $pm = pm();
    $staff = ps();
    $owner = owner();

    $product = makeStocktakeProduct();
    $batch = makeStocktakeBatch($product);
    StockMovementService::post($batch, 30, 'receipt', $product, $pm, 'sellable');

    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, ['type' => 'full']);
    $line = $stocktake->lines->first();

    actAs($staff);
    app(RecordStocktakeCount::class)->execute($staff, [
        'line_id' => $line->id,
        'counted_qty' => 20, // -10 variance
    ]);

    actAs($pm);
    app(ApproveStocktake::class)->submitForReview($pm, $stocktake->fresh());

    // Owner rejects
    actAs($owner);
    app(ApproveStocktake::class)->reject($owner, $stocktake->fresh(), 'Counts look unreliable.');

    expect($stocktake->fresh()->status)->toBe(Stocktake::STATUS_REJECTED);
    expect($batch->fresh()->sellableStockQuantity())->toBe(30); // unchanged
});

test('an unapproved count leaves stock unchanged (E1)', function () {
    $pm = pm();
    $staff = ps();

    $product = makeStocktakeProduct();
    $batch = makeStocktakeBatch($product);
    StockMovementService::post($batch, 30, 'receipt', $product, $pm, 'sellable');

    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, ['type' => 'full']);

    // Count but don't submit for review / don't approve
    actAs($staff);
    app(RecordStocktakeCount::class)->execute($staff, [
        'line_id' => $stocktake->lines->first()->id,
        'counted_qty' => 20,
    ]);

    // Stock is still 30
    expect($batch->fresh()->sellableStockQuantity())->toBe(30);
    expect($stocktake->fresh()->status)->toBe(Stocktake::STATUS_COUNTING);
});

test('initiator cannot approve their own stocktake', function () {
    $pm = pm();
    $staff = ps();

    $product = makeStocktakeProduct();
    $batch = makeStocktakeBatch($product);
    StockMovementService::post($batch, 10, 'receipt', $product, $pm, 'sellable');

    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, ['type' => 'full']);

    actAs($staff);
    app(RecordStocktakeCount::class)->execute($staff, [
        'line_id' => $stocktake->lines->first()->id,
        'counted_qty' => 10,
    ]);

    actAs($pm);
    app(ApproveStocktake::class)->submitForReview($pm, $stocktake->fresh());

    // PM trying to approve their own → denied
    expect(fn () => app(ApproveStocktake::class)->approve($pm, $stocktake->fresh()))
        ->toThrow(AuthorizationException::class);
});

// ── Recount rule (A1) ───────────────────────────────────────────────────────

test('variance above recount threshold forces a recount before approval', function () {
    $pm = pm();
    $staff = ps();
    $owner = owner();

    // unit_cost = 10000 santim = 100 ETB; threshold = 500 ETB
    $product = makeStocktakeProduct();
    $batch = makeStocktakeBatch($product, ['unit_cost' => 10000]);
    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');

    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, ['type' => 'full']);
    $line = $stocktake->lines->first();

    // 6 unit variance → 6 × 100 ETB = 600 ETB > 500 ETB → recount required
    actAs($staff);
    app(RecordStocktakeCount::class)->execute($staff, [
        'line_id' => $line->id,
        'counted_qty' => 44, // variance = -6
    ]);

    actAs($pm);
    app(ApproveStocktake::class)->submitForReview($pm, $stocktake->fresh());

    // Approving without recount → exception
    actAs($owner);
    expect(fn () => app(ApproveStocktake::class)->approve($owner, $stocktake->fresh()))
        ->toThrow(ValidationException::class);

    // Record a recount
    $line->refresh();
    app(RecordStocktakeCount::class)->recordRecount($owner, $line, 44);
    expect($line->fresh()->recount_qty)->toBe(44);

    // Now approval succeeds
    app(ApproveStocktake::class)->approve($owner, $stocktake->fresh());
    expect($stocktake->fresh()->status)->toBe(Stocktake::STATUS_APPROVED);
    expect($batch->fresh()->sellableStockQuantity())->toBe(44);
});

// ── Controlled drug register entries ─────────────────────────────────────────

test('approved stocktake with controlled drug writes register entry with second person', function () {
    $pm = pm();
    $staff = ps();
    $witness = ps();
    $owner = owner();

    $product = makeStocktakeProduct(['classification' => 'controlled']);
    $batch = makeStocktakeBatch($product);
    StockMovementService::post($batch, 20, 'receipt', $product, $pm, 'sellable', secondPerson: $witness);

    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, ['type' => 'full']);
    $line = $stocktake->lines->first();

    actAs($staff);
    app(RecordStocktakeCount::class)->execute($staff, [
        'line_id' => $line->id,
        'counted_qty' => 18,
        'second_person_id' => $witness->id,
    ]);

    actAs($pm);
    app(ApproveStocktake::class)->submitForReview($pm, $stocktake->fresh());

    actAs($owner);
    app(ApproveStocktake::class)->approve($owner, $stocktake->fresh());

    // Check controlled drug register entry
    $registerEntry = ControlledDrugRegister::where('entry_type', 'adjustment')
        ->latest('id')
        ->first();

    expect($registerEntry)->not->toBeNull()
        ->and($registerEntry->running_balance)->toBe(18)
        ->and($registerEntry->second_person_id)->toBe($witness->id);
});

// ── System admin blocked ─────────────────────────────────────────────────────

test('system admin cannot access stocktakes', function () {
    $sa = sa();
    actAs($sa);

    $this->get(route('inventory.stocktakes.index'))->assertForbidden();
});

// ── Livewire components ──────────────────────────────────────────────────────

test('Livewire Index renders and lists stocktakes', function () {
    $pm = pm();
    $product = makeStocktakeProduct();
    $batch = makeStocktakeBatch($product);
    StockMovementService::post($batch, 10, 'receipt', $product, $pm, 'sellable');

    actAs($pm);
    app(CreateStocktake::class)->execute($pm, ['type' => 'full']);

    Livewire::test(Index::class)
        ->assertSee(__('stocktake.title'))
        ->assertSee('Full');
});

test('Livewire Show allows count entry and submit for review', function () {
    $pm = pm();
    $staff = ps();

    $product = makeStocktakeProduct();
    $batch = makeStocktakeBatch($product);
    StockMovementService::post($batch, 10, 'receipt', $product, $pm, 'sellable');

    actAs($pm);
    $stocktake = app(CreateStocktake::class)->execute($pm, ['type' => 'full']);
    $line = $stocktake->lines->first();

    actAs($staff);
    Livewire::test(Show::class, ['stocktake' => $stocktake])
        ->call('selectLine', $line->id)
        ->set('countedQty', '10')
        ->call('saveCount')
        ->assertHasNoErrors()
        ->assertSee(__('stocktake.count_saved'));

    expect($line->fresh()->counted_qty)->toBe(10)
        ->and($line->fresh()->variance)->toBe(0);

    // Submit for review
    actAs($pm);
    Livewire::test(Show::class, ['stocktake' => $stocktake->fresh()])
        ->call('submitForReview')
        ->assertSee(__('stocktake.submitted_for_review'));

    expect($stocktake->fresh()->status)->toBe(Stocktake::STATUS_REVIEW);
});
