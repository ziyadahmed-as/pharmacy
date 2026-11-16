<?php

use App\Enums\Role as RoleEnum;
use App\Livewire\GlobalSearch;
use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\Till;
use App\Support\Navigation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RolePermissionSeeder::class)->run();
});

// ─── STEP 1: Job-based Navigation Menu Groups & Badges ────────────────────────

it('groups navigation by job and respects permissions per role', function () {
    $ownerUser = owner();
    $pmUser = pm();
    $staffUser = ps();
    $adminUser = sa();

    // 1. Owner sees Sell, Stock, Customers & Prescriptions, Money, Staff, Reports, Settings
    actAs($ownerUser, RoleEnum::Owner);
    $ownerNav = Navigation::forUser($ownerUser);
    $ownerKeys = collect($ownerNav)->pluck('key')->all();
    expect($ownerKeys)->toContain('sell', 'stock', 'customers_prescriptions', 'money', 'staff', 'reports', 'settings');

    // 2. Pharmacy Staff sees Sell, Stock, Customers & Prescriptions, Staff, but NOT technical Settings or admin features
    actAs($staffUser, RoleEnum::PharmacyStaff);
    $staffNav = Navigation::forUser($staffUser);
    $staffKeys = collect($staffNav)->pluck('key')->all();
    expect($staffKeys)->toContain('sell', 'stock');

    // Staff does not see technical settings/audit/users
    $staffRoutes = collect($staffNav)->pluck('items')->flatten(1)->pluck('route')->all();
    expect($staffRoutes)->not->toContain('users.index', 'audit.index', 'settings.index', 'admin.backups');

    // 3. System Admin sees technical settings and audit, but NOT clinical/financial business modules (BR-06)
    actAs($adminUser, RoleEnum::SystemAdmin);
    $saNav = Navigation::forUser($adminUser);
    $saRoutes = collect($saNav)->pluck('items')->flatten(1)->pluck('route')->all();
    expect($saRoutes)->toContain('users.index', 'audit.index', 'settings.index');
    expect($saRoutes)->not->toContain('pos.sale', 'catalogue.index', 'prescriptions.index', 'reports.index');
});

it('displays pending approvals badge on navigation when approvals are pending', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);

    // No pending approvals
    $response = test()->get('/dashboard');
    $response->assertOk();

    // Create a pending approval request
    ApprovalRequest::create([
        'type' => 'stock_adjustment',
        'summary' => 'Damaged vials write-off',
        'initiator_id' => $ownerUser->id,
        'eligible_roles' => ['Owner', 'PharmacyManager'],
        'status' => 'pending',
    ]);

    $responseWithBadge = test()->get('/dashboard');
    $responseWithBadge->assertOk();
    $responseWithBadge->assertSee('approvals');
});

// ─── STEP 2: Global Search & Permission Leakage Prevention ────────────────────

it('searches products by brand, generic name, and barcode for authorized users', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);

    Product::create([
        'brand' => 'Amoxicillin Trihydrate',
        'generic_name' => 'Amoxicillin',
        'barcode' => '8901234567890',
        'classification' => 'rx',
        'unit' => 'piece',
        'is_active' => true,
    ]);

    Livewire::actingAs($ownerUser)
        ->test(GlobalSearch::class)
        ->set('query', 'Amox')
        ->assertSee('Amoxicillin Trihydrate')
        ->assertSee('Amoxicillin')
        ->set('query', '8901234567890')
        ->assertSee('Amoxicillin Trihydrate');
});

it('searches customers, prescriptions, receipts, and staff for authorized users', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);

    $customer = Customer::create([
        'name' => 'Sara Alemayehu',
        'phone' => '+251922334455',
    ]);

    Prescription::create([
        'prescription_number' => 'RX-2026-9999',
        'prescriber_name' => 'Dr. Taye',
        'prescriber_registration_no' => 'DOC-99',
        'prescription_date' => now()->toDateString(),
        'customer_id' => $customer->id,
        'status' => 'verified',
        'created_by' => $ownerUser->id,
    ]);

    $till = Till::create(['name' => 'Till-Search', 'is_active' => true]);
    $shift = Shift::create([
        'till_id' => $till->id,
        'user_id' => $ownerUser->id,
        'opened_at' => now(),
        'opening_float' => 1000,
        'status' => 'open',
    ]);

    Sale::create([
        'receipt_no' => 'RCP-8888',
        'shift_id' => $shift->id,
        'user_id' => $ownerUser->id,
        'total' => 12500,
        'idempotency_key' => Str::uuid()->toString(),
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    Staff::create([
        'name' => 'Dawit Kebede',
        'title' => 'Senior Pharmacist',
        'phone' => '+251911223344',
        'status' => 'active',
        'hire_date' => now()->toDateString(),
    ]);

    // Search Customer
    Livewire::actingAs($ownerUser)
        ->test(GlobalSearch::class)
        ->set('query', 'Sara')
        ->assertSee('Sara Alemayehu')
        ->assertSee('RX-2026-9999');

    // Search Receipt
    Livewire::actingAs($ownerUser)
        ->test(GlobalSearch::class)
        ->set('query', 'RCP-8888')
        ->assertSee('RCP-8888');

    // Search Staff
    Livewire::actingAs($ownerUser)
        ->test(GlobalSearch::class)
        ->set('query', 'Dawit')
        ->assertSee('Dawit Kebede');
});

it('strictly enforces permission filtering in search results (No Leakage)', function () {
    $staffUser = ps();
    $adminUser = sa();

    Customer::create([
        'name' => 'Sensitive Patient',
        'phone' => '+251911998877',
    ]);

    Staff::create([
        'name' => 'Confidential Staff',
        'title' => 'Auditor',
        'phone' => '+251922001122',
        'status' => 'active',
        'hire_date' => now()->toDateString(),
    ]);

    Product::create([
        'brand' => 'Confidential Med',
        'generic_name' => 'Confidential INN',
        'classification' => 'rx',
        'unit' => 'piece',
        'is_active' => true,
    ]);

    // 1. Staff does NOT have staff.manage -> cannot find staff records via search
    actAs($staffUser, RoleEnum::PharmacyStaff);
    Livewire::actingAs($staffUser)
        ->test(GlobalSearch::class)
        ->set('query', 'Confidential')
        ->assertDontSee('Confidential Staff');

    // 2. System Admin is blocked from all business data (BR-06)
    actAs($adminUser, RoleEnum::SystemAdmin);
    Livewire::actingAs($adminUser)
        ->test(GlobalSearch::class)
        ->set('query', 'Sensitive Patient')
        ->assertDontSee('Sensitive Patient')
        ->set('query', 'Confidential Med')
        ->assertDontSee('Confidential Med');
});

// ─── STEP 3: Breadcrumbs & Back-to-List on Detail Pages ───────────────────────

it('renders breadcrumbs and back-to-list navigation on detail screens', function () {
    $ownerUser = owner();
    actAs($ownerUser, RoleEnum::Owner);

    $product = Product::create([
        'brand' => 'Metformin 500mg',
        'generic_name' => 'Metformin',
        'classification' => 'rx',
        'unit' => 'piece',
        'is_active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Abebe Customer',
        'phone' => '+251911000000',
    ]);

    // Product detail page
    test()->get(route('catalogue.show', $product))
        ->assertOk()
        ->assertSee(route('catalogue.index'))
        ->assertSee('Metformin 500mg');

    // Customer detail page
    test()->get(route('customers.show', $customer))
        ->assertOk()
        ->assertSee(route('customers.index'))
        ->assertSee('Abebe Customer');
});
