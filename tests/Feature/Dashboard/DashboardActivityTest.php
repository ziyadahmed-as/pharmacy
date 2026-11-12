<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Prescriptions\RegisterPrescription;
use App\Actions\Shifts\OpenShift;
use App\Livewire\Dashboard;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Till;
use App\Services\SettingsRepository;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('profile.name', 'Test Pharmacy');
    app(SettingsRepository::class)->put('prescriptions.validity_days', 30);
});

it('renders dashboard for pharmacy staff without an open shift showing shift prompt', function () {
    $staff = ps();

    Livewire::actingAs($staff)
        ->test(Dashboard::class)
        ->assertStatus(200)
        ->assertSee('No Active Cash Shift')
        ->assertSee('Open Shift')
        ->assertDontSee('Today\'s Sales Revenue'); // Staff cannot see store-wide financials
});

it('renders dashboard for pharmacy staff with an active open shift showing till and POS shortcut', function () {
    $staff = ps();
    $till = Till::create(['name' => 'Front Till 1', 'is_active' => true]);
    app(OpenShift::class)->execute($staff, $till, 5000);

    Livewire::actingAs($staff)
        ->test(Dashboard::class)
        ->assertStatus(200)
        ->assertSee('Active Cash Shift: Front Till 1')
        ->assertSee('50.00 ETB')
        ->assertSee('Open Point of Sale')
        ->assertSee('Close Shift');
});

it('reflects prescription queue activity for licensed pharmacy staff', function () {
    $staff = ps();
    $product = Product::create([
        'sku' => 'RX-DASH-1',
        'brand' => 'Amoxil 500mg',
        'generic_name' => 'Amoxicillin',
        'strength' => '500mg',
        'dosage_form' => 'capsule',
        'classification' => 'rx',
        'storage_condition' => 'room_temperature',
        'is_active' => true,
    ]);
    app(SetSellingPrice::class)->execute($product, 1200, now()->subDay(), 'Initial price', unit: null, user: pm());

    // Register a prescription to create a pending verification item
    app(RegisterPrescription::class)->execute(
        user: $staff,
        prescriberName: 'Dr. Zewdu',
        prescriberRegNo: 'DOC-1122',
        prescriptionDate: now()->subDay()->toDateString(),
        patientData: ['name' => 'Tigist Assefa', 'phone' => '+251911445566'],
        lines: [['product_id' => $product->id, 'dose' => '1 cap tid', 'quantity' => 15]],
    );

    Livewire::actingAs($staff)
        ->test(Dashboard::class)
        ->assertStatus(200)
        ->assertSee('Pending Verification')
        ->assertSee('1 pending');
});

it('renders management sales totals and open shifts for Pharmacy Manager and Owner', function () {
    $manager = pm();
    $staff = ps();
    $till = Till::create(['name' => 'Main Till', 'is_active' => true]);
    $shift = app(OpenShift::class)->execute($staff, $till, 10000);

    // Create a completed sale on this shift
    Sale::create([
        'receipt_no' => 'REC-DASH-1001',
        'shift_id' => $shift->id,
        'user_id' => $staff->id,
        'status' => 'completed',
        'total' => 25000, // 250.00 ETB
        'idempotency_key' => (string) Str::uuid(),
        'completed_at' => now(),
    ]);

    Livewire::actingAs($manager)
        ->test(Dashboard::class)
        ->assertStatus(200)
        ->assertSee('Today\'s Sales Revenue')
        ->assertSee('250.00')
        ->assertSee('REC-DASH-1001')
        ->assertSee('Active Shifts');
});

it('strictly denies System Admin from viewing business data on the dashboard', function () {
    $admin = sa();
    $till = Till::create(['name' => 'Admin Till', 'is_active' => true]);
    $shift = app(OpenShift::class)->execute(ps(), $till, 10000);

    Sale::create([
        'receipt_no' => 'REC-SECRET-1',
        'shift_id' => $shift->id,
        'user_id' => ps()->id,
        'status' => 'completed',
        'total' => 50000,
        'idempotency_key' => (string) Str::uuid(),
        'completed_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertStatus(200)
        ->assertSee('Total Accounts')
        ->assertSee('Audit Events Today')
        ->assertDontSee('REC-SECRET-1')
        ->assertDontSee('Today\'s Sales Revenue')
        ->assertDontSee('Active Catalogue items')
        ->assertDontSee('Active Cash Shift');
});
