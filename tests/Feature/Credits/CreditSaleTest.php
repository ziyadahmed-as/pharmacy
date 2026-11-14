<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Credits\CustomerStatement;
use App\Actions\Credits\ReceivablesAgeing;
use App\Actions\Credits\RecordCreditPayment;
use App\Actions\Credits\SetCreditLimit;
use App\Actions\Sales\CompleteSale;
use App\Actions\Sales\ProcessReturn;
use App\Actions\Shifts\OpenShift;
use App\Livewire\Credits\Ageing;
use App\Livewire\Credits\Index;
use App\Livewire\Credits\SetLimit;
use App\Livewire\Credits\Show;
use App\Livewire\Pos\Sale as PosSaleComponent;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\CreditAccount;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SaleReturnLine;
use App\Models\Shift;
use App\Models\Till;
use App\Services\SettingsRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(fn () => seedRoles());

function createTestCustomer(array $attrs = []): Customer
{
    return Customer::create(array_merge([
        'name' => 'Abebe Bikila',
        'phone' => '+251911000001',
        'address' => 'Addis Ababa',
        'birth_date' => '1985-05-15',
        'consent_given' => true,
        'consent_at' => now(),
    ], $attrs));
}

function createCreditOtcProduct(): Product
{
    return Product::create([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'dosage_form' => 'capsule',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'capsule',
        'is_active' => true,
        'loose_allowed' => true,
    ]);
}

function setupCreditBatch(Product $product, int $quantity = 100): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'CR-'.Str::random(5),
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 1000,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $quantity,
        'status' => 'sellable',
    ]);

    return $batch;
}

function setCreditProductPrice(Product $product, int $priceSantim = 5000): ProductPrice
{
    $manager = pm();

    return app(SetSellingPrice::class)->execute(
        product: $product,
        price: $priceSantim,
        effectiveFrom: now()->subMinute(),
        reason: 'Credit test pricing',
        user: $manager
    );
}

function openShiftForCreditUser($user): Shift
{
    $till = Till::firstOrCreate(['name' => 'Credit Till 1'], ['is_active' => true]);

    return app(OpenShift::class)->execute($user, $till, 10000);
}

function setCreditSetting(string $key, mixed $val): void
{
    app(SettingsRepository::class)->put($key, $val);
}

it('allows PM and owner to set credit limit with audit logging', function () {
    $manager = pm();
    $customer = createTestCustomer();

    $action = app(SetCreditLimit::class);
    $account = $action->execute(
        actor: $manager,
        customer: $customer,
        limitSantim: 100000, // 1000.00 ETB
        notes: 'Approved VIP credit account'
    );

    expect($account)->toBeInstanceOf(CreditAccount::class)
        ->and($account->customer_id)->toBe($customer->id)
        ->and($account->credit_limit)->toBe(100000)
        ->and($account->balance)->toBe(0)
        ->and($account->availableCredit())->toBe(100000);

    $audit = AuditLog::where('action', 'credit.limit.set')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->new_values['credit_limit'])->toBe(100000);

    // Update existing limit
    $updated = $action->execute(
        actor: $manager,
        customer: $customer,
        limitSantim: 150000
    );
    expect($updated->credit_limit)->toBe(150000);
});

it('denies staff from setting a credit limit', function () {
    $staff = ps();
    $customer = createTestCustomer();

    $action = app(SetCreditLimit::class);

    expect(fn () => $action->execute(
        actor: $staff,
        customer: $customer,
        limitSantim: 50000
    ))->toThrow(AuthorizationException::class);
});

it('charges credit sale to customer credit account and updates balance', function () {
    $staff = ps();
    openShiftForCreditUser($staff);
    $customer = createTestCustomer();
    $manager = pm();

    // Set credit limit: 500.00 ETB (50,000 santim)
    app(SetCreditLimit::class)->execute($manager, $customer, 50000);

    $product = createCreditOtcProduct();
    setupCreditBatch($product, 50);
    setCreditProductPrice($product, 10000); // 100.00 ETB per unit

    // Sale of 2 units = 200.00 ETB (20,000 santim)
    $sale = app(CompleteSale::class)->execute(
        user: $staff,
        items: [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
        payments: [
            ['method' => 'credit', 'amount' => 20000],
        ],
        idempotencyKey: (string) Str::uuid(),
        prescriptionId: null,
        patientName: null,
        prescriberName: null,
        customerId: $customer->id
    );

    expect($sale)->not->toBeNull()
        ->and($sale->total)->toBe(20000)
        ->and($sale->customer_id)->toBe($customer->id);

    $account = CreditAccount::where('customer_id', $customer->id)->first();
    expect($account->balance)->toBe(20000)
        ->and($account->availableCredit())->toBe(30000);

    // Credit payment record exists
    $charge = CreditPayment::where('credit_account_id', $account->id)->first();
    expect($charge)->not->toBeNull()
        ->and($charge->direction)->toBe('charge')
        ->and($charge->amount)->toBe(20000)
        ->and($charge->sale_id)->toBe($sale->id);
});

it('refuses credit sale when credit limit is exceeded (E1)', function () {
    $staff = ps();
    openShiftForCreditUser($staff);
    $customer = createTestCustomer();
    $manager = pm();

    // Limit is 100.00 ETB (10,000 santim)
    app(SetCreditLimit::class)->execute($manager, $customer, 10000);

    $product = createCreditOtcProduct();
    setupCreditBatch($product, 50);
    setCreditProductPrice($product, 15000); // 150.00 ETB

    // Attempt to buy for 150.00 ETB on credit with only 100.00 ETB limit
    expect(fn () => app(CompleteSale::class)->execute(
        user: $staff,
        items: [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        payments: [
            ['method' => 'credit', 'amount' => 15000],
        ],
        idempotencyKey: (string) Str::uuid(),
        customerId: $customer->id
    ))->toThrow(RuntimeException::class);

    $account = CreditAccount::where('customer_id', $customer->id)->first();
    expect($account->balance)->toBe(0);
});

it('refuses credit sale if customer does not have a credit account', function () {
    $staff = ps();
    openShiftForCreditUser($staff);
    $customer = createTestCustomer();

    $product = createCreditOtcProduct();
    setupCreditBatch($product, 50);
    setCreditProductPrice($product, 5000);

    expect(fn () => app(CompleteSale::class)->execute(
        user: $staff,
        items: [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        payments: [
            ['method' => 'credit', 'amount' => 5000],
        ],
        idempotencyKey: (string) Str::uuid(),
        customerId: $customer->id
    ))->toThrow(InvalidArgumentException::class);
});

it('records credit payment, reduces balance, and issues sequential receipt', function () {
    $manager = pm();
    $customer = createTestCustomer();
    $account = app(SetCreditLimit::class)->execute($manager, $customer, 50000);

    // Simulate existing balance of 30,000 santim (300.00 ETB)
    $account->update(['balance' => 30000]);

    $payment = app(RecordCreditPayment::class)->execute(
        actor: $manager,
        account: $account,
        amountSantim: 12000, // 120.00 ETB
        method: 'cash',
        reference: 'RCP-TX-001',
        notes: 'Partial payment received'
    );

    expect($payment)->toBeInstanceOf(CreditPayment::class)
        ->and($payment->direction)->toBe('payment')
        ->and($payment->amount)->toBe(12000)
        ->and($payment->method)->toBe('cash')
        ->and($payment->receipt_no)->toStartWith('CPR-');

    $account->refresh();
    expect($account->balance)->toBe(18000) // 30,000 - 12,000
        ->and($account->availableCredit())->toBe(32000);

    // Audit log
    $audit = AuditLog::where('action', 'credit.payment.recorded')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->new_values['balance'])->toBe(18000);
});

it('refuses credit payment exceeding outstanding balance', function () {
    $manager = pm();
    $customer = createTestCustomer();
    $account = app(SetCreditLimit::class)->execute($manager, $customer, 50000);
    $account->update(['balance' => 10000]);

    // Try to pay 15,000 when only 10,000 is owed
    expect(fn () => app(RecordCreditPayment::class)->execute(
        actor: $manager,
        account: $account,
        amountSantim: 15000,
        method: 'cash'
    ))->toThrow(RuntimeException::class);
});

it('denies staff from recording credit payment', function () {
    $staff = ps();
    $customer = createTestCustomer();
    $account = CreditAccount::create([
        'customer_id' => $customer->id,
        'credit_limit' => 50000,
        'balance' => 20000,
        'created_by' => $staff->id,
    ]);

    expect(fn () => app(RecordCreditPayment::class)->execute(
        actor: $staff,
        account: $account,
        amountSantim: 5000,
        method: 'cash'
    ))->toThrow(AuthorizationException::class);
});

it('calculates receivables ageing buckets correctly', function () {
    $manager = pm();

    // Account 1: 15 days old (Current: 0-30 days)
    $c1 = createTestCustomer(['name' => 'Cust Current', 'phone' => '+251911111111']);
    $a1 = app(SetCreditLimit::class)->execute($manager, $c1, 100000);
    $a1->update(['balance' => 10000]);
    CreditPayment::create([
        'credit_account_id' => $a1->id,
        'amount' => 10000,
        'direction' => 'charge',
        'user_id' => $manager->id,
        'created_at' => now()->subDays(15),
    ]);

    // Account 2: 45 days old (31-60 days)
    $c2 = createTestCustomer(['name' => 'Cust 45 Days', 'phone' => '+251922222222']);
    $a2 = app(SetCreditLimit::class)->execute($manager, $c2, 100000);
    $a2->update(['balance' => 25000]);
    CreditPayment::create([
        'credit_account_id' => $a2->id,
        'amount' => 25000,
        'direction' => 'charge',
        'user_id' => $manager->id,
        'created_at' => now()->subDays(45),
    ]);

    // Account 3: 75 days old (61-90 days)
    $c3 = createTestCustomer(['name' => 'Cust 75 Days', 'phone' => '+251933333333']);
    $a3 = app(SetCreditLimit::class)->execute($manager, $c3, 100000);
    $a3->update(['balance' => 30000]);
    CreditPayment::create([
        'credit_account_id' => $a3->id,
        'amount' => 30000,
        'direction' => 'charge',
        'user_id' => $manager->id,
        'created_at' => now()->subDays(75),
    ]);

    // Account 4: 120 days old (90+ days)
    $c4 = createTestCustomer(['name' => 'Cust 120 Days', 'phone' => '+251944444444']);
    $a4 = app(SetCreditLimit::class)->execute($manager, $c4, 100000);
    $a4->update(['balance' => 40000]);
    CreditPayment::create([
        'credit_account_id' => $a4->id,
        'amount' => 40000,
        'direction' => 'charge',
        'user_id' => $manager->id,
        'created_at' => now()->subDays(120),
    ]);

    $report = app(ReceivablesAgeing::class)->execute($manager);

    $row1 = $report->firstWhere('customer_name', 'Cust Current');
    $row2 = $report->firstWhere('customer_name', 'Cust 45 Days');
    $row3 = $report->firstWhere('customer_name', 'Cust 75 Days');
    $row4 = $report->firstWhere('customer_name', 'Cust 120 Days');

    expect($row1['bucket'])->toBe('current')
        ->and($row2['bucket'])->toBe('31_60')
        ->and($row3['bucket'])->toBe('61_90')
        ->and($row4['bucket'])->toBe('90_plus');
});

it('generates customer statement matching all transactions and balances', function () {
    $manager = pm();
    $customer = createTestCustomer();
    $account = app(SetCreditLimit::class)->execute($manager, $customer, 100000);

    // Charge 1: 5 days ago = 20,000
    CreditPayment::create([
        'credit_account_id' => $account->id,
        'amount' => 20000,
        'direction' => 'charge',
        'user_id' => $manager->id,
        'created_at' => now()->subDays(5),
    ]);

    // Payment 1: 3 days ago = 8,000
    CreditPayment::create([
        'credit_account_id' => $account->id,
        'amount' => 8000,
        'direction' => 'payment',
        'user_id' => $manager->id,
        'created_at' => now()->subDays(3),
    ]);

    // Charge 2: 1 day ago = 15,000
    CreditPayment::create([
        'credit_account_id' => $account->id,
        'amount' => 15000,
        'direction' => 'charge',
        'user_id' => $manager->id,
        'created_at' => now()->subDay(),
    ]);

    $account->update(['balance' => 27000]);

    // Query statement for last 4 days (opening should be 20,000 from Charge 1)
    $statement = app(CustomerStatement::class)->execute(
        actor: $manager,
        account: $account,
        from: now()->subDays(4)->startOfDay(),
        to: now()->endOfDay()
    );

    expect($statement['opening_balance'])->toBe(20000)
        ->and($statement['closing_balance'])->toBe(27000)
        ->and($statement['transactions'])->toHaveCount(2);
});

it('reduces credit balance when a customer return is processed on a credit sale', function () {
    $staff = ps();
    openShiftForCreditUser($staff);
    $customer = createTestCustomer();
    $manager = pm();

    app(SetCreditLimit::class)->execute($manager, $customer, 100000);

    $product = createCreditOtcProduct();
    setupCreditBatch($product, 50);
    setCreditProductPrice($product, 10000); // 100.00 ETB

    // Complete sale: 2 units = 200.00 ETB (20,000 santim) on credit
    $sale = app(CompleteSale::class)->execute(
        user: $staff,
        items: [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
        payments: [
            ['method' => 'credit', 'amount' => 20000],
        ],
        idempotencyKey: (string) Str::uuid(),
        customerId: $customer->id
    );

    $account = CreditAccount::where('customer_id', $customer->id)->first();
    expect($account->balance)->toBe(20000);

    // Return 1 unit (10,000 santim)
    $saleLine = $sale->lines->first();
    setCreditSetting('sales.refund_limit', 100000); // Set limit high so immediate completion

    $result = app(ProcessReturn::class)->execute(
        user: $staff,
        receiptNo: $sale->receipt_no,
        items: [
            [
                'sale_line_id' => $saleLine->id,
                'quantity' => 1,
                'assessment' => SaleReturnLine::ASSESSMENT_RESALEABLE,
                'reason' => 'Customer changed mind',
            ],
        ],
        generalReason: 'Return 1 unit'
    );

    expect($result['sale_return'])->not->toBeNull()
        ->and($result['sale_return']->status)->toBe('completed');

    $account->refresh();
    expect($account->balance)->toBe(10000); // 20,000 - 10,000

    // Check credit payment refund entry
    $refundPayment = CreditPayment::where('credit_account_id', $account->id)
        ->where('direction', 'payment')
        ->latest('id')
        ->first();
    expect($refundPayment)->not->toBeNull()
        ->and($refundPayment->amount)->toBe(10000)
        ->and($refundPayment->method)->toBe('credit');
});

it('renders credit panel in POS sale component when customer with credit is selected', function () {
    $staff = ps();
    openShiftForCreditUser($staff);
    $customer = createTestCustomer(['name' => 'POS Credit Customer', 'phone' => '+251911999999']);
    $manager = pm();

    app(SetCreditLimit::class)->execute($manager, $customer, 80000);

    actAs($staff);

    Livewire::test(PosSaleComponent::class)
        ->set('customerSearch', 'POS Credit')
        ->assertSee('POS Credit Customer')
        ->call('selectCustomer', $customer->id)
        ->assertSee('800.00') // credit limit
        ->assertSeeHtml('value="credit"'); // 'credit' is now in payment methods dropdown!
});

it('forbids staff and system admin from accessing credit screens', function () {
    $staff = ps();
    $admin = sa();
    $customer = createTestCustomer();
    $manager = pm();
    $account = app(SetCreditLimit::class)->execute($manager, $customer, 50000);

    // Staff
    actAs($staff);
    Livewire::test(Index::class)->assertForbidden();
    Livewire::test(Ageing::class)->assertForbidden();
    Livewire::test(Show::class, ['account' => $account])->assertForbidden();
    Livewire::test(SetLimit::class, ['customer' => $customer])->assertForbidden();

    // System Admin
    actAs($admin);
    Livewire::test(Index::class)->assertForbidden();
    Livewire::test(Ageing::class)->assertForbidden();
    Livewire::test(Show::class, ['account' => $account])->assertForbidden();
    Livewire::test(SetLimit::class, ['customer' => $customer])->assertForbidden();
});

it('allows PM to set credit limit via SetLimit Livewire component', function () {
    $manager = pm();
    $customer = createTestCustomer();
    actAs($manager);

    Livewire::test(SetLimit::class, ['customer' => $customer])
        ->set('limitEtb', '750.50')
        ->set('notes', 'Set via Livewire form')
        ->call('saveLimit')
        ->assertHasNoErrors()
        ->assertSee(__('credits.limit_saved_successfully'));

    $account = CreditAccount::where('customer_id', $customer->id)->first();
    expect($account)->not->toBeNull()
        ->and($account->credit_limit)->toBe(75050);
});

it('allows PM to collect payment via Show Livewire component', function () {
    $manager = pm();
    $customer = createTestCustomer();
    $account = app(SetCreditLimit::class)->execute($manager, $customer, 50000);
    $account->update(['balance' => 20000]);

    actAs($manager);

    Livewire::test(Show::class, ['account' => $account])
        ->set('paymentAmount', '100.00')
        ->set('paymentMethod', 'telebirr')
        ->set('paymentReference', 'TB-12345')
        ->call('recordPayment')
        ->assertHasNoErrors()
        ->assertSee(__('credits.payment_recorded_successfully'));

    $account->refresh();
    expect($account->balance)->toBe(10000);
});

it('renders receivables ageing screen for PM', function () {
    $manager = pm();
    $customer = createTestCustomer(['name' => 'Ageing Display Customer']);
    $account = app(SetCreditLimit::class)->execute($manager, $customer, 50000);
    $account->update(['balance' => 15000]);
    CreditPayment::create([
        'credit_account_id' => $account->id,
        'amount' => 15000,
        'direction' => 'charge',
        'user_id' => $manager->id,
        'created_at' => now()->subDays(10),
    ]);

    actAs($manager);

    Livewire::test(Ageing::class)
        ->assertOk()
        ->assertSee('Ageing Display Customer')
        ->assertSee('150.00 ETB');
});
