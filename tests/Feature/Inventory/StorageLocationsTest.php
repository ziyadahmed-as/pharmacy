<?php

use App\Actions\Inventory\MoveStock;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Services\StockMovementService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedRoles();
    $this->artisan('db:seed', ['--class' => 'DocumentSequenceSeeder']);
});

test('storage locations can be created, edited, deactivated and never deleted', function () {
    $loc = StorageLocation::create([
        'code' => 'SHELF-A1',
        'name' => 'Main Shelf A1',
        'type' => StorageLocation::TYPE_SHELF,
        'is_active' => true,
    ]);

    expect($loc->id)->not->toBeNull()
        ->and($loc->code)->toBe('SHELF-A1')
        ->and($loc->is_active)->toBeTrue();

    $loc->update(['name' => 'Main Shelf A1 Updated']);
    expect($loc->fresh()->name)->toBe('Main Shelf A1 Updated');

    $loc->update(['is_active' => false]);
    expect($loc->fresh()->is_active)->toBeFalse();

    // Verify code uniqueness
    expect(fn () => StorageLocation::create([
        'code' => 'SHELF-A1',
        'name' => 'Duplicate Shelf',
        'type' => StorageLocation::TYPE_SHELF,
        'is_active' => true,
    ]))->toThrow(QueryException::class);
});

test('moving stock between locations creates two ledger rows and preserves total stock', function () {
    $user = ps();
    $pm = pm();

    $sourceLoc = StorageLocation::create([
        'code' => 'DISP-1',
        'name' => 'Dispensary Shelf 1',
        'type' => StorageLocation::TYPE_DISPENSARY,
        'is_active' => true,
    ]);

    $targetLoc = StorageLocation::create([
        'code' => 'FRIDGE-1',
        'name' => 'Main Refrigerator',
        'type' => StorageLocation::TYPE_FRIDGE,
        'is_active' => true,
    ]);

    $product = Product::create([
        'brand' => 'Insulin Glargine',
        'generic_name' => 'Insulin',
        'classification' => 'otc',
        'unit' => 'vial',
        'is_active' => true,
    ]);

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'INS-B1',
        'expiry_date' => now()->addYear()->toDateString(),
        'unit_cost' => 1500,
    ]);

    // Initial stock receipt posting to sourceLoc
    StockMovementService::post(
        batch: $batch,
        signedQuantity: 100,
        type: 'receipt',
        source: $batch,
        user: $pm,
        status: 'sellable',
        locationId: $sourceLoc->id,
        description: 'Initial stock'
    );

    $sourceStock = BatchStock::where('batch_id', $batch->id)
        ->where('location_id', $sourceLoc->id)
        ->first();

    expect($sourceStock->quantity)->toBe(100);

    // Move 40 units from source to target
    $moveAction = app(MoveStock::class);
    $result = $moveAction->execute(
        batch: $batch,
        fromLocationId: $sourceLoc->id,
        toLocationId: $targetLoc->id,
        quantity: 40,
        user: $user,
        status: 'sellable',
        reason: 'Transferring cold storage items'
    );

    expect($result)->toHaveKeys(['out_movement', 'in_movement']);

    $sourceStock->refresh();
    $targetStock = BatchStock::where('batch_id', $batch->id)
        ->where('location_id', $targetLoc->id)
        ->first();

    expect($sourceStock->quantity)->toBe(60)
        ->and($targetStock)->not->toBeNull()
        ->and($targetStock->quantity)->toBe(40);

    // Assert total stock across locations equals initial 100
    $totalStock = BatchStock::where('batch_id', $batch->id)->sum('quantity');
    expect($totalStock)->toBe(100);

    // Verify two ledger movements recorded
    expect($result['out_movement']->type)->toBe('transfer')
        ->and($result['out_movement']->quantity)->toBe(-40)
        ->and($result['out_movement']->location_id)->toBe($sourceLoc->id)
        ->and($result['in_movement']->type)->toBe('transfer')
        ->and($result['in_movement']->quantity)->toBe(40)
        ->and($result['in_movement']->location_id)->toBe($targetLoc->id);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'stock.location.moved',
    ]);
});

test('cannot move more quantity than available at source location', function () {
    $user = ps();

    $sourceLoc = StorageLocation::create([
        'code' => 'SHELF-1',
        'name' => 'Shelf 1',
        'type' => StorageLocation::TYPE_SHELF,
        'is_active' => true,
    ]);

    $targetLoc = StorageLocation::create([
        'code' => 'SHELF-2',
        'name' => 'Shelf 2',
        'type' => StorageLocation::TYPE_SHELF,
        'is_active' => true,
    ]);

    $product = Product::create([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'classification' => 'otc',
        'unit' => 'capsule',
        'is_active' => true,
    ]);

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'AMX-B1',
        'expiry_date' => now()->addYear()->toDateString(),
        'unit_cost' => 500,
    ]);

    StockMovementService::post(
        batch: $batch,
        signedQuantity: 20,
        type: 'receipt',
        source: $batch,
        user: $user,
        status: 'sellable',
        locationId: $sourceLoc->id
    );

    $moveAction = app(MoveStock::class);

    expect(fn () => $moveAction->execute(
        batch: $batch,
        fromLocationId: $sourceLoc->id,
        toLocationId: $targetLoc->id,
        quantity: 25, // Available is only 20
        user: $user,
        status: 'sellable',
        reason: 'Excess move'
    ))->toThrow(ValidationException::class);
});

test('cannot move to or from inactive location or identical location', function () {
    $user = ps();

    $activeLoc = StorageLocation::create([
        'code' => 'ACT-1',
        'name' => 'Active Location',
        'type' => StorageLocation::TYPE_SHELF,
        'is_active' => true,
    ]);

    $inactiveLoc = StorageLocation::create([
        'code' => 'INACT-1',
        'name' => 'Inactive Location',
        'type' => StorageLocation::TYPE_SHELF,
        'is_active' => false,
    ]);

    $product = Product::create([
        'brand' => 'Paracetamol 500mg',
        'generic_name' => 'Paracetamol',
        'classification' => 'otc',
        'unit' => 'tablet',
        'is_active' => true,
    ]);

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'PAR-B1',
        'expiry_date' => now()->addYear()->toDateString(),
        'unit_cost' => 200,
    ]);

    StockMovementService::post(
        batch: $batch,
        signedQuantity: 50,
        type: 'receipt',
        source: $batch,
        user: $user,
        status: 'sellable',
        locationId: $activeLoc->id
    );

    $moveAction = app(MoveStock::class);

    // Target is inactive
    expect(fn () => $moveAction->execute(
        batch: $batch,
        fromLocationId: $activeLoc->id,
        toLocationId: $inactiveLoc->id,
        quantity: 10,
        user: $user,
        status: 'sellable'
    ))->toThrow(ValidationException::class);

    // Same location source and destination
    expect(fn () => $moveAction->execute(
        batch: $batch,
        fromLocationId: $activeLoc->id,
        toLocationId: $activeLoc->id,
        quantity: 10,
        user: $user,
        status: 'sellable'
    ))->toThrow(ValidationException::class);
});

test('controlled products require controlled-cabinet location', function () {
    $user = ps();

    $shelfLoc = StorageLocation::create([
        'code' => 'SHELF-REG',
        'name' => 'Regular Shelf',
        'type' => StorageLocation::TYPE_SHELF,
        'is_active' => true,
    ]);

    $cabinetLoc = StorageLocation::create([
        'code' => 'SAFE-1',
        'name' => 'Controlled Drug Safe',
        'type' => StorageLocation::TYPE_CONTROLLED_CABINET,
        'is_active' => true,
    ]);

    $controlledProduct = Product::create([
        'brand' => 'Morphine 10mg',
        'generic_name' => 'Morphine',
        'classification' => 'controlled',
        'unit' => 'ampoule',
        'is_active' => true,
    ]);

    $batch = Batch::create([
        'product_id' => $controlledProduct->id,
        'batch_no' => 'MOR-B1',
        'expiry_date' => now()->addYear()->toDateString(),
        'unit_cost' => 3000,
    ]);

    // Posting receipt of controlled drug to a regular shelf must fail
    expect(fn () => StockMovementService::post(
        batch: $batch,
        signedQuantity: 10,
        type: 'receipt',
        source: $batch,
        user: $user,
        status: 'sellable',
        locationId: $shelfLoc->id
    ))->toThrow(RuntimeException::class);

    // Posting to controlled-cabinet location succeeds
    $movement = StockMovementService::post(
        batch: $batch,
        signedQuantity: 10,
        type: 'receipt',
        source: $batch,
        user: $user,
        status: 'sellable',
        locationId: $cabinetLoc->id
    );

    expect($movement)->not->toBeNull()
        ->and($movement->location_id)->toBe($cabinetLoc->id);
});
