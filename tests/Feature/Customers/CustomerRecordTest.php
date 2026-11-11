<?php

use App\Actions\Customers\RecordConsent;
use App\Actions\Customers\SaveCustomer;
use App\Actions\Customers\ViewHealthFields;
use App\Exceptions\ConsentRequiredException;
use App\Livewire\Customers\Form as CustomerForm;
use App\Livewire\Customers\Index as CustomerIndex;
use App\Livewire\Customers\Show as CustomerShow;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Customer;
use App\Models\CustomerHealthView;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Shift;
use App\Models\Till;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * UC-25: Manage customer record
 * BRD: FR-5.1, 5.2, 5.6; OQ-07. AC-26.
 */
beforeEach(function () {
    seedRoles();
});

it('refuses saving health fields without recorded customer consent (E1, AC-26)', function () {
    $staff = ps();

    expect(function () use ($staff) {
        app(SaveCustomer::class)->execute($staff, [
            'name' => 'Abebe Kebede',
            'phone' => '+251911223344',
            'consent_given' => false,
            'allergies' => 'Penicillin allergy',
            'chronic_conditions' => 'Hypertension',
        ]);
    })->toThrow(ConsentRequiredException::class, __('customers.consent_required_for_health_data'));
});

it('saves customer profile with health fields when consent is provided', function () {
    $staff = ps();

    $customer = app(SaveCustomer::class)->execute($staff, [
        'name' => 'Abebe Kebede',
        'phone' => '+251911223344',
        'address' => 'Bole Subcity, Woreda 03, Addis Ababa',
        'birth_date' => '1988-04-12',
        'consent_given' => true,
        'allergies' => 'Penicillin',
        'chronic_conditions' => 'Type 2 Diabetes',
    ]);

    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Abebe Kebede')
        ->and($customer->phone)->toBe('+251911223344')
        ->and($customer->consent_given)->toBeTrue()
        ->and($customer->consent_at)->not->toBeNull()
        ->and($customer->allergies)->toBe('Penicillin')
        ->and($customer->chronic_conditions)->toBe('Type 2 Diabetes');

    // Verify audit log
    expect(
        AuditLog::where('action', 'customer.created')
            ->where('auditable_id', (string) $customer->id)
            ->exists()
    )->toBeTrue();
});

it('encrypts allergies and chronic conditions at rest in the database', function () {
    $staff = ps();

    $customer = app(SaveCustomer::class)->execute($staff, [
        'name' => 'Almaz Wolde',
        'phone' => '+251922334455',
        'consent_given' => true,
        'allergies' => 'Sulfa Antibiotics',
        'chronic_conditions' => 'Asthma',
    ]);

    // Read directly from DB without Eloquent decryption
    $rawRow = DB::table('customers')->where('id', $customer->id)->first();

    expect($rawRow->allergies)->not->toBe('Sulfa Antibiotics')
        ->and($rawRow->chronic_conditions)->not->toBe('Asthma')
        ->and($rawRow->allergies)->not->toBeNull()
        ->and($rawRow->chronic_conditions)->not->toBeNull();

    // Eloquent decodes transparently
    $fresh = Customer::find($customer->id);
    expect($fresh->allergies)->toBe('Sulfa Antibiotics')
        ->and($fresh->chronic_conditions)->toBe('Asthma');
});

it('logs every authorized health fields view to customer_health_views and audit trail (E2, AC-26)', function () {
    $staff = ps();
    $manager = pm();

    $customer = app(SaveCustomer::class)->execute($staff, [
        'name' => 'Chala Tadesse',
        'phone' => '+251933445566',
        'consent_given' => true,
        'allergies' => 'Aspirin',
        'chronic_conditions' => 'Hypertension',
    ]);

    // Manager views health fields
    $result = app(ViewHealthFields::class)->execute($manager, $customer, '192.168.1.100');

    expect($result['allergies'])->toBe('Aspirin')
        ->and($result['chronic_conditions'])->toBe('Hypertension')
        ->and($result['consent_given'])->toBeTrue();

    // Verify customer_health_views table row
    $viewLog = CustomerHealthView::where('customer_id', $customer->id)
        ->where('user_id', $manager->id)
        ->first();

    expect($viewLog)->not->toBeNull()
        ->and($viewLog->ip_address)->toBe('192.168.1.100')
        ->and($viewLog->viewed_at)->not->toBeNull();

    // Verify immutable central audit trail
    expect(
        AuditLog::where('action', 'customer.health.viewed')
            ->where('auditable_id', (string) $customer->id)
            ->where('user_id', $manager->id)
            ->exists()
    )->toBeTrue();
});

it('strictly denies health fields access to System Admin and unauthorized users (AC-26, A1)', function () {
    $staff = ps();
    $admin = sa();

    $customer = app(SaveCustomer::class)->execute($staff, [
        'name' => 'Derartu Tulu',
        'phone' => '+251944556677',
        'consent_given' => true,
        'allergies' => 'Codeine',
        'chronic_conditions' => 'Cardiovascular Disease',
    ]);

    // Action check
    expect(fn () => app(ViewHealthFields::class)->execute($admin, $customer))
        ->toThrow(AuthorizationException::class);

    // Livewire direct access check
    Livewire::actingAs($admin)
        ->test(CustomerShow::class, ['customer' => $customer])
        ->assertForbidden();
});

it('warns about duplicate phone numbers during input', function () {
    $staff = ps();

    app(SaveCustomer::class)->execute($staff, [
        'name' => 'Existing Customer',
        'phone' => '+251911000111',
    ]);

    Livewire::actingAs($staff)
        ->test(CustomerForm::class)
        ->set('phone', '+251911000111')
        ->assertSee(__('customers.duplicate_phone_warning', ['name' => 'Existing Customer']));
});

it('records customer consent and revoking consent clears health data', function () {
    $staff = ps();

    $customer = app(SaveCustomer::class)->execute($staff, [
        'name' => 'Fatuma Roba',
        'phone' => '+251955667788',
        'consent_given' => true,
        'allergies' => 'Ibuprofen',
        'chronic_conditions' => 'Gastritis',
    ]);

    expect($customer->consent_given)->toBeTrue()
        ->and($customer->allergies)->toBe('Ibuprofen');

    // Revoke consent
    $revoked = app(RecordConsent::class)->execute($staff, $customer, false);

    expect($revoked->consent_given)->toBeFalse()
        ->and($revoked->consent_at)->toBeNull()
        ->and($revoked->allergies)->toBeNull()
        ->and($revoked->chronic_conditions)->toBeNull();

    // Verify audit log for revocation
    expect(
        AuditLog::where('action', 'customer.consent.revoked')
            ->where('auditable_id', (string) $customer->id)
            ->exists()
    )->toBeTrue();
});

it('displays accurate purchase history for the customer (FR-5.2)', function () {
    $staff = ps();
    $till = Till::firstOrCreate(['name' => 'Till 1'], ['is_active' => true]);
    $shift = Shift::create([
        'till_id' => $till->id,
        'user_id' => $staff->id,
        'opened_at' => now(),
        'opening_float' => 50000,
        'status' => 'open',
    ]);

    $customer = app(SaveCustomer::class)->execute($staff, [
        'name' => 'Haile Gebrselassie',
        'phone' => '+251966778899',
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

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'BATCH-CUST-001',
        'expiry_date' => Carbon::now()->addYear()->format('Y-m-d'),
        'unit_cost' => 1000,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => 100,
        'status' => 'sellable',
    ]);

    $sale = Sale::create([
        'receipt_no' => 'REC-CUST-202611-001',
        'shift_id' => $shift->id,
        'user_id' => $staff->id,
        'customer_id' => $customer->id,
        'status' => 'completed',
        'total' => 2500, // 25.00 ETB
        'idempotency_key' => 'idemp-cust-test-1',
        'completed_at' => now(),
    ]);

    SaleLine::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'quantity' => 10,
        'unit_price' => 250,
        'subtotal' => 2500,
    ]);

    Livewire::actingAs($staff)
        ->test(CustomerShow::class, ['customer' => $customer])
        ->set('activeTab', 'history')
        ->assertSee('REC-CUST-202611-001')
        ->assertSee('25.00 ETB')
        ->assertSee('Amoxicillin');
});

it('renders livewire index and search components correctly', function () {
    $staff = ps();

    app(SaveCustomer::class)->execute($staff, [
        'name' => 'Kenenisa Bekele',
        'phone' => '+251977889900',
    ]);

    Livewire::actingAs($staff)
        ->test(CustomerIndex::class)
        ->assertSee(__('customers.title'))
        ->assertSee('Kenenisa Bekele')
        ->set('search', 'NonExistent')
        ->assertDontSee('Kenenisa Bekele')
        ->assertSee(__('customers.no_customers_found'));
});
