<?php

use App\Actions\Prescriptions\CancelAndReRegister;
use App\Actions\Prescriptions\RegisterPrescription;
use App\Livewire\Prescriptions\Index as PrescriptionIndex;
use App\Livewire\Prescriptions\Register as PrescriptionRegister;
use App\Livewire\Prescriptions\Show as PrescriptionShow;
use App\Models\AuditLog;
use App\Models\Prescription;
use App\Models\Product;
use App\Services\SettingsRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * UC-19: Register prescription
 * BRD: FR-4.2, 4.8, 4.11.
 */
beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('profile.name', 'Test Pharmacy');
    app(SettingsRepository::class)->put('prescriptions.validity_days', 30);
});

function createPrescriptionEligibleProduct(string $classification = 'rx'): Product
{
    static $seq = 1;
    $num = $seq++;

    return Product::create([
        'sku' => "RX-PROD-{$num}",
        'brand' => "Amoxicillin {$num}mg",
        'generic_name' => 'Amoxicillin',
        'strength' => "{$num}00mg",
        'dosage_form' => 'capsule',
        'classification' => $classification,
        'schedule' => $classification === 'controlled' ? 'schedule_2' : null,
        'storage_condition' => 'room_temperature',
        'is_active' => true,
    ]);
}

it('valid prescription registers and moves to Pending Verification (FR-4.2)', function () {
    $staff = ps();
    $product = createPrescriptionEligibleProduct('rx');

    $prescription = app(RegisterPrescription::class)->execute(
        user: $staff,
        prescriberName: 'Dr. John Doe',
        prescriberRegNo: 'MD-12345',
        prescriptionDate: now()->subDays(2)->toDateString(),
        patientData: [
            'name' => 'Jane Patient',
            'phone' => '+251911223344',
        ],
        lines: [
            [
                'product_id' => $product->id,
                'dose' => '500mg tid x 7d',
                'quantity' => 21,
            ],
        ],
    );

    expect($prescription)->toBeInstanceOf(Prescription::class)
        ->and($prescription->prescription_number)->toStartWith('RX-')
        ->and($prescription->status)->toBe(Prescription::STATUS_PENDING_VERIFICATION)
        ->and($prescription->customer->name)->toBe('Jane Patient')
        ->and($prescription->lines)->toHaveCount(1)
        ->and($prescription->lines->first()->quantity_prescribed)->toBe(21)
        ->and($prescription->lines->first()->dose)->toBe('500mg tid x 7d');

    // Status history complete
    $history = $prescription->statusHistory;
    expect($history)->toHaveCount(2)
        ->and($history[0]->to_status)->toBe(Prescription::STATUS_REGISTERED)
        ->and($history[1]->to_status)->toBe(Prescription::STATUS_PENDING_VERIFICATION);

    // Audit log
    expect(AuditLog::where('action', 'prescription.registered')->exists())->toBeTrue();
});

it('rejects prescription older than validity days (E1)', function () {
    $staff = ps();
    $product = createPrescriptionEligibleProduct('rx');

    app(SettingsRepository::class)->put('prescriptions.validity_days', 15);

    expect(function () use ($staff, $product) {
        app(RegisterPrescription::class)->execute(
            user: $staff,
            prescriberName: 'Dr. John Doe',
            prescriberRegNo: 'MD-12345',
            prescriptionDate: now()->subDays(20)->toDateString(),
            patientData: ['name' => 'Jane Patient', 'phone' => '+251911223344'],
            lines: [['product_id' => $product->id, 'dose' => '1 cap daily', 'quantity' => 10]],
        );
    })->toThrow(RuntimeException::class);
});

it('blocks registration when validity days is not configured (E1)', function () {
    $staff = ps();
    $product = createPrescriptionEligibleProduct('rx');

    // Set setting to null
    app(SettingsRepository::class)->put('prescriptions.validity_days', null);

    expect(function () use ($staff, $product) {
        app(RegisterPrescription::class)->execute(
            user: $staff,
            prescriberName: 'Dr. John Doe',
            prescriberRegNo: 'MD-12345',
            prescriptionDate: now()->toDateString(),
            patientData: ['name' => 'Jane Patient', 'phone' => '+251911223344'],
            lines: [['product_id' => $product->id, 'dose' => '1 cap daily', 'quantity' => 10]],
        );
    })->toThrow(RuntimeException::class, __('prescriptions.validity_days_not_configured'));
});

it('refuses registration when mandatory prescriber or line fields are missing (E2)', function () {
    $staff = ps();
    $product = createPrescriptionEligibleProduct('rx');

    // Missing prescriber name
    expect(function () use ($staff, $product) {
        app(RegisterPrescription::class)->execute(
            user: $staff,
            prescriberName: '',
            prescriberRegNo: 'MD-123',
            prescriptionDate: now()->toDateString(),
            patientData: ['name' => 'Patient', 'phone' => '+251911111111'],
            lines: [['product_id' => $product->id, 'dose' => '1 cap', 'quantity' => 5]],
        );
    })->toThrow(InvalidArgumentException::class, __('prescriptions.prescriber_name_required'));

    // Missing prescriber reg no
    expect(function () use ($staff, $product) {
        app(RegisterPrescription::class)->execute(
            user: $staff,
            prescriberName: 'Dr. Smith',
            prescriberRegNo: '',
            prescriptionDate: now()->toDateString(),
            patientData: ['name' => 'Patient', 'phone' => '+251911111111'],
            lines: [['product_id' => $product->id, 'dose' => '1 cap', 'quantity' => 5]],
        );
    })->toThrow(InvalidArgumentException::class, __('prescriptions.prescriber_reg_no_required'));

    // Empty lines
    expect(function () use ($staff) {
        app(RegisterPrescription::class)->execute(
            user: $staff,
            prescriberName: 'Dr. Smith',
            prescriberRegNo: 'MD-123',
            prescriptionDate: now()->toDateString(),
            patientData: ['name' => 'Patient', 'phone' => '+251911111111'],
            lines: [],
        );
    })->toThrow(InvalidArgumentException::class, __('prescriptions.at_least_one_line_required'));
});

it('only products with Rx or controlled classification can be added', function () {
    $staff = ps();

    // Create OTC product
    $otcProduct = Product::create([
        'sku' => 'OTC-PROD-01',
        'brand' => 'Paracetamol 500mg',
        'generic_name' => 'Paracetamol',
        'strength' => '500mg',
        'dosage_form' => 'tablet',
        'classification' => 'otc',
        'storage_condition' => 'room_temperature',
        'is_active' => true,
    ]);

    expect(function () use ($staff, $otcProduct) {
        app(RegisterPrescription::class)->execute(
            user: $staff,
            prescriberName: 'Dr. Smith',
            prescriberRegNo: 'MD-123',
            prescriptionDate: now()->toDateString(),
            patientData: ['name' => 'Patient', 'phone' => '+251911111111'],
            lines: [['product_id' => $otcProduct->id, 'dose' => '1 tab tid', 'quantity' => 10]],
        );
    })->toThrow(InvalidArgumentException::class);

    // Controlled product works fine
    $controlledProduct = createPrescriptionEligibleProduct('controlled');
    $prescription = app(RegisterPrescription::class)->execute(
        user: $staff,
        prescriberName: 'Dr. Smith',
        prescriberRegNo: 'MD-123',
        prescriptionDate: now()->toDateString(),
        patientData: ['name' => 'Patient', 'phone' => '+251911111111'],
        lines: [['product_id' => $controlledProduct->id, 'dose' => '1 tab bid', 'quantity' => 10]],
    );

    expect($prescription->lines->first()->product_id)->toBe($controlledProduct->id);
});

it('cancel and re-register keeps and links original (FR-4.11)', function () {
    $staff = ps();
    $product = createPrescriptionEligibleProduct('rx');

    $original = app(RegisterPrescription::class)->execute(
        user: $staff,
        prescriberName: 'Dr. John Doe',
        prescriberRegNo: 'MD-12345',
        prescriptionDate: now()->toDateString(),
        patientData: ['name' => 'Jane Patient', 'phone' => '+251911223344'],
        lines: [['product_id' => $product->id, 'dose' => '500mg tid x 7d', 'quantity' => 21]],
    );

    $originalNumber = $original->prescription_number;

    $replacement = app(CancelAndReRegister::class)->execute(
        user: $staff,
        original: $original,
        cancellationReason: 'Prescriber changed dosage from 500mg to 250mg',
        prescriberName: 'Dr. John Doe',
        prescriberRegNo: 'MD-12345',
        prescriptionDate: now()->toDateString(),
        patientData: ['customer_id' => $original->customer_id],
        lines: [['product_id' => $product->id, 'dose' => '250mg tid x 7d', 'quantity' => 21]],
    );

    $original->refresh();

    // Original is cancelled and links to replacement
    expect($original->status)->toBe(Prescription::STATUS_CANCELLED)
        ->and($original->prescription_number)->toBe($originalNumber)
        ->and($original->replaced_by_id)->toBe($replacement->id)
        ->and($original->cancellation_reason)->toBe('Prescriber changed dosage from 500mg to 250mg');

    // Replacement is active
    expect($replacement->status)->toBe(Prescription::STATUS_PENDING_VERIFICATION)
        ->and($replacement->prescription_number)->not->toBe($originalNumber);

    // Audit logged
    expect(AuditLog::where('action', 'prescription.cancelled_and_reregistered')->exists())->toBeTrue();
});

it('system admin cannot register prescriptions', function () {
    $admin = sa();
    $product = createPrescriptionEligibleProduct('rx');

    expect(function () use ($admin, $product) {
        app(RegisterPrescription::class)->execute(
            user: $admin,
            prescriberName: 'Dr. Smith',
            prescriberRegNo: 'MD-123',
            prescriptionDate: now()->toDateString(),
            patientData: ['name' => 'Patient', 'phone' => '+251911111111'],
            lines: [['product_id' => $product->id, 'dose' => '1 cap', 'quantity' => 5]],
        );
    })->toThrow(AuthorizationException::class);
});

it('Livewire Register component successfully registers prescription', function () {
    $staff = ps();
    $product = createPrescriptionEligibleProduct('rx');

    Livewire::actingAs($staff)
        ->test(PrescriptionRegister::class)
        ->set('prescriberName', 'Dr. Adams')
        ->set('prescriberRegNo', 'REG-999')
        ->set('prescriptionDate', now()->toDateString())
        ->set('patientName', 'Bob Tester')
        ->set('patientPhone', '+251922334455')
        ->call('addLine')
        ->set('lines.0.product_id', $product->id)
        ->set('lines.0.dose', '1 tablet daily')
        ->set('lines.0.quantity', 30)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Prescription::where('prescriber_registration_no', 'REG-999')->exists())->toBeTrue();
});

it('Livewire Index and Show components render properly', function () {
    $staff = ps();
    $product = createPrescriptionEligibleProduct('rx');

    $prescription = app(RegisterPrescription::class)->execute(
        user: $staff,
        prescriberName: 'Dr. Adams',
        prescriberRegNo: 'REG-999',
        prescriptionDate: now()->toDateString(),
        patientData: ['name' => 'Bob Tester', 'phone' => '+251922334455'],
        lines: [['product_id' => $product->id, 'dose' => '1 tablet daily', 'quantity' => 30]],
    );

    Livewire::actingAs($staff)
        ->test(PrescriptionIndex::class)
        ->assertStatus(200)
        ->assertSee($prescription->prescription_number);

    Livewire::actingAs($staff)
        ->test(PrescriptionShow::class, ['prescription' => $prescription])
        ->assertStatus(200)
        ->assertSee($prescription->prescription_number)
        ->assertSee('Bob Tester');
});
