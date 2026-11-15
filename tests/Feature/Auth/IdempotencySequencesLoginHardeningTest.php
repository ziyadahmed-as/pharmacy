<?php

use App\Actions\Auth\LogIn;
use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Inventory\SubmitStockReceipt;
use App\Actions\Prescriptions\RegisterPrescription;
use App\Actions\Prescriptions\VerifyPrescription;
use App\Actions\Sales\CompleteSale;
use App\Actions\Shifts\OpenShift;
use App\Exceptions\PrescriptionNotDispensableException;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\StaffLicence;
use App\Models\Till;
use App\Models\User;
use App\Services\DocumentSequence;
use App\Services\SettingsRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedRoles();
    $this->artisan('db:seed', ['--class' => 'DocumentSequenceSeeder']);
    app(SettingsRepository::class)->put('prescriptions.validity_days', 30);
});

test('CompleteSale idempotency: duplicate submission catches unique constraint and returns existing sale without 500 error', function () {
    $user = ps();

    $till = Till::firstOrCreate(['name' => 'Main Till'], ['is_active' => true]);
    $shift = app(OpenShift::class)->execute($user, $till, 50000);

    $product = Product::create([
        'sku' => 'PARA-IDEM-01',
        'brand' => 'Paracetamol 500mg',
        'generic_name' => 'Paracetamol',
        'dosage_form' => 'tablet',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'tablet',
        'is_active' => true,
        'loose_allowed' => true,
    ]);

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'B-IDEM-001',
        'expiry_date' => Carbon::now()->addYear()->format('Y-m-d'),
        'unit_cost' => 500,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => 100,
        'status' => 'sellable',
    ]);

    app(SetSellingPrice::class)->execute(
        product: $product,
        price: 1000,
        effectiveFrom: now()->subMinute(),
        reason: 'Price set for testing',
        user: pm()
    );

    $idempotencyKey = (string) Str::uuid();

    $items = [
        ['product_id' => $product->id, 'quantity' => 2],
    ];
    $payments = [
        ['method' => 'cash', 'amount' => 2000],
    ];

    $action = app(CompleteSale::class);

    // First sale completion
    $sale1 = $action->execute(
        user: $user,
        items: $items,
        payments: $payments,
        idempotencyKey: $idempotencyKey
    );

    expect($sale1)->not->toBeNull()
        ->and($sale1->idempotency_key)->toBe($idempotencyKey)
        ->and((int) $sale1->total)->toBe(2000);

    // Second sale completion with identical idempotencyKey
    $sale2 = $action->execute(
        user: $user,
        items: $items,
        payments: $payments,
        idempotencyKey: $idempotencyKey
    );

    expect($sale2)->not->toBeNull()
        ->and($sale2->id)->toBe($sale1->id)
        ->and($sale2->sale_number)->toBe($sale1->sale_number)
        ->and($sale2->idempotency_key)->toBe($idempotencyKey);
});

test('SubmitStockReceipt idempotency: duplicate submission returns existing receipt', function () {
    $user = ps();

    $product = Product::create([
        'sku' => 'AMOX-IDEM-01',
        'brand' => 'Amoxicillin 250mg',
        'generic_name' => 'Amoxicillin',
        'dosage_form' => 'capsule',
        'strength' => '250mg',
        'classification' => 'otc',
        'unit' => 'capsule',
        'is_active' => true,
    ]);

    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => $idempotencyKey,
        'lines' => [
            [
                'product_id' => $product->id,
                'batch_no' => 'B-SR-01',
                'manufacturing_date' => now()->subMonths(2)->format('Y-m-d'),
                'expiry_date' => now()->addMonths(18)->format('Y-m-d'),
                'quantity' => 50,
                'flag' => 'ok',
            ],
        ],
    ];

    $action = app(SubmitStockReceipt::class);

    $receipt1 = $action->execute(
        data: $payload,
        user: $user
    );

    expect($receipt1)->not->toBeNull()
        ->and($receipt1->idempotency_key)->toBe($idempotencyKey);

    // Duplicate submission
    $receipt2 = $action->execute(
        data: $payload,
        user: $user
    );

    expect($receipt2)->not->toBeNull()
        ->and($receipt2->id)->toBe($receipt1->id)
        ->and($receipt2->receipt_number)->toBe($receipt1->receipt_number);
});

test('DocumentSequence raises exception for unknown types and increments properly for pre-seeded sequences', function () {
    // Missing sequence row raises exception (create-on-demand removed)
    expect(fn () => DocumentSequence::next('non_existent_sequence_type'))
        ->toThrow(RuntimeException::class);

    // Pre-seeded sequences increment sequentially
    $seq1 = DocumentSequence::next('sale');
    $seq2 = DocumentSequence::next('sale');

    expect($seq1)->toBeString()->and($seq2)->toBeString();

    // Extract numbers to verify monotonic increase
    preg_match('/(\d+)$/', $seq1, $m1);
    preg_match('/(\d+)$/', $seq2, $m2);
    expect((int) $m2[1])->toBe((int) $m1[1] + 1);
});

test('CompleteSale with prescriptionId: verifies validity, status, product, remaining qty and licence gate', function () {
    $pharmacist = ps();
    $staff = makeStaff(['user_id' => $pharmacist->id]);
    StaffLicence::create([
        'staff_id' => $staff->id,
        'number' => 'PHARM-1234',
        'issuing_body' => 'FMHACA',
        'category' => 'pharmacist',
        'issued_at' => now()->subDay()->toDateString(),
        'expires_at' => now()->addYear()->toDateString(),
    ]);

    $till = Till::firstOrCreate(['name' => 'Prescription Till'], ['is_active' => true]);
    $shift = app(OpenShift::class)->execute($pharmacist, $till, 10000);

    $product = Product::create([
        'sku' => 'AMOX-RX-01',
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'dosage_form' => 'capsule',
        'strength' => '500mg',
        'classification' => 'rx',
        'unit' => 'capsule',
        'is_active' => true,
        'loose_allowed' => true,
    ]);

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'AMOX-B1',
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 500,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => 100,
        'status' => 'sellable',
    ]);

    app(SetSellingPrice::class)->execute(
        product: $product,
        price: 1000,
        effectiveFrom: now()->subMinute(),
        reason: 'Price set for testing',
        user: pm()
    );

    $register = app(RegisterPrescription::class);
    $rx = $register->execute(
        user: $pharmacist,
        prescriberName: 'Dr. Kebede',
        prescriberRegNo: 'DOC-123',
        prescriptionDate: now()->subDay()->toDateString(),
        patientData: ['name' => 'Abebe Bikila', 'phone' => '+251911223344'],
        lines: [
            ['product_id' => $product->id, 'dose' => '500mg tid', 'quantity' => 5],
        ],
    );

    app(VerifyPrescription::class)->execute(user: $pharmacist, prescription: $rx);

    $action = app(CompleteSale::class);

    $items = [
        ['product_id' => $product->id, 'quantity' => 2],
    ];
    $payments = [
        ['method' => 'cash', 'amount' => 2000],
    ];

    // Case 1: Valid prescription dispensing succeeds
    $sale = $action->execute(
        user: $pharmacist,
        items: $items,
        payments: $payments,
        idempotencyKey: (string) Str::uuid(),
        prescriptionId: $rx->id
    );

    expect($sale)->not->toBeNull()
        ->and($sale->prescription_id)->toBe($rx->id);

    // Case 2: Prescription not verified / draft throws PrescriptionNotDispensableException
    $draftRx = $register->execute(
        user: $pharmacist,
        prescriberName: 'Dr. Kebede',
        prescriberRegNo: 'DOC-123',
        prescriptionDate: now()->toDateString(),
        patientData: ['name' => 'Almaz', 'phone' => '+251911000000'],
        lines: [
            ['product_id' => $product->id, 'dose' => '500mg tid', 'quantity' => 5],
        ],
    );

    expect(fn () => $action->execute(
        user: $pharmacist,
        items: $items,
        payments: $payments,
        idempotencyKey: (string) Str::uuid(),
        prescriptionId: $draftRx->id
    ))->toThrow(PrescriptionNotDispensableException::class);

    // Case 3: Expired prescription throws PrescriptionNotDispensableException
    $expiredRx = $register->execute(
        user: $pharmacist,
        prescriberName: 'Dr. Kebede',
        prescriberRegNo: 'DOC-123',
        prescriptionDate: now()->subDays(5)->toDateString(),
        patientData: ['name' => 'Chala', 'phone' => '+251911111111'],
        lines: [
            ['product_id' => $product->id, 'dose' => '500mg tid', 'quantity' => 5],
        ],
    );
    app(VerifyPrescription::class)->execute(user: $pharmacist, prescription: $expiredRx);
    Carbon::setTestNow(now()->addDays(40)); // Beyond 30 days validity

    expect(fn () => $action->execute(
        user: $pharmacist,
        items: $items,
        payments: $payments,
        idempotencyKey: (string) Str::uuid(),
        prescriptionId: $expiredRx->id
    ))->toThrow(PrescriptionNotDispensableException::class);

    // Case 4: Quantity exceeds remaining prescribed quantity throws PrescriptionNotDispensableException
    $excessItems = [
        ['product_id' => $product->id, 'quantity' => 10], // prescribed is 5, remaining is 3
    ];
    $excessPayments = [
        ['method' => 'cash', 'amount' => 10000],
    ];

    expect(fn () => $action->execute(
        user: $pharmacist,
        items: $excessItems,
        payments: $excessPayments,
        idempotencyKey: (string) Str::uuid(),
        prescriptionId: $rx->id
    ))->toThrow(PrescriptionNotDispensableException::class);

    // Case 5: Product not on prescription throws PrescriptionNotDispensableException
    $otherProduct = Product::create([
        'sku' => 'IBU-RX-01',
        'brand' => 'Ibuprofen 400mg',
        'generic_name' => 'Ibuprofen',
        'dosage_form' => 'tablet',
        'strength' => '400mg',
        'classification' => 'otc',
        'unit' => 'tablet',
        'is_active' => true,
        'loose_allowed' => true,
    ]);
    $otherBatch = Batch::create([
        'product_id' => $otherProduct->id,
        'batch_no' => 'IBU-B1',
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 200,
    ]);
    BatchStock::create([
        'batch_id' => $otherBatch->id,
        'quantity' => 50,
        'status' => 'sellable',
    ]);
    app(SetSellingPrice::class)->execute(
        product: $otherProduct,
        price: 500,
        effectiveFrom: now()->subMinute(),
        reason: 'Price set for testing',
        user: pm()
    );

    $unlistedItems = [
        ['product_id' => $otherProduct->id, 'quantity' => 1],
    ];
    $unlistedPayments = [
        ['method' => 'cash', 'amount' => 500],
    ];

    expect(fn () => $action->execute(
        user: $pharmacist,
        items: $unlistedItems,
        payments: $unlistedPayments,
        idempotencyKey: (string) Str::uuid(),
        prescriptionId: $rx->id
    ))->toThrow(PrescriptionNotDispensableException::class);
});

test('Login hardening: check password before revealing state, generic message to user, timed lockout', function () {
    $login = app(LogIn::class);

    $user = ps([
        'username' => 'locked_user',
        'password' => 'CorrectPassword123!',
        'locked_at' => now()->subMinutes(5), // locked 5 mins ago
        'failed_login_attempts' => 4, // 4 failed attempts prior
    ]);

    // 1. Wrong password on locked user: throws generic auth.failed error and audits bad_password
    try {
        $login->execute('locked_user', 'WrongPassword!');
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors()['username'][0])->toBe(__('auth.failed'));
    }

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
    ]);

    // 2. Right password on locked user (within lockout window of 15 min): throws generic auth.failed error and audits locked
    try {
        $login->execute('locked_user', 'CorrectPassword123!');
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors()['username'][0])->toBe(__('auth.failed'));
    }

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'auth.login.failed',
        'user_id' => $user->id,
    ]);

    // 3. Timed unlock: travel past 15 minutes
    Carbon::setTestNow(now()->addMinutes(16));

    $result = $login->execute('locked_user', 'CorrectPassword123!');

    expect($result['user']->id)->toBe($user->id)
        ->and($user->fresh()->locked_at)->toBeNull()
        ->and($user->fresh()->failed_login_attempts)->toBe(0);

    // 4. Deactivated user: wrong password gives generic auth.failed; right password gives generic auth.failed
    $deactivatedUser = ps([
        'username' => 'deactivated_user',
        'password' => 'CorrectPassword123!',
        'is_active' => false,
    ]);

    try {
        $login->execute('deactivated_user', 'WrongPassword!');
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors()['username'][0])->toBe(__('auth.failed'));
    }

    try {
        $login->execute('deactivated_user', 'CorrectPassword123!');
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors()['username'][0])->toBe(__('auth.failed'));
    }

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'auth.login.failed',
        'user_id' => $deactivatedUser->id,
    ]);
});
