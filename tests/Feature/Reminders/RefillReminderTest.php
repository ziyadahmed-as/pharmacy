<?php

use App\Actions\Reminders\BuildRefillReminders;
use App\Actions\Reminders\SendRefillReminder;
use App\Livewire\Reminders\Index as RemindersIndex;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Prescription;
use App\Models\PrescriptionLine;
use App\Models\Product;
use App\Models\Reminder;
use App\Services\Messaging\LogMessageGateway;
use App\Services\Messaging\MessageGateway;
use App\Services\SettingsRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    LogMessageGateway::reset();
    app(SettingsRepository::class)->put('profile.name', 'Central Health Pharmacy');
});

function createReminderCustomer(array $attrs = []): Customer
{
    return Customer::create(array_merge([
        'name' => 'Sara Haile',
        'phone' => '+251911555555',
        'address' => 'Bole, Addis Ababa',
        'birth_date' => '1990-08-20',
        'consent_given' => true,
        'consent_at' => now(),
    ], $attrs));
}

function createReminderProduct(array $attrs = []): Product
{
    static $pCount = 1;
    $num = $pCount++;

    return Product::create(array_merge([
        'sku' => "RX-REM-{$num}",
        'brand' => "Metformin {$num}00mg",
        'generic_name' => 'Metformin Hydrochloride',
        'dosage_form' => 'tablet',
        'strength' => "{$num}00mg",
        'classification' => 'rx',
        'storage_condition' => 'room_temperature',
        'is_active' => true,
    ], $attrs));
}

function createReminderPrescription(Customer $customer, string $status = Prescription::STATUS_VERIFIED): Prescription
{
    return Prescription::create([
        'prescription_number' => 'RX-REM-'.uniqid(),
        'customer_id' => $customer->id,
        'prescriber_name' => 'Dr. Birhanu',
        'prescriber_registration_no' => 'DOC-REM-101',
        'prescription_date' => now()->subDays(10)->toDateString(),
        'status' => $status,
        'created_by' => pm()->id,
    ]);
}

function createReminderLine(
    Prescription $prescription,
    Product $product,
    ?string $nextRefillDate = null,
    int $remainingQty = 30,
    int $refillsAllowed = 3,
    int $refillsUsed = 1
): PrescriptionLine {
    return PrescriptionLine::create([
        'prescription_id' => $prescription->id,
        'product_id' => $product->id,
        'dose' => '500mg twice daily for diabetes',
        'quantity_prescribed' => 60,
        'quantity_dispensed' => 30,
        'remaining_quantity' => $remainingQty,
        'refills_allowed' => $refillsAllowed,
        'refills_used' => $refillsUsed,
        'next_refill_date' => $nextRefillDate ?? now()->toDateString(),
    ]);
}

it('only lists customers with recorded consent AND a phone number due for refill', function () {
    $product = createReminderProduct();

    // 1. Eligible customer: Consent=true, has phone, refill due today
    $eligibleCust = createReminderCustomer(['name' => 'Eligible Customer', 'phone' => '+251911111111', 'consent_given' => true]);
    $rx1 = createReminderPrescription($eligibleCust);
    $line1 = createReminderLine($rx1, $product, now()->toDateString());

    // 2. Ineligible: NO consent (consent_given = false)
    $noConsentCust = createReminderCustomer(['name' => 'No Consent Cust', 'phone' => '+251922222222', 'consent_given' => false]);
    $rx2 = createReminderPrescription($noConsentCust);
    createReminderLine($rx2, $product, now()->toDateString());

    // 3. Ineligible: Consent=true but phone is empty string
    $noPhoneCust = createReminderCustomer(['name' => 'No Phone Cust', 'phone' => '', 'consent_given' => true]);
    $rx3 = createReminderPrescription($noPhoneCust);
    createReminderLine($rx3, $product, now()->toDateString());

    // 4. Ineligible: Cancelled prescription
    $cancelledCust = createReminderCustomer(['name' => 'Cancelled Cust', 'phone' => '+251933333333', 'consent_given' => true]);
    $rx4 = createReminderPrescription($cancelledCust, Prescription::STATUS_CANCELLED);
    createReminderLine($rx4, $product, now()->toDateString());

    // 5. Ineligible: Fully dispensed with 0 refills remaining
    $fullyDoneCust = createReminderCustomer(['name' => 'Done Cust', 'phone' => '+251944444444', 'consent_given' => true]);
    $rx5 = createReminderPrescription($fullyDoneCust);
    createReminderLine($rx5, $product, now()->toDateString(), remainingQty: 0, refillsAllowed: 1, refillsUsed: 1);

    // 6. Ineligible: Refill date far in the future (> lookahead window)
    $futureCust = createReminderCustomer(['name' => 'Future Cust', 'phone' => '+251955555555', 'consent_given' => true]);
    $rx6 = createReminderPrescription($futureCust);
    createReminderLine($rx6, $product, now()->addDays(30)->toDateString());

    $builder = app(BuildRefillReminders::class);
    $reminders = $builder->execute(lookaheadDays: 3);

    expect($reminders)->toHaveCount(1)
        ->and($reminders->first()->customer_id)->toBe($eligibleCust->id)
        ->and($reminders->first()->prescription_line_id)->toBe($line1->id)
        ->and($reminders->first()->status)->toBe(Reminder::STATUS_QUEUED);

    // Running build again does not duplicate the queued reminder
    $remindersAgain = $builder->execute(lookaheadDays: 3);
    expect($remindersAgain)->toHaveCount(0);
});

it('ensures message text contains NO health or medication details for privacy', function () {
    $product = createReminderProduct([
        'brand' => 'Special Antiretroviral',
        'generic_name' => 'Dolutegravir',
    ]);
    $customer = createReminderCustomer(['name' => 'Kenenisa Bekele']);
    $rx = createReminderPrescription($customer);
    $line = createReminderLine($rx, $product, now()->toDateString());

    $builder = app(BuildRefillReminders::class);
    $reminders = $builder->execute();
    $reminder = $reminders->first();

    $text = $reminder->message_text;

    expect($text)->not->toBeNull()
        // Must contain generic greeting and customer name
        ->and($text)->toContain('Kenenisa Bekele')
        ->and($text)->toContain('Central Health Pharmacy')
        // Strict Privacy Requirement: NO health details
        ->and($text)->not->toContain('Special Antiretroviral')
        ->and($text)->not->toContain('Dolutegravir')
        ->and($text)->not->toContain('diabetes')
        ->and($text)->not->toContain('500mg');
});

it('records send event and audit trail when staff sends reminder', function () {
    $staff = ps();
    $customer = createReminderCustomer(['name' => 'Derartu Tulu', 'phone' => '+251911888888']);
    $product = createReminderProduct();
    $rx = createReminderPrescription($customer);
    $line = createReminderLine($rx, $product);

    $reminder = Reminder::create([
        'customer_id' => $customer->id,
        'prescription_line_id' => $line->id,
        'due_date' => now()->toDateString(),
        'channel' => 'sms',
        'status' => Reminder::STATUS_QUEUED,
        'message_text' => 'Dear Derartu, your refill is due.',
    ]);

    $action = app(SendRefillReminder::class);
    $updated = $action->execute($staff, $reminder);

    expect($updated->isSent())->toBeTrue()
        ->and($updated->sent_at)->not->toBeNull()
        ->and($updated->sent_by)->toBe($staff->id)
        ->and($updated->attempts)->toBe(1);

    // Verify MessageGateway logged the dispatch
    $sentMessages = LogMessageGateway::sentMessages();
    expect($sentMessages)->toHaveCount(1)
        ->and($sentMessages[0]['recipient'])->toBe('+251911888888')
        ->and($sentMessages[0]['message'])->toBe('Dear Derartu, your refill is due.');

    // Verify audit log
    $audit = AuditLog::where('action', 'reminder.sent')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->metadata['customer_id'])->toBe($customer->id)
        ->and($audit->metadata['recipient_phone'])->toBe('+251911888888');
});

it('flags failed messages for a call or retry upon gateway delivery failure (E1)', function () {
    $staff = ps();
    $customer = createReminderCustomer(['name' => 'Failing Phone Cust', 'phone' => '+251999999999']);
    $product = createReminderProduct();
    $rx = createReminderPrescription($customer);
    $line = createReminderLine($rx, $product);

    $reminder = Reminder::create([
        'customer_id' => $customer->id,
        'prescription_line_id' => $line->id,
        'due_date' => now()->toDateString(),
        'channel' => 'sms',
        'status' => Reminder::STATUS_QUEUED,
        'message_text' => 'Dear Customer, your refill is due.',
    ]);

    // Simulate carrier failure
    LogMessageGateway::fakeFailure(true, 'Carrier network timeout');

    $action = app(SendRefillReminder::class);
    $updated = $action->execute($staff, $reminder);

    expect($updated->isFailed())->toBeTrue()
        ->and($updated->error_message)->toContain('Carrier network timeout')
        ->and($updated->attempts)->toBe(1);

    // Verify failure audit log
    $audit = AuditLog::where('action', 'reminder.failed')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->metadata['error'])->toContain('Carrier network timeout');

    // Staff marks as called
    actAs($staff);
    Livewire::test(RemindersIndex::class)
        ->call('markAsCalled', $reminder->id)
        ->assertSee(__('reminders.marked_as_called_success', ['name' => $customer->name]));

    $reminder->refresh();
    expect($reminder->isCalled())->toBeTrue();

    $calledAudit = AuditLog::where('action', 'reminder.called')->latest('id')->first();
    expect($calledAudit)->not->toBeNull();
});

it('denies system administrator and unauthorized users from sending reminders', function () {
    $admin = sa();
    $customer = createReminderCustomer();
    $product = createReminderProduct();
    $rx = createReminderPrescription($customer);
    $line = createReminderLine($rx, $product);

    $reminder = Reminder::create([
        'customer_id' => $customer->id,
        'prescription_line_id' => $line->id,
        'due_date' => now()->toDateString(),
        'channel' => 'sms',
        'status' => Reminder::STATUS_QUEUED,
        'message_text' => 'Refill due notice',
    ]);

    $action = app(SendRefillReminder::class);

    // System Administrator denied
    expect(fn () => $action->execute($admin, $reminder))
        ->toThrow(AuthorizationException::class);

    // SA denied on Livewire component
    actAs($admin);
    Livewire::test(RemindersIndex::class)->assertForbidden();
});

it('executes pms:build-refill-reminders console command successfully', function () {
    $customer = createReminderCustomer(['phone' => '+251911444444']);
    $product = createReminderProduct();
    $rx = createReminderPrescription($customer);
    createReminderLine($rx, $product, now()->toDateString());

    $exitCode = Artisan::call('pms:build-refill-reminders', ['--lookahead' => 5]);

    expect($exitCode)->toBe(0);

    $reminders = Reminder::all();
    expect($reminders)->toHaveCount(1)
        ->and($reminders->first()->customer_id)->toBe($customer->id);
});

it('renders due, failed, and sent tabs and supports batch sending in Livewire component', function () {
    $staff = ps();
    actAs($staff);

    $customer1 = createReminderCustomer(['name' => 'Batch Cust One', 'phone' => '+251911000001']);
    $customer2 = createReminderCustomer(['name' => 'Batch Cust Two', 'phone' => '+251911000002']);
    $product = createReminderProduct();

    $rx1 = createReminderPrescription($customer1);
    $line1 = createReminderLine($rx1, $product, now()->toDateString());

    $rx2 = createReminderPrescription($customer2);
    $line2 = createReminderLine($rx2, $product, now()->toDateString());

    $rem1 = Reminder::create([
        'customer_id' => $customer1->id,
        'prescription_line_id' => $line1->id,
        'due_date' => now()->toDateString(),
        'channel' => 'sms',
        'status' => Reminder::STATUS_QUEUED,
        'message_text' => 'Reminder 1',
    ]);

    $rem2 = Reminder::create([
        'customer_id' => $customer2->id,
        'prescription_line_id' => $line2->id,
        'due_date' => now()->toDateString(),
        'channel' => 'sms',
        'status' => Reminder::STATUS_QUEUED,
        'message_text' => 'Reminder 2',
    ]);

    Livewire::test(RemindersIndex::class)
        ->assertSee('Batch Cust One')
        ->assertSee('Batch Cust Two')
        // Select all and send batch
        ->set('selectedReminders', [$rem1->id, $rem2->id])
        ->call('sendSelected')
        ->assertSee(__('reminders.batch_sent_summary', ['success' => 2, 'failed' => 0]));

    $rem1->refresh();
    $rem2->refresh();

    expect($rem1->isSent())->toBeTrue()
        ->and($rem2->isSent())->toBeTrue();

    // Switch to Sent tab and see them listed
    Livewire::test(RemindersIndex::class)
        ->call('setTab', 'sent')
        ->assertSee('Batch Cust One')
        ->assertSee('Batch Cust Two');
});
