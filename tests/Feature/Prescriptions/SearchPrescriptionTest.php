<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Customers\RecordConsent;
use App\Actions\Customers\SaveCustomer;
use App\Actions\Prescriptions\DispensePrescriptionLine;
use App\Actions\Prescriptions\RegisterPrescription;
use App\Actions\Prescriptions\RejectPrescription;
use App\Actions\Prescriptions\VerifyPrescription;
use App\Actions\Shifts\OpenShift;
use App\Enums\Role;
use App\Livewire\Prescriptions\Search as PrescriptionSearchComponent;
use App\Livewire\Prescriptions\Show as PrescriptionShowComponent;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Customer;
use App\Models\CustomerHealthView;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\StaffLicence;
use App\Models\Till;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-23: Search prescription and patient history
 * BRD: FR-4.12, 5.6, 7.4. AC-26.
 */
beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('profile.name', 'Test Pharmacy');
    app(SettingsRepository::class)->put('prescriptions.validity_days', 30);
    app(SettingsRepository::class)->put('prescriptions.repeat_purchase_warning_days', 0);
});

function searchLicensedUser(): User
{
    $user = ps();
    $staff = makeStaff(['user_id' => $user->id]);

    StaffLicence::create([
        'staff_id' => $staff->id,
        'number' => 'SLIC-'.uniqid(),
        'issuing_body' => 'EFDA',
        'category' => 'Pharmacist',
        'issued_at' => now()->subMonths(6)->toDateString(),
        'expires_at' => now()->addYear()->toDateString(),
    ]);

    return $user->refresh();
}

function searchMakeRxProduct(string $brand = 'Amoxicillin 500mg', string $generic = 'Amoxicillin'): Product
{
    static $seq = 1;
    $num = $seq++;

    $product = Product::create([
        'sku' => "RX-SRCH-{$num}",
        'brand' => $brand,
        'generic_name' => $generic,
        'strength' => '500mg',
        'dosage_form' => 'capsule',
        'classification' => 'rx',
        'storage_condition' => 'room_temperature',
        'is_active' => true,
    ]);

    app(SetSellingPrice::class)->execute(
        product: $product,
        price: 1500,
        effectiveFrom: now()->subDays(5),
        reason: 'Initial price',
        unit: null,
        user: pm(),
    );

    return $product;
}

function createSearchPrescription(
    User $user,
    string $prescriberName = 'Dr. Search Prescriber',
    string $prescriberRegNo = 'DOC-SRCH-1',
    string $patientName = 'Abebe Bikila',
    ?string $patientPhone = null,
    ?string $date = null,
    ?Product $product = null,
    int $quantity = 10,
): Prescription {
    static $phoneSeq = 1000;
    $patientPhone ??= '+251911'.($phoneSeq++);
    $product ??= searchMakeRxProduct();

    return app(RegisterPrescription::class)->execute(
        user: $user,
        prescriberName: $prescriberName,
        prescriberRegNo: $prescriberRegNo,
        prescriptionDate: $date ?? now()->subDays(2)->toDateString(),
        patientData: [
            'name' => $patientName,
            'phone' => $patientPhone,
        ],
        lines: [
            [
                'product_id' => $product->id,
                'dose' => '1 cap tid',
                'quantity' => $quantity,
            ],
        ],
    );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('searches prescriptions by keyword (number, prescriber, patient)', function () {
    $staff = searchLicensedUser();
    $rx1 = createSearchPrescription($staff, prescriberName: 'Dr. Gregory House', patientName: 'Solomon Tadesse');
    $rx2 = createSearchPrescription($staff, prescriberName: 'Dr. John Watson', patientName: 'Almaz Ayana');

    Livewire::actingAs($staff)
        ->test(PrescriptionSearchComponent::class)
        ->set('search', 'Gregory')
        ->assertSee($rx1->prescription_number)
        ->assertDontSee($rx2->prescription_number)
        ->set('search', 'Almaz')
        ->assertSee($rx2->prescription_number)
        ->assertDontSee($rx1->prescription_number)
        ->set('search', $rx1->prescription_number)
        ->assertSee($rx1->prescription_number)
        ->assertDontSee($rx2->prescription_number);
});

it('filters prescriptions by patient name and phone', function () {
    $staff = searchLicensedUser();
    $rx1 = createSearchPrescription($staff, patientName: 'Derartu Tulu', patientPhone: '+251911001122');
    $rx2 = createSearchPrescription($staff, patientName: 'Kenenisa Bekele', patientPhone: '+251922334455');

    Livewire::actingAs($staff)
        ->test(PrescriptionSearchComponent::class)
        ->set('patient', 'Derartu')
        ->assertSee($rx1->prescription_number)
        ->assertDontSee($rx2->prescription_number)
        ->set('patient', '922334455')
        ->assertSee($rx2->prescription_number)
        ->assertDontSee($rx1->prescription_number);
});

it('filters prescriptions by prescriber name and reg no', function () {
    $staff = searchLicensedUser();
    $rx1 = createSearchPrescription($staff, prescriberName: 'Dr. Strange', prescriberRegNo: 'DOC-999');
    $rx2 = createSearchPrescription($staff, prescriberName: 'Dr. McCoy', prescriberRegNo: 'DOC-888');

    Livewire::actingAs($staff)
        ->test(PrescriptionSearchComponent::class)
        ->set('prescriber', 'Strange')
        ->assertSee($rx1->prescription_number)
        ->assertDontSee($rx2->prescription_number)
        ->set('prescriber', 'DOC-888')
        ->assertSee($rx2->prescription_number)
        ->assertDontSee($rx1->prescription_number);
});

it('filters prescriptions by drug brand and generic name', function () {
    $staff = searchLicensedUser();
    $prod1 = searchMakeRxProduct('Augmentin 625mg', 'Amoxicillin + Clavulanate');
    $prod2 = searchMakeRxProduct('Cipro 500mg', 'Ciprofloxacin');

    $rx1 = createSearchPrescription($staff, product: $prod1);
    $rx2 = createSearchPrescription($staff, product: $prod2);

    Livewire::actingAs($staff)
        ->test(PrescriptionSearchComponent::class)
        ->set('drug', 'Augmentin')
        ->assertSee($rx1->prescription_number)
        ->assertDontSee($rx2->prescription_number)
        ->set('drug', 'Ciprofloxacin')
        ->assertSee($rx2->prescription_number)
        ->assertDontSee($rx1->prescription_number);
});

it('filters prescriptions by date range', function () {
    $staff = searchLicensedUser();
    $rxOld = createSearchPrescription($staff, date: now()->subDays(10)->toDateString());
    $rxRecent = createSearchPrescription($staff, date: now()->subDays(2)->toDateString());

    Livewire::actingAs($staff)
        ->test(PrescriptionSearchComponent::class)
        ->set('dateFrom', now()->subDays(5)->toDateString())
        ->set('dateTo', now()->toDateString())
        ->assertSee($rxRecent->prescription_number)
        ->assertDontSee($rxOld->prescription_number);
});

it('filters prescriptions by status and suspect flag', function () {
    $staff = searchLicensedUser();
    $rxPending = createSearchPrescription($staff);

    $rxToVerify = createSearchPrescription($staff);
    $rxVerified = app(VerifyPrescription::class)->execute($staff, $rxToVerify);

    $rxToReject = createSearchPrescription($staff);
    $rxRejected = app(RejectPrescription::class)->execute($staff, $rxToReject, 'Forged doctor signature');

    // Filter by status
    Livewire::actingAs($staff)
        ->test(PrescriptionSearchComponent::class)
        ->set('status', Prescription::STATUS_VERIFIED)
        ->assertSee($rxVerified->prescription_number)
        ->assertDontSee($rxPending->prescription_number)
        ->assertDontSee($rxRejected->prescription_number);

    // Filter by suspect only
    Livewire::actingAs($staff)
        ->test(PrescriptionSearchComponent::class)
        ->set('suspectOnly', true)
        ->assertSee($rxRejected->prescription_number)
        ->assertSee('SUSPECT')
        ->assertDontSee($rxVerified->prescription_number)
        ->assertDontSee($rxPending->prescription_number);
});

it('audits opening a prescription with prescription.viewed (UC-23 STEP 3)', function () {
    $staff = searchLicensedUser();
    $rx = createSearchPrescription($staff);

    Livewire::actingAs($staff)
        ->test(PrescriptionShowComponent::class, ['prescription' => $rx])
        ->assertStatus(200);

    $audit = AuditLog::where('action', 'prescription.viewed')
        ->where('auditable_id', $rx->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($staff->id)
        ->and($audit->new_values['prescription_number'])->toBe($rx->prescription_number);
});

it('audits viewing health fields with customer.health.viewed and CustomerHealthView (AC-26, FR-5.6)', function () {
    $staff = searchLicensedUser();
    $rx = createSearchPrescription($staff);

    // Record consent and health details on customer
    $customer = $rx->customer;
    app(RecordConsent::class)->execute($staff, $customer, true);
    app(SaveCustomer::class)->execute($staff, [
        'name' => $customer->name,
        'phone' => $customer->phone,
        'allergies' => 'Severe penicillin allergy',
        'chronic_conditions' => 'Type 2 Diabetes',
    ], $customer);

    Livewire::actingAs($staff)
        ->test(PrescriptionShowComponent::class, ['prescription' => $rx])
        ->assertDontSee('Severe penicillin allergy') // not revealed yet
        ->call('viewHealthFields')
        ->assertSee('Severe penicillin allergy')
        ->assertSee('Type 2 Diabetes');

    // CustomerHealthView recorded
    $healthView = CustomerHealthView::where('customer_id', $customer->id)
        ->where('user_id', $staff->id)
        ->first();
    expect($healthView)->not->toBeNull();

    // AuditLog recorded
    $audit = AuditLog::where('action', 'customer.health.viewed')
        ->where('auditable_id', $customer->id)
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($staff->id);
});

it('denies System Admin from accessing prescription search, export, or show (AC-26, E1)', function () {
    $admin = sa();
    $staff = searchLicensedUser();
    $rx = createSearchPrescription($staff);

    // Direct HTTP route checks
    $this->actingAs($admin)->get('/prescriptions/search')->assertForbidden();
    $this->actingAs($admin)->get("/prescriptions/{$rx->id}")->assertForbidden();
    $this->actingAs($admin)->get('/prescriptions/export/csv')->assertForbidden();
    $this->actingAs($admin)->get('/prescriptions/export/pdf')->assertForbidden();

    // Livewire component checks
    Livewire::actingAs($admin)
        ->test(PrescriptionSearchComponent::class)
        ->assertForbidden();

    Livewire::actingAs($admin)
        ->test(PrescriptionShowComponent::class, ['prescription' => $rx])
        ->assertForbidden();
});

it('denies users without prescriptions.view permission from accessing search or show', function () {
    $user = User::factory()->create(); // no role / no permissions
    $staff = searchLicensedUser();
    $rx = createSearchPrescription($staff);

    $this->actingAs($user)->get('/prescriptions/search')->assertForbidden();
    $this->actingAs($user)->get("/prescriptions/{$rx->id}")->assertForbidden();

    Livewire::actingAs($user)
        ->test(PrescriptionSearchComponent::class)
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test(PrescriptionShowComponent::class, ['prescription' => $rx])
        ->assertForbidden();
});

it('allows PM and Owner to export prescription log in CSV and PDF, denying staff (UC-23 STEP 5)', function () {
    $staff = searchLicensedUser();
    $rx = createSearchPrescription($staff);

    $manager = pm();
    $owner = owner();

    // Staff is denied
    $this->actingAs($staff)->get('/prescriptions/export/csv')->assertForbidden();
    $this->actingAs($staff)->get('/prescriptions/export/pdf')->assertForbidden();

    // PM can export CSV
    $csvResponse = $this->actingAs($manager)->get('/prescriptions/export/csv');
    $csvResponse->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($csvResponse->streamedContent())->toContain($rx->prescription_number);

    // PM can export PDF
    $pdfResponse = $this->actingAs($manager)->get('/prescriptions/export/pdf');
    $pdfResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // Owner can export CSV
    $ownerCsv = $this->actingAs($owner)->get('/prescriptions/export/csv');
    $ownerCsv->assertOk();

    // Verify audit log for export
    $exportAudit = AuditLog::where('action', 'prescriptions.export')->get();
    expect($exportAudit->count())->toBeGreaterThanOrEqual(3);
});

it('displays status history and dispensing events on Show page', function () {
    $staff = searchLicensedUser();
    $product = searchMakeRxProduct();

    // Open shift and set up stock
    $till = Till::create(['name' => 'Search Till 1', 'is_active' => true]);
    app(OpenShift::class)->execute($staff, $till, 10000);

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'BATCH-SRCH-1',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'unit_cost' => 800,
    ]);
    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => 50,
        'reserved_quantity' => 0,
    ]);

    $rx = createSearchPrescription($staff, product: $product, quantity: 10);
    app(VerifyPrescription::class)->execute($staff, $rx);

    // Dispense line
    $line = $rx->lines->first();
    app(DispensePrescriptionLine::class)->execute(
        user: $staff,
        prescription: $rx,
        lineId: $line->id,
        quantity: 5,
        payments: [['method' => 'cash', 'amount' => 5 * 1500]],
        idempotencyKey: (string) Str::uuid(),
        batchId: $batch->id,
    );

    Livewire::actingAs($staff)
        ->test(PrescriptionShowComponent::class, ['prescription' => $rx])
        ->assertStatus(200)
        ->assertSee('Status History')
        ->assertSee('registered')
        ->assertSee('pending_verification')
        ->assertSee('verified')
        ->assertSee('partially_dispensed')
        ->assertSee('Dispensing History')
        ->assertSee('BATCH-SRCH-1');
});
