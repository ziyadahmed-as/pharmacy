<?php

use App\Actions\Catalogue\BulkSetSellingPrices;
use App\Actions\Catalogue\SetSellingPrice;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Services\PriceResolver;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

function makeProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'strength' => '500mg',
        'dosage_form' => 'Capsule',
        'pack_size' => '10x10',
        'unit' => 'capsule',
        'manufacturer' => 'Cadila',
        'classification' => 'rx',
        'is_active' => true,
    ], $attributes));
}

test('PM and Owner can set a selling price (AC-16)', function () {
    $product = makeProduct();
    $pmUser = pm();
    $ownerUser = owner();
    $action = new SetSellingPrice();

    $effective = now();
    $price1 = $action->execute($product, 2500, $effective, 'Initial price', null, $pmUser);

    expect($price1)->toBeInstanceOf(ProductPrice::class)
        ->and($price1->price)->toBe(2500)
        ->and($price1->set_by)->toBe($pmUser->id);

    $price2 = $action->execute($product, 3000, $effective->copy()->addDays(2), 'Owner markup', null, $ownerUser);

    expect($price2->price)->toBe(3000)
        ->and($price2->set_by)->toBe($ownerUser->id);
});

test('zero or negative price is refused (E2)', function () {
    $product = makeProduct();
    $action = new SetSellingPrice();

    expect(fn () => $action->execute($product, 0, now(), 'Free promo', null, pm()))
        ->toThrow(ValidationException::class);

    expect(fn () => $action->execute($product, -500, now(), 'Negative price', null, pm()))
        ->toThrow(ValidationException::class);
});

test('staff change denied including direct call (AC-16)', function () {
    $product = makeProduct();
    $staffUser = ps();
    $action = new SetSellingPrice();

    expect(fn () => $action->execute($product, 1500, now(), 'Staff override', null, $staffUser))
        ->toThrow(ValidationException::class);
});

test('system administrator change denied', function () {
    $product = makeProduct();
    $saUser = sa();
    $action = new SetSellingPrice();

    expect(fn () => $action->execute($product, 1500, now(), 'SA override', null, $saUser))
        ->toThrow(ValidationException::class);
});

test('history is preserved and resolver returns the right price before and after effective time (AC-17)', function () {
    $product = makeProduct();
    $action = new SetSellingPrice();
    $pmUser = pm();

    $t1 = Carbon::parse('2026-11-01 10:00:00');
    $t2 = Carbon::parse('2026-11-10 10:00:00');

    $action->execute($product, 1000, $t1, 'November 1 price', null, $pmUser);
    $action->execute($product, 1500, $t2, 'November 10 increase', null, $pmUser);

    // Before t1: no price
    expect(PriceResolver::at($product, null, $t1->copy()->subDay()))->toBeNull();

    // At and after t1 but before t2: price is 1000
    expect(PriceResolver::at($product, null, $t1)->price)->toBe(1000);
    expect(PriceResolver::at($product, null, $t2->copy()->subSecond())->price)->toBe(1000);

    // At and after t2: price is 1500
    expect(PriceResolver::at($product, null, $t2)->price)->toBe(1500);
    expect(PriceResolver::at($product, null, $t2->copy()->addDays(5))->price)->toBe(1500);

    // Append-only guarantee
    $priceRecord = ProductPrice::first();
    expect(fn () => $priceRecord->update(['price' => 9999]))->toThrow(RuntimeException::class);
    expect(fn () => $priceRecord->delete())->toThrow(RuntimeException::class);
});

test('unit specific price resolution works correctly', function () {
    $product = makeProduct();
    $unitBox = ProductUnit::create(['product_id' => $product->id, 'name' => 'box', 'factor' => 10]);
    $action = new SetSellingPrice();
    $pmUser = pm();

    $now = now();
    $action->execute($product, 100, $now, 'Per tablet price', null, $pmUser);
    $action->execute($product, 900, $now, 'Per box price', $unitBox, $pmUser);

    expect(PriceResolver::at($product, null, $now)->price)->toBe(100);
    expect(PriceResolver::at($product, $unitBox, $now)->price)->toBe(900);
});

test('audit entry records price.changed with old and new values', function () {
    $product = makeProduct();
    $action = new SetSellingPrice();
    $pmUser = pm();

    $t1 = now()->subDays(1);
    $action->execute($product, 2000, $t1, 'First price', null, $pmUser);

    $t2 = now();
    $action->execute($product, 2500, $t2, 'Price hike', null, $pmUser);

    $audit = AuditLog::where('action', 'price.changed')->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->old_values['price'])->toBe(2000)
        ->and($audit->new_values['price'])->toBe(2500)
        ->and($audit->new_values['reason'])->toBe('Price hike');
});

test('bulk set selling prices works transactionally', function () {
    $p1 = makeProduct(['brand' => 'Medicine A']);
    $p2 = makeProduct(['brand' => 'Medicine B']);
    $bulk = new BulkSetSellingPrices();

    $items = [
        ['product' => $p1, 'price' => 1200, 'effective_from' => now(), 'reason' => 'Bulk import'],
        ['product' => $p2, 'price' => 1800, 'effective_from' => now(), 'reason' => 'Bulk import'],
    ];

    $results = $bulk->execute($items, pm());

    expect($results)->toHaveCount(2)
        ->and(PriceResolver::at($p1)->price)->toBe(1200)
        ->and(PriceResolver::at($p2)->price)->toBe(1800);
});
