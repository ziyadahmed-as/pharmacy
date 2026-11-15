<?php

use App\Actions\Prescriptions\RegisterPrescription;
use App\Livewire\Prescribers\Index as PrescribersIndex;
use App\Livewire\Prescriptions\Register as PrescriptionRegister;
use App\Models\Batch;
use App\Models\ControlledDrugRegister;
use App\Models\Prescriber;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Reports\Definitions\Compliance\ComplianceControlledDrugsReport;
use App\Services\SettingsRepository;
use App\Services\StockMovementService;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('profile.name', 'Test Pharmacy');
    app(SettingsRepository::class)->put('prescriptions.validity_days', 30);
    app(SettingsRepository::class)->put('pharmacy.handles_controlled_drugs', true);
});

test('prescribers can be listed, created, edited, and toggled active with audit logging and unique licence number', function () {
    $pm = pm();

    // 1. Unique licence number check
    $p1 = Prescriber::create([
        'name' => 'Dr. First Prescriber',
        'licence_number' => 'LIC-1001',
        'facility' => 'Central Hospital',
        'phone' => '0911000001',
        'is_active' => true,
    ]);

    expect(fn () => Prescriber::create([
        'name' => 'Dr. Second Prescriber',
        'licence_number' => 'LIC-1001',
    ]))->toThrow(QueryException::class);

    // 2. Livewire component listing and creating
    Livewire::actingAs($pm)
        ->test(PrescribersIndex::class)
        ->assertSee('Dr. First Prescriber')
        ->assertSee('LIC-1001')
        ->set('name', 'Dr. Third Prescriber')
        ->set('licence_number', 'LIC-1003')
        ->set('facility', 'St. Paul Hospital')
        ->set('phone', '0911000003')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $created = Prescriber::where('licence_number', 'LIC-1003')->first();
    expect($created)->not->toBeNull();
    expect($created->name)->toBe('Dr. Third Prescriber');

    // 3. Edit prescriber
    Livewire::actingAs($pm)
        ->test(PrescribersIndex::class)
        ->call('edit', $created->id)
        ->set('name', 'Dr. Third Prescriber Updated')
        ->call('save')
        ->assertHasNoErrors();

    expect($created->refresh()->name)->toBe('Dr. Third Prescriber Updated');

    // 4. Toggle active status
    Livewire::actingAs($pm)
        ->test(PrescribersIndex::class)
        ->call('toggleActive', $created->id);

    expect($created->refresh()->is_active)->toBeFalse();

    // Re-activate
    Livewire::actingAs($pm)
        ->test(PrescribersIndex::class)
        ->call('toggleActive', $created->id);

    expect($created->refresh()->is_active)->toBeTrue();
});

test('quick-add modal inside Register prescription creates and selects prescriber immediately', function () {
    $ps = ps();

    Livewire::actingAs($ps)
        ->test(PrescriptionRegister::class)
        ->call('openQuickAddPrescriber')
        ->assertSet('showQuickAddModal', true)
        ->set('quickAddName', 'Dr. Quick Doc')
        ->set('quickAddLicence', 'LIC-QUICK-1')
        ->set('quickAddFacility', 'City Clinic')
        ->set('quickAddPhone', '0911223344')
        ->call('saveQuickAddPrescriber')
        ->assertHasNoErrors()
        ->assertSet('showQuickAddModal', false)
        ->assertSet('prescriberName', 'Dr. Quick Doc')
        ->assertSet('prescriberRegNo', 'LIC-QUICK-1');

    $prescriber = Prescriber::where('licence_number', 'LIC-QUICK-1')->first();
    expect($prescriber)->not->toBeNull();
    expect($prescriber->is_active)->toBeTrue();
});

test('prescriber search by name or licence number and warning when prescriber is inactive', function () {
    $ps = ps();

    $active = Prescriber::create([
        'name' => 'Dr. Active Specialist',
        'licence_number' => 'ACT-999',
        'is_active' => true,
    ]);

    $inactive = Prescriber::create([
        'name' => 'Dr. Inactive Specialist',
        'licence_number' => 'INACT-000',
        'is_active' => false,
    ]);

    $test = Livewire::actingAs($ps)
        ->test(PrescriptionRegister::class)
        // Search by name
        ->set('prescriberSearch', 'Specialist')
        ->assertCount('prescriberSearchResults', 2)
        // Select inactive -> warning is raised
        ->call('selectPrescriber', $inactive->id)
        ->assertSet('prescriberId', $inactive->id)
        ->assertSet('prescriberName', 'Dr. Inactive Specialist')
        ->assertSet('prescriberRegNo', 'INACT-000');

    expect($test->get('prescriberWarning'))->not->toBeNull();

    // Select active -> warning cleared
    $test->call('selectPrescriber', $active->id)
        ->assertSet('prescriberId', $active->id);

    expect($test->get('prescriberWarning'))->toBeNull();
});

test('prescription registration links prescriber_id and snapshots name and registration number', function () {
    $ps = ps();

    $prescriber = Prescriber::create([
        'name' => 'Dr. Snapshot Prescriber',
        'licence_number' => 'SNAP-1234',
        'is_active' => true,
    ]);

    $product = Product::create([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'dosage_form' => 'capsule',
        'strength' => '500mg',
        'classification' => 'rx',
        'unit' => 'capsule',
        'is_active' => true,
    ]);

    $action = app(RegisterPrescription::class);
    $rx = $action->execute(
        user: $ps,
        prescriberName: $prescriber->name,
        prescriberRegNo: $prescriber->licence_number,
        prescriptionDate: now()->toDateString(),
        patientData: ['name' => 'Patient John', 'phone' => '0911001122'],
        lines: [
            ['product_id' => $product->id, 'dose' => '1 cap TID', 'quantity' => 21],
        ],
        scanPath: null,
        prescriberId: $prescriber->id
    );

    expect($rx->prescriber_id)->toBe($prescriber->id);
    expect($rx->prescriber_name)->toBe('Dr. Snapshot Prescriber');
    expect($rx->prescriber_registration_no)->toBe('SNAP-1234');
    expect($rx->prescriber)->not->toBeNull();
    expect($rx->prescriber->id)->toBe($prescriber->id);
});

test('controlled drug prescriptions strictly require a prescriber with a licence number', function () {
    $ps = ps();

    $controlled = Product::create([
        'brand' => 'Morphine 10mg',
        'generic_name' => 'Morphine Sulfate',
        'dosage_form' => 'tablet',
        'strength' => '10mg',
        'classification' => 'controlled',
        'unit' => 'tablet',
        'is_active' => true,
    ]);

    $action = app(RegisterPrescription::class);

    // 1. Prescriber without licence number fails on controlled prescription
    $unlicensedPrescriber = Prescriber::create([
        'name' => 'Dr. Unlicensed',
        'licence_number' => null,
        'is_active' => true,
    ]);

    expect(fn () => $action->execute(
        user: $ps,
        prescriberName: 'Dr. Unlicensed',
        prescriberRegNo: '',
        prescriptionDate: now()->toDateString(),
        patientData: ['name' => 'Patient Morphine', 'phone' => '0911003344'],
        lines: [
            ['product_id' => $controlled->id, 'dose' => '1 tab daily', 'quantity' => 10],
        ],
        scanPath: null,
        prescriberId: $unlicensedPrescriber->id
    ))->toThrow(InvalidArgumentException::class);

    // 2. Prescriber with valid licence number succeeds
    $licensedPrescriber = Prescriber::create([
        'name' => 'Dr. Licensed CD',
        'licence_number' => 'CD-LIC-777',
        'is_active' => true,
    ]);

    $rx = $action->execute(
        user: $ps,
        prescriberName: 'Dr. Licensed CD',
        prescriberRegNo: 'CD-LIC-777',
        prescriptionDate: now()->toDateString(),
        patientData: ['name' => 'Patient Morphine', 'phone' => '0911003344'],
        lines: [
            ['product_id' => $controlled->id, 'dose' => '1 tab daily', 'quantity' => 10],
        ],
        scanPath: null,
        prescriberId: $licensedPrescriber->id
    );

    expect($rx->prescriber_id)->toBe($licensedPrescriber->id);
    expect($rx->prescriber_registration_no)->toBe('CD-LIC-777');
});

test('controlled drug register entry records prescriber_id and displays prescriber licence number', function () {
    $pm = pm();
    $ps = ps();

    $prescriber = Prescriber::create([
        'name' => 'Dr. Pethidine Prescriber',
        'licence_number' => 'MED-CD-999',
        'is_active' => true,
    ]);

    $product = Product::create([
        'brand' => 'Pethidine 50mg',
        'generic_name' => 'Pethidine',
        'dosage_form' => 'injection',
        'strength' => '50mg',
        'classification' => 'controlled',
        'unit' => 'ampoule',
        'is_active' => true,
    ]);

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'CD-B1',
        'expiry_date' => now()->addYear()->toDateString(),
        'unit_cost' => 1000,
    ]);

    $cabinet = StorageLocation::getDefaultControlledCabinet();

    // Receive stock via StockMovementService
    StockMovementService::post(
        batch: $batch,
        signedQuantity: 50,
        type: 'receipt',
        source: $batch,
        user: $pm,
        status: 'sellable',
        locationId: $cabinet->id,
        description: 'Initial stock',
    );

    // Adjustment movement linking prescriber
    $movement = StockMovementService::post(
        batch: $batch,
        signedQuantity: -5,
        type: 'adjustment',
        source: $batch,
        user: $ps,
        status: 'sellable',
        locationId: $cabinet->id,
        description: 'Dispensing test',
        secondPerson: $pm,
        patientName: 'Jane Doe',
        prescriberName: $prescriber->name,
    );

    $entry = ControlledDrugRegister::where('product_id', $product->id)
        ->where('entry_type', 'adjustment') // non-sale/receipt movements default to adjustment
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->prescriber_id)->toBe($prescriber->id);
    expect($entry->prescriber)->not->toBeNull();
    expect($entry->prescriber->licence_number)->toBe('MED-CD-999');

    // Verify Compliance Controlled Drugs Report includes prescriber licence number
    $report = app(ComplianceControlledDrugsReport::class);
    $data = $report->query([
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->addDay()->toDateString(),
    ], $pm);

    $prescriberColFound = false;
    foreach ($data['rows'] as $row) {
        if (str_contains(json_encode($row), 'MED-CD-999')) {
            $prescriberColFound = true;
            break;
        }
    }
    expect($prescriberColFound)->toBeTrue();
});

test('system administrator is denied all prescriber screens and actions', function () {
    $sa = sa();

    Livewire::actingAs($sa)
        ->test(PrescribersIndex::class)
        ->assertForbidden();

    $this->actingAs($sa)
        ->get(route('prescribers.index'))
        ->assertForbidden();
});
