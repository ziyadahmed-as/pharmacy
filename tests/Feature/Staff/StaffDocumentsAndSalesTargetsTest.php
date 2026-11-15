<?php

use App\Actions\Sales\SetSalesTarget;
use App\Actions\Staff\CheckDocumentExpiry;
use App\Actions\Staff\DeleteStaffDocument;
use App\Actions\Staff\UploadStaffDocument;
use App\Livewire\Dashboard;
use App\Livewire\Owner\Tiles\SalesTargetTile;
use App\Livewire\Staff\Show as StaffShow;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesTarget;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\Till;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * UC-39 & UC-32: Staff documents with private storage and expiry reminders,
 * sales targets with progress bars on owner and staff views.
 *
 * @uc UC-39, UC-32
 *
 * @fr FR-10.3, FR-7.7, FR-9.1
 */
beforeEach(function () {
    seedRoles();
    Storage::fake('local');
});

// ─── Helpers ─────────────────────────────────────────────────────────────

function createTestStaff(?User $user = null): Staff
{
    return Staff::create([
        'user_id' => $user?->id,
        'name' => 'Dr. Jane Pharmacist',
        'title' => 'Chief Pharmacist',
        'phone' => '+251911223344',
        'address' => 'Bole, Addis Ababa',
        'hire_date' => now()->subYear()->toDateString(),
        'status' => Staff::ACTIVE,
    ]);
}

function createCompletedSaleForTarget(User $user, int $amountSantim, ?Carbon $date = null): Sale
{
    $shift = Shift::where('user_id', $user->id)->where('status', 'open')->first();
    if (! $shift) {
        $till = Till::create(['name' => 'Till-T-'.Str::random(4), 'is_active' => true]);
        $shift = Shift::create([
            'till_id' => $till->id,
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_float' => 10000,
            'status' => 'open',
        ]);
    }

    $product = Product::create([
        'brand' => 'Target Test Drug '.Str::random(4),
        'generic_name' => 'Test Drug',
        'dosage_form' => 'tablet',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'tablet',
        'is_active' => true,
        'loose_allowed' => true,
    ]);

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'BAT-'.Str::random(5),
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 100,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => 100,
        'status' => 'sellable',
    ]);

    $sale = Sale::create([
        'receipt_no' => 'REC-'.Str::random(8),
        'shift_id' => $shift->id,
        'user_id' => $user->id,
        'status' => 'completed',
        'total' => $amountSantim,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => $date ?: now(),
    ]);

    $sale->lines()->create([
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'quantity' => 1,
        'unit_price' => $amountSantim,
        'subtotal' => $amountSantim,
        'is_fefo_override' => false,
    ]);

    return $sale;
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 1 — Staff Documents: Upload, Private Storage, Security
// ═══════════════════════════════════════════════════════════════════════════

it('PM or Owner can upload a staff document to private storage', function () {
    $manager = pm();
    $staff = createTestStaff();
    $file = UploadedFile::fake()->create('contract.pdf', 250, 'application/pdf');

    $action = app(UploadStaffDocument::class);
    $document = $action->execute($manager, $staff, [
        'type' => StaffDocument::TYPE_CONTRACT,
        'file' => $file,
        'issued_at' => '2026-01-01',
        'expires_at' => '2027-01-01',
    ]);

    expect($document)->toBeInstanceOf(StaffDocument::class)
        ->and($document->staff_id)->toBe($staff->id)
        ->and($document->type)->toBe(StaffDocument::TYPE_CONTRACT)
        ->and($document->file_name)->toBe('contract.pdf')
        ->and($document->uploaded_by)->toBe($manager->id)
        ->and($document->issued_at->toDateString())->toBe('2026-01-01')
        ->and($document->expires_at->toDateString())->toBe('2027-01-01');

    // Assert stored in private local disk, not public
    Storage::disk('local')->assertExists($document->file_path);
    Storage::disk('public')->assertMissing($document->file_path);

    // Audit logged
    expect(AuditLog::where('action', 'staff.document.uploaded')->exists())->toBeTrue();
});

it('rejects upload by System Admin (SA cannot touch staff files)', function () {
    $sa = sa();
    $staff = createTestStaff();
    $file = UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg');

    $action = app(UploadStaffDocument::class);

    expect(fn () => $action->execute($sa, $staff, [
        'type' => StaffDocument::TYPE_ID_CARD,
        'file' => $file,
    ]))->toThrow(AuthorizationException::class);
});

it('rejects files larger than 5MB or with disallowed extensions', function () {
    $manager = pm();
    $staff = createTestStaff();
    $action = app(UploadStaffDocument::class);

    // Oversized file (> 5 MB = 5120 KB)
    $largeFile = UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf');
    expect(fn () => $action->execute($manager, $staff, [
        'type' => StaffDocument::TYPE_CONTRACT,
        'file' => $largeFile,
    ]))->toThrow(ValidationException::class);

    // Disallowed extension (.exe / .zip)
    $scriptFile = UploadedFile::fake()->create('malicious.exe', 50, 'application/octet-stream');
    expect(fn () => $action->execute($manager, $staff, [
        'type' => StaffDocument::TYPE_CONTRACT,
        'file' => $scriptFile,
    ]))->toThrow(ValidationException::class);
});

it('private staff document download enforces strict authorization', function () {
    $manager = pm();
    $ownerUser = owner();
    $staffUser = ps();
    $otherStaffUser = ps();
    $sa = sa();

    $staff = createTestStaff($staffUser);
    $file = UploadedFile::fake()->create('qualification.pdf', 150, 'application/pdf');

    $document = app(UploadStaffDocument::class)->execute($manager, $staff, [
        'type' => StaffDocument::TYPE_QUALIFICATION,
        'file' => $file,
    ]);

    // 1. PM can download
    $this->actingAs($manager)
        ->get(route('staff.documents.show', [$staff, $document]))
        ->assertOk();

    // 2. Owner can download
    $this->actingAs($ownerUser)
        ->get(route('staff.documents.show', [$staff, $document]))
        ->assertOk();

    // 3. Linked staff member can download their own document
    $this->actingAs($staffUser)
        ->get(route('staff.documents.show', [$staff, $document]))
        ->assertOk();

    // 4. Other staff member cannot download
    $this->actingAs($otherStaffUser)
        ->get(route('staff.documents.show', [$staff, $document]))
        ->assertForbidden();

    // 5. System Admin is forbidden
    $this->actingAs($sa)
        ->get(route('staff.documents.show', [$staff, $document]))
        ->assertForbidden();
});

it('manager can delete a staff document', function () {
    $manager = pm();
    $staff = createTestStaff();
    $file = UploadedFile::fake()->create('temp.pdf', 100, 'application/pdf');

    $document = app(UploadStaffDocument::class)->execute($manager, $staff, [
        'type' => StaffDocument::TYPE_TRAINING,
        'file' => $file,
    ]);

    $path = $document->file_path;
    Storage::disk('local')->assertExists($path);

    app(DeleteStaffDocument::class)->execute($manager, $document);

    expect(StaffDocument::find($document->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
    expect(AuditLog::where('action', 'staff.document.deleted')->exists())->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 2 — Document Expiry and Reminders
// ═══════════════════════════════════════════════════════════════════════════

it('CheckDocumentExpiry notifies managers and creates reminders for expiring documents', function () {
    $manager = pm();
    $staff = createTestStaff();

    // Expiring in 10 days
    $expiringDoc = StaffDocument::create([
        'staff_id' => $staff->id,
        'type' => StaffDocument::TYPE_LICENCE,
        'file_path' => 'staff-documents/fake1.pdf',
        'file_name' => 'fake1.pdf',
        'issued_at' => now()->subYear()->toDateString(),
        'expires_at' => now()->addDays(10)->toDateString(),
        'uploaded_by' => $manager->id,
    ]);

    // Already expired 5 days ago
    $expiredDoc = StaffDocument::create([
        'staff_id' => $staff->id,
        'type' => StaffDocument::TYPE_CONTRACT,
        'file_path' => 'staff-documents/fake2.pdf',
        'file_name' => 'fake2.pdf',
        'issued_at' => now()->subYears(2)->toDateString(),
        'expires_at' => now()->subDays(5)->toDateString(),
        'uploaded_by' => $manager->id,
    ]);

    // Valid for another year (should not trigger)
    $futureDoc = StaffDocument::create([
        'staff_id' => $staff->id,
        'type' => StaffDocument::TYPE_QUALIFICATION,
        'file_path' => 'staff-documents/fake3.pdf',
        'file_name' => 'fake3.pdf',
        'expires_at' => now()->addYear()->toDateString(),
        'uploaded_by' => $manager->id,
    ]);

    $action = app(CheckDocumentExpiry::class);
    $result = $action->execute(lookaheadDays: 30);

    expect($result['notified'])->toBeGreaterThan(0);

    // Database notifications check
    expect(DB::table('notifications')->where('type', 'staff.document.expiring')->exists())->toBeTrue();
    expect(DB::table('notifications')->where('type', 'staff.document.expired')->exists())->toBeTrue();

    // Repeated run is idempotent (dedupe key prevents spam)
    $secondResult = $action->execute(lookaheadDays: 30);
    expect($secondResult['notified'])->toBe(0);
});

it('runs staff:document-alerts command successfully', function () {
    $this->artisan('staff:document-alerts --lookahead=30')
        ->assertSuccessful();
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 3 — Sales Targets and Progress Calculation
// ═══════════════════════════════════════════════════════════════════════════

it('Owner or PM can set a monthly sales target for whole pharmacy', function () {
    $ownerUser = owner();
    $currentPeriod = now()->format('Y-m');

    $target = app(SetSalesTarget::class)->execute($ownerUser, [
        'staff_id' => null,
        'period' => $currentPeriod,
        'amount' => 10000000, // 100,000.00 ETB
    ]);

    expect($target)->toBeInstanceOf(SalesTarget::class)
        ->and($target->staff_id)->toBeNull()
        ->and($target->isWholePharmacy())->toBeTrue()
        ->and($target->period)->toBe($currentPeriod)
        ->and($target->amount)->toBe(10000000);

    expect(AuditLog::where('action', 'sales_target.saved')->exists())->toBeTrue();
});

it('Owner or PM can set an individual sales target for a staff member', function () {
    $manager = pm();
    $staff = createTestStaff();
    $currentPeriod = now()->format('Y-m');

    $target = app(SetSalesTarget::class)->execute($manager, [
        'staff_id' => $staff->id,
        'period' => $currentPeriod,
        'amount' => 2500000, // 25,000.00 ETB
    ]);

    expect($target->staff_id)->toBe($staff->id)
        ->and($target->isWholePharmacy())->toBeFalse()
        ->and($target->amount)->toBe(2500000);
});

it('rejects setting sales target by non-manager or system admin', function () {
    $sa = sa();
    $staffUser = ps();

    expect(fn () => app(SetSalesTarget::class)->execute($sa, [
        'period' => '2026-11',
        'amount' => 500000,
    ]))->toThrow(AuthorizationException::class);

    expect(fn () => app(SetSalesTarget::class)->execute($staffUser, [
        'period' => '2026-11',
        'amount' => 500000,
    ]))->toThrow(AuthorizationException::class);
});

it('calculates achieved sales and progress percentage correctly', function () {
    $user1 = ps();
    $user2 = ps();
    $staff1 = createTestStaff($user1);
    $currentPeriod = now()->format('Y-m');

    // Create sales in current month
    createCompletedSaleForTarget($user1, 300000); // 3,000 ETB
    createCompletedSaleForTarget($user1, 200000); // 2,000 ETB
    createCompletedSaleForTarget($user2, 500000); // 5,000 ETB

    // Whole pharmacy target = 20,000 ETB (2,000,000 santim)
    $pharmacyTarget = SalesTarget::create([
        'staff_id' => null,
        'period' => $currentPeriod,
        'amount' => 2000000,
    ]);

    // Total pharmacy sales = 3,000 + 2,000 + 5,000 = 10,000 ETB (1,000,000 santim) -> 50%
    expect($pharmacyTarget->achievedSales())->toBe(1000000);
    expect($pharmacyTarget->progressPercentage())->toBe(50.0);

    // Individual target for staff1 = 10,000 ETB (1,000,000 santim)
    $staffTarget = SalesTarget::create([
        'staff_id' => $staff1->id,
        'period' => $currentPeriod,
        'amount' => 1000000,
    ]);

    // staff1 sales = 5,000 ETB (500,000 santim) -> 50%
    expect($staffTarget->achievedSales())->toBe(500000);
    expect($staffTarget->progressPercentage())->toBe(50.0);
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 4 — UI Displays: Owner Dashboard & Staff Shift
// ═══════════════════════════════════════════════════════════════════════════

it('Owner dashboard renders SalesTargetTile with progress bar and target details', function () {
    $ownerUser = owner();
    $currentPeriod = now()->format('Y-m');

    // Target = 10,000 ETB
    SalesTarget::create([
        'staff_id' => null,
        'period' => $currentPeriod,
        'amount' => 1000000,
    ]);

    // Achieved = 4,000 ETB (40%)
    $staffUser = ps();
    createCompletedSaleForTarget($staffUser, 400000);

    Livewire::actingAs($ownerUser)
        ->test(SalesTargetTile::class)
        ->assertSee('40%')
        ->assertSee('4,000.00')
        ->assertSee('10,000.00')
        ->assertSee('6,000.00'); // Remaining
});

it('Staff Dashboard renders sales target progress bar for active cashier', function () {
    $staffUser = ps();
    $staff = createTestStaff($staffUser);
    $currentPeriod = now()->format('Y-m');

    // Set individual target = 5,000 ETB
    SalesTarget::create([
        'staff_id' => $staff->id,
        'period' => $currentPeriod,
        'amount' => 500000,
    ]);

    // Staff makes a sale of 2,500 ETB (50%)
    createCompletedSaleForTarget($staffUser, 250000);

    Livewire::actingAs($staffUser)
        ->test(Dashboard::class)
        ->assertSee('50%')
        ->assertSee('2,500.00')
        ->assertSee('5,000.00');
});

it('Staff Show Livewire component allows uploading documents', function () {
    $manager = pm();
    $staff = createTestStaff();
    $file = UploadedFile::fake()->create('id_doc.pdf', 300, 'application/pdf');

    Livewire::actingAs($manager)
        ->test(StaffShow::class, ['staff' => $staff])
        ->set('doc_type', StaffDocument::TYPE_ID_CARD)
        ->set('doc_file', $file)
        ->set('doc_issued', '2026-02-01')
        ->set('doc_expires', '2028-02-01')
        ->call('uploadDocument')
        ->assertHasNoErrors();

    expect(StaffDocument::where('staff_id', $staff->id)->exists())->toBeTrue();
});
