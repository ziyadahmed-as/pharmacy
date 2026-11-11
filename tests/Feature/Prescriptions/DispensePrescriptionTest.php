<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Prescriptions\DispensePrescriptionLine;
use App\Actions\Prescriptions\RegisterPrescription;
use App\Actions\Prescriptions\VerifyPrescription;
use App\Actions\Shifts\OpenShift;
use App\Exceptions\NoOpenShiftException;
use App\Exceptions\PrescriptionNotDispensableException;
use App\Exceptions\QuantityExceedsPrescriptionException;
use App\Livewire\Dispense\Show;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-21: Dispense prescription
 * BRD: FR-4.1, 4.8–4.10, 3.4, 3.5, 2.4; BR-03, BR-12, BR-17. AC-03, AC-12.
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

function dispenseLicensedUser(string $category = 'Pharmacist', ?string $expiresAt = null): User
{
    $user = ps();
    $staff = makeStaff(['user_id' => $user->id]);

    StaffLicence::create([
        'staff_id' => $staff->id,
        'number' => 'DLIC-'.uniqid(),
        'issuing_body' => 'EFDA',
        'category' => $category,
        'issued_at' => now()->subMonths(6)->toDateString(),
        'expires_at' => $expiresAt ?? now()->addYear()->toDateString(),
    ]);

    return $user->refresh();
}

function makeRxProduct(string $suffix = '', string $classification = 'rx'): Product
{
    static $seq = 1;
    $n = $seq++;

    return Product::create([
        'sku' => "RX-DISP-{$n}{$suffix}",
        'brand' => "Amoxicillin {$n}00mg",
        'generic_name' => 'Amoxicillin',
        'strength' => "{$n}00mg",
        'dosage_form' => 'capsule',
        'classification' => $classification,
        'storage_condition' => 'room_temperature',
        'is_active' => true,
    ]);
}

function makeVerifiedPrescription(User $actor, Product $product, int $qty = 10): Prescription
{
    $register = app(RegisterPrescription::class);
    $rx = $register->execute(
        user: $actor,
        prescriberName: 'Dr. Alemu',
        prescriberRegNo: 'DOC-123',
        prescriptionDate: now()->subDays(1)->toDateString(),
        patientData: ['name' => 'Tigist Patient', 'phone' => '+251922334455'],
        lines: [
            ['product_id' => $product->id, 'dose' => '500mg twice daily', 'quantity' => $qty],
        ],
    );

    app(VerifyPrescription::class)->execute(user: $actor, prescription: $rx);

    return $rx->refresh();
}

function makeBatchForProduct(Product $product, int $stock = 100): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'BT-'.uniqid(),
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 500,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $stock,
        'status' => 'sellable',
    ]);

    // Set a price on the product
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

function dispenseOpenShiftForUser(User $user): Shift
{
    $till = Till::create(['name' => 'Till-D-'.uniqid(), 'is_active' => true]);

    return app(OpenShift::class)->execute($user, $till->id, 0);
}

function dispenseSale(
    User $user,
    Prescription $rx,
    int $lineId,
    int $qty,
    array $extraArgs = []
): Sale {
    return app(DispensePrescriptionLine::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $lineId,
        quantity: $qty,
        payments: [['method' => 'cash', 'amount' => 1500 * $qty]],
        idempotencyKey: Str::uuid()->toString(),
        batchId: $extraArgs['batchId'] ?? null,
        overrideReason: $extraArgs['overrideReason'] ?? null,
        repeatWarningAcknowledged: $extraArgs['repeatWarningAcknowledged'] ?? false,
    );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('blocks dispensing an unverified prescription (AC-03)', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    makeBatchForProduct($product);

    // Register but do NOT verify
    $rx = app(RegisterPrescription::class)->execute(
        user: $user,
        prescriberName: 'Dr. Test',
        prescriberRegNo: 'DOC-999',
        prescriptionDate: now()->subDays(1)->toDateString(),
        patientData: ['name' => 'Patient One', 'phone' => '+251911000001'],
        lines: [['product_id' => $product->id, 'dose' => '250mg', 'quantity' => 5]],
    );
    $line = $rx->lines->first();

    expect(fn () => dispenseSale($user, $rx, $line->id, 1))
        ->toThrow(PrescriptionNotDispensableException::class);
});

it('blocks dispensing a fully dispensed prescription (AC-12)', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    makeBatchForProduct($product);

    $rx = makeVerifiedPrescription($user, $product, 2);
    $line = $rx->lines->first();

    // Dispense all 2
    dispenseSale($user, $rx, $line->id, 2);
    $rx->refresh();

    // Now try again on fully dispensed prescription
    expect(fn () => dispenseSale($user, $rx, $line->id, 1))
        ->toThrow(PrescriptionNotDispensableException::class);
});

it('blocks dispensing an expired prescription (AC-12)', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    makeBatchForProduct($product);

    $rx = makeVerifiedPrescription($user, $product, 5);
    // Artificially age the prescription beyond validity
    $rx->prescription_date = now()->subDays(60);
    $rx->save();

    $line = $rx->lines->first();

    expect(fn () => dispenseSale($user, $rx, $line->id, 1))
        ->toThrow(PrescriptionNotDispensableException::class);
});

it('blocks quantity above prescribed without override reason (BR-17)', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    makeBatchForProduct($product, 500);

    $rx = makeVerifiedPrescription($user, $product, 5);
    $line = $rx->lines->first();

    expect(fn () => dispenseSale($user, $rx, $line->id, 10)) // 10 > 5
        ->toThrow(QuantityExceedsPrescriptionException::class);
});

it('allows quantity above prescribed with a licensed override and reason (A2, BR-17)', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    makeBatchForProduct($product, 500);

    $rx = makeVerifiedPrescription($user, $product, 5);
    $line = $rx->lines->first();

    $sale = dispenseSale($user, $rx, $line->id, 8, [
        'overrideReason' => 'Clinically justified excess per consultant advice',
    ]);

    expect($sale)->toBeInstanceOf(Sale::class)
        ->and($sale->receipt_no)->toStartWith('REC');

    $event = DispensingEvent::where('sale_id', $sale->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->quantity)->toBe(8);
});

it('triggers repeat purchase warning and allows override (A1)', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    app(SettingsRepository::class)->put('prescriptions.repeat_purchase_warning_days', 30);

    $product = makeRxProduct();
    makeBatchForProduct($product, 500);

    $rx = makeVerifiedPrescription($user, $product, 20);
    $line = $rx->lines->first();

    // First dispense
    dispenseSale($user, $rx, $line->id, 5);
    $rx->refresh()->load('lines.product');
    $line->refresh();

    // Second dispense without acknowledgement → warning
    expect(fn () => dispenseSale($user, $rx, $line->id, 5))
        ->toThrow(InvalidArgumentException::class);

    // With acknowledgement → succeeds
    $sale = dispenseSale($user, $rx, $line->id, 5, ['repeatWarningAcknowledged' => true]);
    expect($sale)->toBeInstanceOf(Sale::class);
});

it('creates a dispensing event linking line, batch, quantity, user, time and receipt', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    $batch = makeBatchForProduct($product);

    $rx = makeVerifiedPrescription($user, $product, 10);
    $line = $rx->lines->first();

    $sale = dispenseSale($user, $rx, $line->id, 3);

    $event = DispensingEvent::where('sale_id', $sale->id)->first();

    expect($event)->not->toBeNull()
        ->and($event->prescription_line_id)->toBe($line->id)
        ->and($event->batch_id)->toBe($batch->id)
        ->and($event->quantity)->toBe(3)
        ->and($event->user_id)->toBe($user->id)
        ->and($event->dispensed_at)->not->toBeNull()
        ->and($event->sale->receipt_no)->toBe($sale->receipt_no);
});

it('transitions prescription status to partially and fully dispensed', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    makeBatchForProduct($product, 100);

    $rx = makeVerifiedPrescription($user, $product, 6);
    $line = $rx->lines->first();

    // Partial
    dispenseSale($user, $rx, $line->id, 4);
    $rx->refresh();
    expect($rx->status)->toBe(Prescription::STATUS_PARTIALLY_DISPENSED);

    // Full
    dispenseSale($user, $rx, $line->id, 2);
    $rx->refresh();
    expect($rx->status)->toBe(Prescription::STATUS_FULLY_DISPENSED);
});

it('refuses dispensing with an expired licence', function () {
    // Create user with expired licence
    $user = ps();
    $staff = makeStaff(['user_id' => $user->id]);
    StaffLicence::create([
        'staff_id' => $staff->id,
        'number' => 'EXPIRED-LIC-'.uniqid(),
        'issuing_body' => 'EFDA',
        'category' => 'Pharmacist',
        'issued_at' => now()->subYear()->toDateString(),
        'expires_at' => now()->subDay()->toDateString(), // expired yesterday
    ]);
    $user = $user->refresh();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    makeBatchForProduct($product);

    $actorForRx = dispenseLicensedUser();
    dispenseOpenShiftForUser($actorForRx);
    $rx = makeVerifiedPrescription($actorForRx, $product, 5);
    $line = $rx->lines->first();

    expect(fn () => dispenseSale($user, $rx, $line->id, 1))
        ->toThrow(AuthorizationException::class);
});

it('double submit with same idempotency key creates only one dispensing event', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    makeBatchForProduct($product, 100);

    $rx = makeVerifiedPrescription($user, $product, 10);
    $line = $rx->lines->first();
    $key = Str::uuid()->toString();

    $action = app(DispensePrescriptionLine::class);

    $payments = [['method' => 'cash', 'amount' => 1500 * 2]];

    $sale1 = $action->execute(
        user: $user, prescription: $rx, lineId: $line->id, quantity: 2,
        payments: $payments, idempotencyKey: $key,
    );

    // Second call with same key: should return same sale
    $sale2 = $action->execute(
        user: $user, prescription: $rx, lineId: $line->id, quantity: 2,
        payments: $payments, idempotencyKey: $key,
    );

    expect($sale1->id)->toBe($sale2->id);
    expect(DispensingEvent::where('prescription_line_id', $line->id)->count())->toBe(1);
});

it('requires an open shift before dispensing', function () {
    $user = dispenseLicensedUser();
    // No shift opened

    $product = makeRxProduct();
    makeBatchForProduct($product);

    $actorForRx = dispenseLicensedUser();
    dispenseOpenShiftForUser($actorForRx);
    $rx = makeVerifiedPrescription($actorForRx, $product, 5);
    $line = $rx->lines->first();

    expect(fn () => dispenseSale($user, $rx, $line->id, 1))
        ->toThrow(NoOpenShiftException::class);
});

it('links sale to the prescription via prescription_id', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    makeBatchForProduct($product);

    $rx = makeVerifiedPrescription($user, $product, 5);
    $line = $rx->lines->first();

    $sale = dispenseSale($user, $rx, $line->id, 2);
    $sale->refresh();

    expect($sale->prescription_id)->toBe($rx->id);
});

it('Livewire Dispense Show component renders lines and dispenses successfully', function () {
    $user = dispenseLicensedUser();
    dispenseOpenShiftForUser($user);

    $product = makeRxProduct();
    $batch = makeBatchForProduct($product, 50);

    $rx = makeVerifiedPrescription($user, $product, 10);
    $line = $rx->lines->first();

    Livewire::actingAs($user)
        ->test(Show::class, ['prescription' => $rx])
        ->assertSee($product->brand)
        ->assertSee('10') // remaining
        ->set('quantity', 3)
        ->call('dispense')
        ->assertHasNoErrors()
        ->assertSee(__('prescriptions.dispensing_success', ['receipt_no' => '']));

    expect($line->refresh()->quantity_dispensed)->toBe(3)
        ->and($rx->refresh()->status)->toBe(Prescription::STATUS_PARTIALLY_DISPENSED);
});
