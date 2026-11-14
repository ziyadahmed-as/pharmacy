<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Prescriptions\DispensePartially;
use App\Actions\Prescriptions\RegisterPrescription;
use App\Actions\Prescriptions\VerifyPrescription;
use App\Actions\Shifts\OpenShift;
use App\Exceptions\PrescriptionNotDispensableException;
use App\Exceptions\QuantityExceedsPrescriptionException;
use App\Livewire\Dispense\Show;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\DispensingEvent;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StaffLicence;
use App\Models\Till;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-24: Dispense in parts and refill.
 * BRD: FR-4.5, 4.8, 4.10; D-08.
 */
beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('profile.name', 'Test Pharmacy');
    app(SettingsRepository::class)->put('prescriptions.validity_days', 30);
    app(SettingsRepository::class)->put('prescriptions.repeat_purchase_warning_days', 0);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function partiallyLicensedUser(string $category = 'Pharmacist', ?string $expiresAt = null): User
{
    $user = ps();
    $staff = makeStaff(['user_id' => $user->id]);

    StaffLicence::create([
        'staff_id' => $staff->id,
        'number' => 'PART-LIC-'.uniqid(),
        'issuing_body' => 'EFDA',
        'category' => $category,
        'issued_at' => now()->subMonths(6)->toDateString(),
        'expires_at' => $expiresAt ?? now()->addYear()->toDateString(),
    ]);

    return $user->refresh();
}

function partiallyRxProduct(string $suffix = ''): Product
{
    static $seq = 100;
    $n = $seq++;

    return Product::create([
        'sku' => "RX-PART-{$n}{$suffix}",
        'brand' => "Amoxicillin {$n}00mg",
        'generic_name' => 'Amoxicillin',
        'strength' => "{$n}00mg",
        'dosage_form' => 'capsule',
        'classification' => 'rx',
        'storage_condition' => 'room_temperature',
        'is_active' => true,
    ]);
}

function partiallyBatch(Product $product, int $stock = 200): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'PART-BT-'.uniqid(),
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 500,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $stock,
        'status' => 'sellable',
    ]);

    app(SetSellingPrice::class)->execute(
        product: $product,
        price: 1500,
        effectiveFrom: now()->subMinute(),
        reason: 'Initial price',
        unit: null,
        user: pm(),
    );

    return $batch;
}

function partiallyPrescription(
    User $actor,
    Product $product,
    int $qty = 30,
    int $refillsAllowed = 0,
    ?string $prescriptionDate = null
): Prescription {
    $register = app(RegisterPrescription::class);
    $rx = $register->execute(
        user: $actor,
        prescriberName: 'Dr. Tesfaye',
        prescriberRegNo: 'DOC-PART-99',
        prescriptionDate: $prescriptionDate ?? now()->subDays(1)->toDateString(),
        patientData: ['name' => 'Almaz Patient', 'phone' => '+251911223344'],
        lines: [
            [
                'product_id' => $product->id,
                'dose' => '500mg twice daily',
                'quantity' => $qty,
                'refills_allowed' => $refillsAllowed,
            ],
        ],
    );

    app(VerifyPrescription::class)->execute(user: $actor, prescription: $rx);

    return $rx->refresh();
}

function partiallyOpenShift(User $user): Shift
{
    $till = Till::create(['name' => 'Till-P-'.uniqid(), 'is_active' => true]);

    return app(OpenShift::class)->execute($user, $till->id, 0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('updates remaining quantity and next refill date upon partial dispense (STEP 2)', function () {
    $user = partiallyLicensedUser();
    partiallyOpenShift($user);

    $product = partiallyRxProduct();
    $batch = partiallyBatch($product, 100);
    $rx = partiallyPrescription($user, $product, qty: 30);
    $line = $rx->lines->first();

    expect($line->remainingQuantity())->toBe(30)
        ->and($line->quantity_dispensed)->toBe(0);

    // Dispense partial quantity of 10
    $sale = app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 10,
        payments: [['method' => 'cash', 'amount' => 15000]],
        idempotencyKey: Str::uuid()->toString(),
        nextRefillDate: '2026-11-20',
    );

    expect($sale)->toBeInstanceOf(Sale::class);

    $line->refresh();
    expect($line->quantity_dispensed)->toBe(10)
        ->and($line->remaining_quantity)->toBe(20)
        ->and($line->remainingQuantity())->toBe(20)
        ->and($line->next_refill_date->toDateString())->toBe('2026-11-20');

    // Prescription status is partially dispensed
    expect($rx->refresh()->status)->toBe(Prescription::STATUS_PARTIALLY_DISPENSED);

    // Audit logged
    expect(AuditLog::where('action', 'prescription.partially_dispensed')->where('auditable_id', (string) $rx->id)->exists())->toBeTrue();
});

it('calculates default next refill date from dose frequency when not explicitly provided', function () {
    $user = partiallyLicensedUser();
    partiallyOpenShift($user);

    $product = partiallyRxProduct();
    partiallyBatch($product, 100);
    $rx = partiallyPrescription($user, $product, qty: 30);
    $line = $rx->lines->first(); // Dose: '500mg twice daily' -> 2 per day -> 10 doses / 2 = 5 days

    app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 10,
        payments: [['method' => 'cash', 'amount' => 15000]],
        idempotencyKey: Str::uuid()->toString(),
    );

    $line->refresh();
    $expectedDate = now()->addDays(5)->toDateString();
    expect($line->next_refill_date->toDateString())->toBe($expectedDate);
});

it('transitions status from verified to partially dispensed to fully dispensed', function () {
    $user = partiallyLicensedUser();
    partiallyOpenShift($user);

    $product = partiallyRxProduct();
    partiallyBatch($product, 100);
    $rx = partiallyPrescription($user, $product, qty: 25);
    $line = $rx->lines->first();

    expect($rx->status)->toBe(Prescription::STATUS_VERIFIED);

    // 1. Partial dispense: 10 of 25
    app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 10,
        payments: [['method' => 'cash', 'amount' => 15000]],
        idempotencyKey: Str::uuid()->toString(),
    );

    expect($rx->refresh()->status)->toBe(Prescription::STATUS_PARTIALLY_DISPENSED)
        ->and($line->refresh()->remainingQuantity())->toBe(15);

    // 2. Second partial dispense: 10 of remaining 15
    app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 10,
        payments: [['method' => 'cash', 'amount' => 15000]],
        idempotencyKey: Str::uuid()->toString(),
    );

    expect($rx->refresh()->status)->toBe(Prescription::STATUS_PARTIALLY_DISPENSED)
        ->and($line->refresh()->remainingQuantity())->toBe(5);

    // 3. Final dispense: remaining 5
    app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 5,
        payments: [['method' => 'cash', 'amount' => 7500]],
        idempotencyKey: Str::uuid()->toString(),
    );

    expect($rx->refresh()->status)->toBe(Prescription::STATUS_FULLY_DISPENSED)
        ->and($line->refresh()->remainingQuantity())->toBe(0)
        ->and($line->isFullyDispensed())->toBeTrue();
});

it('refuses a quantity above the remaining amount (STEP 3 / E1)', function () {
    $user = partiallyLicensedUser();
    partiallyOpenShift($user);

    $product = partiallyRxProduct();
    partiallyBatch($product, 100);
    $rx = partiallyPrescription($user, $product, qty: 20);
    $line = $rx->lines->first();

    // First dispense: 15
    app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 15,
        payments: [['method' => 'cash', 'amount' => 22500]],
        idempotencyKey: Str::uuid()->toString(),
    );

    expect($line->refresh()->remainingQuantity())->toBe(5);

    // Attempting to dispense 10 (exceeds remaining of 5)
    expect(fn () => app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 10,
        payments: [['method' => 'cash', 'amount' => 15000]],
        idempotencyKey: Str::uuid()->toString(),
    ))->toThrow(QuantityExceedsPrescriptionException::class);
});

it('refuses a refill or partial dispense after prescription expiry (STEP 3 / E1)', function () {
    $user = partiallyLicensedUser();
    partiallyOpenShift($user);

    $product = partiallyRxProduct();
    partiallyBatch($product, 100);

    // Register a valid prescription, then age it past validity_days (30 days)
    $rx = partiallyPrescription(
        $user,
        $product,
        qty: 30,
        refillsAllowed: 1
    );
    $rx->update(['prescription_date' => now()->subDays(35)->toDateString()]);
    $line = $rx->lines->first();

    expect($rx->isExpiredForDispensing())->toBeTrue();

    // Partial dispense refused
    expect(fn () => app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 10,
        payments: [['method' => 'cash', 'amount' => 15000]],
        idempotencyKey: Str::uuid()->toString(),
    ))->toThrow(PrescriptionNotDispensableException::class);

    // Refill refused
    expect(fn () => app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 30,
        payments: [['method' => 'cash', 'amount' => 45000]],
        idempotencyKey: Str::uuid()->toString(),
        isRefill: true,
    ))->toThrow(PrescriptionNotDispensableException::class);
});

it('links each partial dispense and refill event to batch and receipt', function () {
    $user = partiallyLicensedUser();
    partiallyOpenShift($user);

    $product = partiallyRxProduct();
    $batch = partiallyBatch($product, 100);
    $rx = partiallyPrescription($user, $product, qty: 30, refillsAllowed: 1);
    $line = $rx->lines->first();

    // Dispense 1: 15 units
    $sale1 = app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 15,
        payments: [['method' => 'cash', 'amount' => 22500]],
        idempotencyKey: Str::uuid()->toString(),
    );

    // Dispense 2: 15 units (completes first fill)
    $sale2 = app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 15,
        payments: [['method' => 'cash', 'amount' => 22500]],
        idempotencyKey: Str::uuid()->toString(),
    );

    // Dispense 3: Refill of 30 units
    $sale3 = app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 30,
        payments: [['method' => 'cash', 'amount' => 45000]],
        idempotencyKey: Str::uuid()->toString(),
        isRefill: true,
    );

    // Verify 3 distinct dispensing events exist on the line
    $events = DispensingEvent::where('prescription_line_id', $line->id)->orderBy('id')->get();
    expect($events)->toHaveCount(3);

    // Event 1 linked to batch and sale 1
    expect($events[0]->batch_id)->toBe($batch->id)
        ->and($events[0]->sale_id)->toBe($sale1->id)
        ->and($events[0]->quantity)->toBe(15)
        ->and($events[0]->sale->receipt_no)->toBe($sale1->receipt_no);

    // Event 2 linked to batch and sale 2
    expect($events[1]->batch_id)->toBe($batch->id)
        ->and($events[1]->sale_id)->toBe($sale2->id)
        ->and($events[1]->quantity)->toBe(15)
        ->and($events[1]->sale->receipt_no)->toBe($sale2->receipt_no);

    // Event 3 linked to batch and sale 3
    expect($events[2]->batch_id)->toBe($batch->id)
        ->and($events[2]->sale_id)->toBe($sale3->id)
        ->and($events[2]->quantity)->toBe(30)
        ->and($events[2]->sale->receipt_no)->toBe($sale3->receipt_no);

    // Batch stock properly decremented: 100 - 15 - 15 - 30 = 40
    expect(BatchStock::where('batch_id', $batch->id)->value('quantity'))->toBe(40);
});

it('tracks refills used and keeps status partially dispensed until all refills are exhausted', function () {
    $user = partiallyLicensedUser();
    partiallyOpenShift($user);

    $product = partiallyRxProduct();
    partiallyBatch($product, 200);
    $rx = partiallyPrescription($user, $product, qty: 20, refillsAllowed: 2);
    $line = $rx->lines->first();

    // 1. Initial fill: dispense 20
    app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 20,
        payments: [['method' => 'cash', 'amount' => 30000]],
        idempotencyKey: Str::uuid()->toString(),
    );

    // Remaining is 0, but refills are allowed so status stays partially dispensed!
    $line->refresh();
    expect($line->remainingQuantity())->toBe(0)
        ->and($line->refills_allowed)->toBe(2)
        ->and($line->refills_used)->toBe(0)
        ->and($line->canRefill())->toBeTrue()
        ->and($rx->refresh()->status)->toBe(Prescription::STATUS_PARTIALLY_DISPENSED);

    // 2. Refill 1
    app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 20,
        payments: [['method' => 'cash', 'amount' => 30000]],
        idempotencyKey: Str::uuid()->toString(),
        isRefill: true,
    );

    $line->refresh();
    expect($line->refills_used)->toBe(1)
        ->and($line->canRefill())->toBeTrue()
        ->and($rx->refresh()->status)->toBe(Prescription::STATUS_PARTIALLY_DISPENSED);

    // 3. Refill 2 (final allowed refill)
    app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 20,
        payments: [['method' => 'cash', 'amount' => 30000]],
        idempotencyKey: Str::uuid()->toString(),
        isRefill: true,
    );

    $line->refresh();
    expect($line->refills_used)->toBe(2)
        ->and($line->canRefill())->toBeFalse()
        ->and($line->isFullyDispensed())->toBeTrue()
        ->and($rx->refresh()->status)->toBe(Prescription::STATUS_FULLY_DISPENSED);

    // 4. Attempting another refill beyond allowed limit throws exception
    expect(fn () => app(DispensePartially::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 20,
        payments: [['method' => 'cash', 'amount' => 30000]],
        idempotencyKey: Str::uuid()->toString(),
        isRefill: true,
    ))->toThrow(PrescriptionNotDispensableException::class);
});

it('renders remaining-quantity panel and refill button on Livewire dispense screen (STEP 4)', function () {
    $user = partiallyLicensedUser();
    partiallyOpenShift($user);

    $product = partiallyRxProduct();
    partiallyBatch($product, 100);

    // Create prescription with 1 refill allowed
    $rx = partiallyPrescription($user, $product, qty: 20, refillsAllowed: 1);
    $line = $rx->lines->first();

    // Partial dispense via Livewire
    Livewire::actingAs($user)
        ->test(Show::class, ['prescription' => $rx])
        ->assertSee($product->brand)
        ->assertSee(__('prescriptions.remaining_quantity_panel'))
        ->assertSee('20') // remaining
        ->set('quantity', 8)
        ->set('nextRefillDate', '2026-11-28')
        ->call('dispense')
        ->assertHasNoErrors()
        ->assertSee(__('prescriptions.dispensing_success', ['receipt_no' => '']));

    $line->refresh();
    expect($line->remainingQuantity())->toBe(12)
        ->and($line->next_refill_date->toDateString())->toBe('2026-11-28')
        ->and($rx->refresh()->status)->toBe(Prescription::STATUS_PARTIALLY_DISPENSED);

    // Dispense the remaining 12
    Livewire::actingAs($user)
        ->test(Show::class, ['prescription' => $rx])
        ->set('quantity', 12)
        ->call('dispense')
        ->assertHasNoErrors();

    $line->refresh();
    expect($line->remainingQuantity())->toBe(0)
        ->and($line->canRefill())->toBeTrue();

    // Screen now displays Refill button
    Livewire::actingAs($user)
        ->test(Show::class, ['prescription' => $rx])
        ->assertSee(__('prescriptions.refill'))
        ->call('selectLine', $line->id, true) // Trigger refill
        ->call('dispense')
        ->assertHasNoErrors();

    $line->refresh();
    expect($line->refills_used)->toBe(1)
        ->and($line->canRefill())->toBeFalse()
        ->and($rx->refresh()->status)->toBe(Prescription::STATUS_FULLY_DISPENSED);
});
