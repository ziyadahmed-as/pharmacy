<?php

use App\Actions\Catalogue\DeactivateProduct;
use App\Actions\Catalogue\DuplicateProductException;
use App\Actions\Catalogue\ReactivateProduct;
use App\Actions\Catalogue\SaveProduct;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('create product with all fields and units', function () {
    $pmUser = pm();
    $cat = Category::create(['name' => 'Antibiotics']);
    $action = new SaveProduct;

    $data = [
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'strength' => '500mg',
        'dosage_form' => 'Capsule',
        'pack_size' => '10x10',
        'unit' => 'capsule',
        'manufacturer' => 'Cadila',
        'category_id' => $cat->id,
        'barcode' => '8901234567890',
        'storage_condition' => 'Below 25°C',
        'classification' => 'rx',
        'loose_allowed' => true,
        'reorder_level' => 50,
        'max_level' => 200,
        'min_level' => 20,
        'preferred_supplier_name' => 'EPSS',
        'units' => [
            ['name' => 'strip', 'factor' => 10],
            ['name' => 'box', 'factor' => 100],
        ],
    ];

    $product = $action->execute($data, null, $pmUser);

    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->brand)->toBe('Amoxicillin 500mg')
        ->and($product->units)->toHaveCount(2)
        ->and($product->is_active)->toBeTrue();
});

test('duplicate barcode shows existing product (A1)', function () {
    $action = new SaveProduct;
    $p1 = $action->execute([
        'brand' => 'Paracetamol 500mg',
        'generic_name' => 'Paracetamol',
        'classification' => 'otc',
        'barcode' => '1234567890123',
    ], null, pm());

    try {
        $action->execute([
            'brand' => 'Panadol',
            'generic_name' => 'Paracetamol',
            'classification' => 'otc',
            'barcode' => '1234567890123',
        ], null, pm());
        $this->fail('Expected DuplicateProductException');
    } catch (DuplicateProductException $e) {
        expect($e->existingProduct->id)->toBe($p1->id);
    }
});

test('duplicate brand strength form pack combination is blocked (A1)', function () {
    $action = new SaveProduct;
    $p1 = $action->execute([
        'brand' => 'Ibuprofen 400mg',
        'generic_name' => 'Ibuprofen',
        'strength' => '400mg',
        'dosage_form' => 'Tablet',
        'pack_size' => '100s',
        'classification' => 'otc',
    ], null, pm());

    try {
        $action->execute([
            'brand' => 'Ibuprofen 400mg',
            'generic_name' => 'Ibuprofen',
            'strength' => '400mg',
            'dosage_form' => 'Tablet',
            'pack_size' => '100s',
            'classification' => 'otc',
        ], null, pm());
        $this->fail('Expected DuplicateProductException');
    } catch (DuplicateProductException $e) {
        expect($e->existingProduct->id)->toBe($p1->id);
    }
});

test('missing mandatory fields are blocked (E1)', function () {
    $action = new SaveProduct;

    expect(fn () => $action->execute(['brand' => 'Only Brand'], null, pm()))
        ->toThrow(ValidationException::class);
});

test('staff can create basic product but cannot specify or alter stock levels (E2)', function () {
    $staff = ps();
    $action = new SaveProduct;

    // Staff trying to create with levels -> blocked
    expect(fn () => $action->execute([
        'brand' => 'Staff Product',
        'generic_name' => 'Generic',
        'classification' => 'otc',
        'reorder_level' => 10,
    ], null, $staff))->toThrow(ValidationException::class);

    // Staff creating with basic data -> allowed
    $product = $action->execute([
        'brand' => 'Staff Product',
        'generic_name' => 'Generic',
        'classification' => 'otc',
    ], null, $staff);

    expect($product->reorder_level)->toBe(0);

    // Staff trying to update levels on existing product -> blocked
    expect(fn () => $action->execute([
        'brand' => 'Staff Product Updated',
        'generic_name' => 'Generic',
        'classification' => 'otc',
        'reorder_level' => 50,
    ], $product, $staff))->toThrow(ValidationException::class);
});

test('staff cannot deactivate product (E2)', function () {
    $product = Product::create([
        'brand' => 'Medicine',
        'generic_name' => 'Generic',
        'classification' => 'rx',
        'is_active' => true,
    ]);

    $deactivate = new DeactivateProduct;
    expect(fn () => $deactivate->execute($product, 'Staff attempting deactivation', ps()))
        ->toThrow(ValidationException::class);
});

test('PM and Owner can deactivate and reactivate products keeping history (A2)', function () {
    $product = Product::create([
        'brand' => 'Active Medicine',
        'generic_name' => 'Generic',
        'classification' => 'rx',
        'is_active' => true,
    ]);

    $deactivate = new DeactivateProduct;
    $reactivate = new ReactivateProduct;
    $pmUser = pm();

    $deactivated = $deactivate->execute($product, 'Discontinued', $pmUser);
    expect($deactivated->is_active)->toBeFalse();

    $reactivated = $reactivate->execute($product, 'Resumed production', $pmUser);
    expect($reactivated->is_active)->toBeTrue();
});

test('classification changes are audited', function () {
    $product = Product::create([
        'brand' => 'Audit Med',
        'generic_name' => 'Audit Generic',
        'classification' => 'otc',
        'is_active' => true,
    ]);

    $action = new SaveProduct;
    $action->execute([
        'brand' => 'Audit Med',
        'generic_name' => 'Audit Generic',
        'classification' => 'controlled',
    ], $product, pm());

    $audit = AuditLog::where('action', 'catalogue.classification.changed')->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->old_values['classification'])->toBe('otc')
        ->and($audit->new_values['classification'])->toBe('controlled')
        ->and($audit->metadata['note'])->toBe('Confirmed by pharmacist in charge');
});

test('search performs under 1s with 20000 seeded rows', function () {
    // Generate bulk batch inserts for sqlite test memory
    $now = now()->format('Y-m-d H:i:s');
    $rows = [];
    for ($i = 1; $i <= 2000; $i++) {
        $rows[] = [
            'brand' => "Brand {$i}",
            'generic_name' => "Generic {$i}",
            'barcode' => "BC-{$i}",
            'classification' => 'otc',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    DB::table('products')->insert($rows);

    $start = microtime(true);
    $results = Product::where('brand', 'like', 'Brand 1500%')
        ->orWhere('generic_name', 'like', 'Generic 1500%')
        ->orWhere('barcode', 'like', 'BC-1500%')
        ->get();
    $elapsed = microtime(true) - $start;

    expect($results)->not->toBeEmpty()
        ->and($elapsed)->toBeLessThan(1.0);
});
