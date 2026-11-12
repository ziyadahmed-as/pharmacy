<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Inventory\ConfirmStockReceipt;
use App\Actions\Inventory\SubmitStockReceipt;
use App\Actions\Prescriptions\DispenseControlledDrug;
use App\Actions\Prescriptions\RegisterPrescription;
use App\Actions\Prescriptions\VerifyPrescription;
use App\Actions\Shifts\OpenShift;
use App\Livewire\ControlledDrugs\Register;
use App\Livewire\Dispense\Show;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\ControlledDrugRegister;
use App\Models\DispensingEvent;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StaffLicence;
use App\Models\StockMovement;
use App\Models\Till;
use App\Models\User;
use App\Services\SettingsRepository;
use App\Services\StockMovementService;
use App\Support\Navigation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-22: Dispense controlled drug
 * BRD: FR-4.4, 4.3, 4.9; BR-03, BR-12. AC-13, AC-34.
 */
beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('profile.name', 'Test Pharmacy');
    app(SettingsRepository::class)->put('prescriptions.validity_days', 30);
    app(SettingsRepository::class)->put('prescriptions.repeat_purchase_warning_days', 0);
    app(SettingsRepository::class)->put('pharmacy.handles_controlled_drugs', true);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeCdLicensedUser(string $category = 'Pharmacist', ?string $expiresAt = null): User
{
    $user = ps();
    $staff = makeStaff(['user_id' => $user->id]);

    StaffLicence::create([
        'staff_id' => $staff->id,
        'number' => 'CD-LIC-'.uniqid(),
        'issuing_body' => 'EFDA',
        'category' => $category,
        'issued_at' => now()->subMonths(6)->toDateString(),
        'expires_at' => $expiresAt ?? now()->addYear()->toDateString(),
    ]);

    return $user->refresh();
}

function makeControlledProduct(string $suffix = ''): Product
{
    static $seq = 1;
    $n = $seq++;

    return Product::create([
        'sku' => "CD-PROD-{$n}{$suffix}",
        'brand' => "Morphine Sulfate {$n}0mg",
        'generic_name' => 'Morphine Sulfate',
        'strength' => "{$n}0mg",
        'dosage_form' => 'injection',
        'classification' => 'controlled',
        'storage_condition' => 'room_temperature',
        'is_active' => true,
    ]);
}

function makeCdBatchForProduct(Product $product, int $stock = 100): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'BT-CD-'.uniqid(),
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 1200,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $stock,
        'status' => 'sellable',
    ]);

    app(SetSellingPrice::class)->execute(
        product: $product,
        price: 2500,
        effectiveFrom: now()->subMinute(),
        reason: 'Controlled drug initial price',
        unit: null,
        user: pm(),
    );

    return $batch;
}

function makeCdVerifiedPrescription(User $actor, Product $product, int $qty = 10): Prescription
{
    $register = app(RegisterPrescription::class);
    $rx = $register->execute(
        user: $actor,
        prescriberName: 'Dr. Kebede',
        prescriberRegNo: 'DOC-CD-77',
        prescriptionDate: now()->subDays(1)->toDateString(),
        patientData: ['name' => 'Abebe Patient', 'phone' => '+251911223344'],
        lines: [
            ['product_id' => $product->id, 'dose' => '10mg IM as needed', 'quantity' => $qty],
        ],
    );

    app(VerifyPrescription::class)->execute(user: $actor, prescription: $rx);

    return $rx->refresh();
}

function openCdShiftForUser(User $user): Shift
{
    $till = Till::create(['name' => 'Till-CD-'.uniqid(), 'is_active' => true]);

    return app(OpenShift::class)->execute($user, $till->id, 0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('controlled dispensing creates a register entry and the running balance equals stock', function () {
    $user = makeCdLicensedUser();
    openCdShiftForUser($user);

    $product = makeControlledProduct();
    $batch = makeCdBatchForProduct($product, 50);

    $rx = makeCdVerifiedPrescription($user, $product, 10);
    $line = $rx->lines->first();

    $action = app(DispenseControlledDrug::class);
    $sale = $action->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 4,
        payments: [['method' => 'cash', 'amount' => 2500 * 4]],
        idempotencyKey: Str::uuid()->toString(),
        confirmedPatientName: 'Abebe Confirmed',
        confirmedPrescriberName: 'Dr. Kebede Confirmed',
    );

    expect($sale)->toBeInstanceOf(Sale::class);

    // Verify DispensingEvent
    $event = DispensingEvent::where('sale_id', $sale->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->quantity)->toBe(4);

    // Verify stock ledger
    $batchStock = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->first();
    expect($batchStock->quantity)->toBe(46);

    // Verify ControlledDrugRegister entry
    $entry = ControlledDrugRegister::where('sale_id', $sale->id)->first();
    expect($entry)->not->toBeNull()
        ->and($entry->product_id)->toBe($product->id)
        ->and($entry->batch_id)->toBe($batch->id)
        ->and($entry->entry_type)->toBe('dispense')
        ->and($entry->quantity)->toBe(4)
        ->and($entry->running_balance)->toBe(46)
        ->and($entry->running_balance)->toBe($batchStock->quantity)
        ->and($entry->patient_name)->toBe('Abebe Confirmed')
        ->and($entry->prescriber_name)->toBe('Dr. Kebede Confirmed')
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->second_person_id)->toBeNull();
});

it('refuses controlled dispensing when licence is expired (AC-13)', function () {
    $user = ps();
    $staff = makeStaff(['user_id' => $user->id]);
    StaffLicence::create([
        'staff_id' => $staff->id,
        'number' => 'EXPIRED-CD-'.uniqid(),
        'issuing_body' => 'EFDA',
        'category' => 'Pharmacist',
        'issued_at' => now()->subYear()->toDateString(),
        'expires_at' => now()->subDay()->toDateString(),
    ]);
    $user = $user->refresh();
    openCdShiftForUser($user);

    $product = makeControlledProduct();
    makeCdBatchForProduct($product, 20);

    $licensedRegistrar = makeCdLicensedUser();
    openCdShiftForUser($licensedRegistrar);
    $rx = makeCdVerifiedPrescription($licensedRegistrar, $product, 5);
    $line = $rx->lines->first();

    $action = app(DispenseControlledDrug::class);

    expect(fn () => $action->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 2,
        payments: [['method' => 'cash', 'amount' => 2500 * 2]],
        idempotencyKey: Str::uuid()->toString(),
    ))->toThrow(AuthorizationException::class);
});

it('refuses non-dispensing movement of a controlled drug without a second person (E2)', function () {
    $user = pm();
    $product = makeControlledProduct();
    $batch = makeCdBatchForProduct($product, 30);

    // 1. Without second person → throws RuntimeException
    expect(fn () => StockMovementService::post(
        batch: $batch,
        signedQuantity: -5,
        type: 'adjustment',
        source: $batch,
        user: $user,
        status: 'sellable',
        description: 'Inventory adjustment down'
    ))->toThrow(RuntimeException::class);

    // 2. With same user as second person → throws RuntimeException
    expect(fn () => StockMovementService::post(
        batch: $batch,
        signedQuantity: -5,
        type: 'adjustment',
        source: $batch,
        user: $user,
        status: 'sellable',
        description: 'Inventory adjustment down',
        secondPerson: $user
    ))->toThrow(RuntimeException::class);

    // 3. With a distinct second person → succeeds
    $witness = ps();
    $movement = StockMovementService::post(
        batch: $batch,
        signedQuantity: -5,
        type: 'adjustment',
        source: $batch,
        user: $user,
        status: 'sellable',
        description: 'Inventory adjustment down',
        secondPerson: $witness
    );

    expect($movement)->toBeInstanceOf(StockMovement::class)
        ->and($movement->balance_after)->toBe(25);

    $entry = ControlledDrugRegister::where('stock_movement_id', $movement->id)->first();
    expect($entry)->not->toBeNull()
        ->and($entry->entry_type)->toBe('adjustment')
        ->and($entry->quantity)->toBe(5)
        ->and($entry->running_balance)->toBe(25)
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->second_person_id)->toBe($witness->id);
});

it('receipt of controlled stock creates register entry (UC-04, STEP 5)', function () {
    $manager = pm();
    $staff = ps();
    $product = makeControlledProduct();

    // Create a receipt with controlled product line
    $receipt = app(SubmitStockReceipt::class)->execute(
        data: [
            'supplier_name' => 'National Pharma Distributor',
            'delivery_note_no' => 'DN-CD-1',
            'receipt_date' => now()->format('Y-m-d'),
            'idempotency_key' => 'IDEM-CD-'.uniqid(),
            'lines' => [
                [
                    'product_id' => $product->id,
                    'batch_no' => 'B-CD-REC-1',
                    'manufacturing_date' => now()->subMonth()->format('Y-m-d'),
                    'expiry_date' => now()->addYear()->format('Y-m-d'),
                    'quantity' => 40,
                    'unit_cost' => 1500,
                    'flag' => 'good',
                ],
            ],
        ],
        user: $staff
    );

    // Confirm receipt (UC-04)
    app(ConfirmStockReceipt::class)->execute($receipt, $manager);

    $batch = Batch::where('product_id', $product->id)->where('batch_no', 'B-CD-REC-1')->first();
    expect($batch)->not->toBeNull();

    $entry = ControlledDrugRegister::where('batch_id', $batch->id)->first();
    expect($entry)->not->toBeNull()
        ->and($entry->entry_type)->toBe('receipt')
        ->and($entry->quantity)->toBe(40)
        ->and($entry->running_balance)->toBe(40)
        ->and($entry->user_id)->toBe($manager->id);
});

it('enforces controlled drug register is append-only', function () {
    $user = makeCdLicensedUser();
    openCdShiftForUser($user);

    $product = makeControlledProduct();
    $batch = makeCdBatchForProduct($product, 20);

    $rx = makeCdVerifiedPrescription($user, $product, 5);
    $line = $rx->lines->first();

    app(DispenseControlledDrug::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 2,
        payments: [['method' => 'cash', 'amount' => 2500 * 2]],
        idempotencyKey: Str::uuid()->toString(),
    );

    $entry = ControlledDrugRegister::first();
    expect($entry)->not->toBeNull();

    // Eloquent update and delete blocked
    expect(fn () => $entry->update(['quantity' => 100]))->toThrow(RuntimeException::class);
    expect(fn () => $entry->delete())->toThrow(RuntimeException::class);

    // Database trigger blocks raw SQL UPDATE and DELETE
    expect(fn () => DB::table('controlled_drug_register')->where('id', $entry->id)->update(['quantity' => 100]))
        ->toThrow(QueryException::class);
    expect(fn () => DB::statement("DELETE FROM controlled_drug_register WHERE id = {$entry->id}"))
        ->toThrow(QueryException::class);
});

it('pharmacy_staff can dispense but not approve or see cost (AC-34)', function () {
    $staff = makeCdLicensedUser();
    openCdShiftForUser($staff);

    // Staff can dispense
    expect($staff->hasPermission('prescriptions.dispense'))->toBeTrue();

    // Staff cannot approve requests
    expect($staff->hasPermission('approvals.process'))->toBeFalse();

    // Staff cannot view or export cost
    expect($staff->hasPermission('cost.view'))->toBeFalse();
    expect($staff->hasPermission('controlled_drugs.export'))->toBeFalse();

    // ControlledDrugRegister table does not expose unit_cost
    $columns = Schema::getColumnListing('controlled_drug_register');
    expect($columns)->not->toContain('unit_cost')
        ->and($columns)->not->toContain('cost')
        ->and($columns)->not->toContain('price');
});

it('switch off hides the module (OQ-01)', function () {
    $manager = pm();

    // With module enabled: navigation includes register
    app(SettingsRepository::class)->put('pharmacy.handles_controlled_drugs', true);
    $nav = Navigation::forUser($manager);
    $rxGroup = collect($nav)->firstWhere('key', 'prescriptions');
    expect($rxGroup)->not->toBeNull();
    $hasCdLink = collect($rxGroup['items'])->contains(fn ($i) => $i['route'] === 'controlled-drugs.register');
    expect($hasCdLink)->toBeTrue();

    // Switch off: navigation hides register
    app(SettingsRepository::class)->put('pharmacy.handles_controlled_drugs', false);
    $navOff = Navigation::forUser($manager);
    $rxGroupOff = collect($navOff)->firstWhere('key', 'prescriptions');
    $hasCdLinkOff = $rxGroupOff ? collect($rxGroupOff['items'])->contains(fn ($i) => $i['route'] === 'controlled-drugs.register') : false;
    expect($hasCdLinkOff)->toBeFalse();

    // Livewire viewer aborts 404
    Livewire::actingAs($manager)
        ->test(Register::class)
        ->assertStatus(404);

    // Dispense action refuses when disabled
    $user = makeCdLicensedUser();
    openCdShiftForUser($user);
    $product = makeControlledProduct();
    makeCdBatchForProduct($product, 10);
    $rx = makeCdVerifiedPrescription($user, $product, 3);
    $line = $rx->lines->first();

    expect(fn () => app(DispenseControlledDrug::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 1,
        payments: [['method' => 'cash', 'amount' => 2500]],
        idempotencyKey: Str::uuid()->toString(),
    ))->toThrow(RuntimeException::class);
});

it('Livewire Register viewer and Dispense Show render controlled drug workflows', function () {
    $user = makeCdLicensedUser();
    openCdShiftForUser($user);

    $product = makeControlledProduct();
    makeCdBatchForProduct($product, 20);

    $rx = makeCdVerifiedPrescription($user, $product, 5);
    $line = $rx->lines->first();

    // 1. Livewire Dispense Show displays controlled drug confirmation panel
    Livewire::actingAs($user)
        ->test(Show::class, ['prescription' => $rx])
        ->assertSee(__('controlled_drugs.badge'))
        ->assertSee(__('controlled_drugs.confirmation_title'))
        ->set('quantity', 2)
        ->call('dispense')
        ->assertHasNoErrors()
        ->assertSee(__('prescriptions.dispensing_success', ['receipt_no' => '']));

    expect($line->refresh()->quantity_dispensed)->toBe(2);

    // 2. Livewire Register viewer displays the recorded dispensing entry
    $manager = pm();
    Livewire::actingAs($manager)
        ->test(Register::class)
        ->assertSee($product->brand)
        ->assertSee('Dispense')
        ->assertSee('18'); // running balance 20 - 2 = 18
});

it('exports controlled drug register as CSV and PDF for PM and Owner but denies staff', function () {
    $manager = pm();
    $staff = ps();

    $product = makeControlledProduct();
    $batch = makeCdBatchForProduct($product, 20);

    $user = makeCdLicensedUser();
    openCdShiftForUser($user);
    $rx = makeCdVerifiedPrescription($user, $product, 5);
    $line = $rx->lines->first();

    app(DispenseControlledDrug::class)->execute(
        user: $user,
        prescription: $rx,
        lineId: $line->id,
        quantity: 2,
        payments: [['method' => 'cash', 'amount' => 2500 * 2]],
        idempotencyKey: Str::uuid()->toString(),
    );

    // Manager can export CSV
    $csvResponse = $this->actingAs($manager)->get(route('controlled-drugs.export', ['format' => 'csv']));
    $csvResponse->assertStatus(200);
    $csvResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    // Manager can export PDF
    $pdfResponse = $this->actingAs($manager)->get(route('controlled-drugs.export', ['format' => 'pdf']));
    $pdfResponse->assertStatus(200);
    $pdfResponse->assertHeader('Content-Type', 'application/pdf');

    // Staff is denied export
    $staffResponse = $this->actingAs($staff)->get(route('controlled-drugs.export', ['format' => 'csv']));
    $staffResponse->assertStatus(403);
});
