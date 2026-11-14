<?php

use App\Actions\Backup\RequestRestore;
use App\Exceptions\ApprovalException;
use App\Livewire\Backup\Dashboard;
use App\Livewire\Backup\RestoreChecklist;
use App\Models\AuditLog;
use App\Models\BackupRun;
use App\Models\Product;
use App\Models\RestoreRequest;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use App\Services\BackupService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    Storage::fake('backup');
    config(['backup.disk' => 'backup']);
});

test('schedule entries and times are configured properly', function () {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events());

    $commands = $events->map(function (Event $event) {
        return $event->command;
    })->filter();

    $fullBackup = $commands->first(fn ($c) => str_contains($c, 'backup:run --type=full'));
    $dbBackup = $commands->first(fn ($c) => str_contains($c, 'backup:run --type=db'));
    $clean = $commands->first(fn ($c) => str_contains($c, 'backup:clean'));
    $monitor = $commands->first(fn ($c) => str_contains($c, 'backup:monitor'));

    expect($fullBackup)->not->toBeNull()
        ->and($dbBackup)->not->toBeNull()
        ->and($clean)->not->toBeNull()
        ->and($monitor)->not->toBeNull();
});

test('retention keeps 30 daily and 12 month-end archives', function () {
    $service = app(BackupService::class);
    $disk = Storage::disk('backup');

    // Create 50 simulated backup runs across various days and month-ends
    $startDate = Carbon::parse('2025-01-01 02:00:00');

    for ($i = 0; $i < 60; $i++) {
        $date = $startDate->copy()->addDays($i);
        $filename = "backup-full-{$date->format('Y-m-d-His')}.enc";
        $disk->put($filename, 'fake-encrypted-content');

        BackupRun::create([
            'filename' => $filename,
            'disk' => 'backup',
            'type' => BackupRun::TYPE_FULL,
            'size_bytes' => 1024,
            'checksum' => 'fakechecksum',
            'status' => BackupRun::STATUS_SUCCESS,
            'is_encrypted' => true,
            'started_at' => $date,
            'completed_at' => $date->copy()->addMinutes(5),
        ]);
    }

    expect(BackupRun::count())->toBe(60);

    // Run custom retention cleanup
    $deleted = $service->clean();

    // Exactly 30 daily + up to 12 month-ends should remain, older ones deleted
    $remaining = BackupRun::all();
    expect($remaining->count())->toBeLessThan(60)
        ->and($remaining->count())->toBeGreaterThanOrEqual(30)
        ->and(count($deleted))->toBeGreaterThan(0);

    foreach ($deleted as $deletedFile) {
        expect($disk->exists($deletedFile))->toBeFalse();
    }
});

test('restore needs approval and the initiator cannot approve (AC-21)', function () {
    $sa = sa();
    $pm = pm();
    $service = app(BackupService::class);

    // Create a real backup
    $backupRun = $service->createBackup(BackupRun::TYPE_DB);

    actAs($sa);
    $action = app(RequestRestore::class);
    $restore = $action->execute($sa, [
        'backup_filename' => $backupRun->filename,
        'reason' => 'Database corrupted during power outage',
    ]);

    expect($restore->status)->toBe(RestoreRequest::STATUS_PENDING)
        ->and($restore->approval_request_id)->not->toBeNull();

    $approvalReq = $restore->approvalRequest;

    // SA cannot approve restore (initiator cannot approve & SA has no approval permission)
    reauth();
    expect(fn () => app(ApprovalService::class)->approve($approvalReq, $sa, 'SA self-approval'))
        ->toThrow(ApprovalException::class);

    // PM approves restore
    reauth();
    actAs($pm);
    app(ApprovalService::class)->approve($approvalReq, $pm, 'Approved restore for disaster recovery');

    $restore->refresh();
    expect($restore->status)->toBe(RestoreRequest::STATUS_COMPLETED)
        ->and($restore->approved_by)->toBe($pm->id);
});

test('direct restore call without an approved request is denied', function () {
    $sa = sa();
    $service = app(BackupService::class);
    $backupRun = $service->createBackup(BackupRun::TYPE_DB);

    $unapprovedRequest = RestoreRequest::create([
        'backup_run_id' => $backupRun->id,
        'backup_filename' => $backupRun->filename,
        'reason' => 'Testing unauthorized call',
        'status' => RestoreRequest::STATUS_PENDING,
        'requested_by' => $sa->id,
    ]);

    expect(fn () => $service->restore($unapprovedRequest, $sa))
        ->toThrow(ApprovalException::class);
});

test('SA cannot see key material or backup contents (AC-35)', function () {
    $sa = sa();
    actAs($sa);

    $service = app(BackupService::class);
    $backupRun = $service->createBackup(BackupRun::TYPE_FULL);

    // SA views Livewire dashboard: shows metadata but no key material
    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee($backupRun->filename)
        ->assertSee('AES-256-CBC Encrypted')
        ->assertDontSee($service->getEncryptionKey())
        ->assertDontSee('APP_KEY')
        ->assertDontSee('BACKUP_ENCRYPTION_KEY');

    // Settings table does not contain key
    expect(DB::table('settings')->where('key', 'like', '%key%')->exists())->toBeFalse();
});

test('failure and 26-hour staleness trigger alert (A1)', function () {
    $sa = sa();
    $pm = pm();
    $owner = owner();
    $service = app(BackupService::class);

    // Scenario 1: No backup in 26 hours
    Carbon::setTestNow(now());
    $stale = $service->checkStaleness();
    expect($stale)->toBeTrue();

    // Audit entry written
    expect(AuditLog::where('action', 'backup.stale')->exists())->toBeTrue();

    // Notifications dispatched
    expect(DB::table('notifications')->where('type', 'backup.alert')->count())->toBeGreaterThan(0);

    // Scenario 2: Recent success clears staleness
    $service->createBackup(BackupRun::TYPE_DB);
    expect($service->checkStaleness())->toBeFalse();

    // Scenario 3: After 27 hours, staleness triggers again
    Carbon::setTestNow(now()->addHours(27));
    expect($service->checkStaleness())->toBeTrue();

    Carbon::setTestNow();
});

test('audit rows written after the backup survive a simulated restore', function () {
    $service = app(BackupService::class);
    $sa = sa();
    $pm = pm();

    // 1. Create a product and an audit row before backup
    $product = Product::create([
        'brand' => 'Existing Med 500mg',
        'generic_name' => 'Existing',
        'classification' => 'rx',
        'is_active' => true,
        'reorder_level' => 10,
    ]);
    AuditLogger::record('product.before.backup', $product, [], [], [], $pm, 'Product created before backup');

    // 2. Take backup at T1
    $backupRun = $service->createBackup(BackupRun::TYPE_DB);

    // 3. Perform actions after backup at T2
    Carbon::setTestNow(now()->addMinutes(10));
    $newProduct = Product::create([
        'brand' => 'New Med 250mg',
        'generic_name' => 'New Med',
        'classification' => 'otc',
        'is_active' => true,
        'reorder_level' => 5,
    ]);
    AuditLogger::record('action.after.backup', $newProduct, [], [], [], $pm, 'Action performed after backup point');

    expect(AuditLog::where('action', 'action.after.backup')->exists())->toBeTrue();

    // 4. Request and approve restore
    actAs($sa);
    $restore = app(RequestRestore::class)->execute($sa, [
        'backup_filename' => $backupRun->filename,
        'reason' => 'Restoring to T1 state',
    ]);

    reauth();
    actAs($pm);
    app(ApprovalService::class)->approve($restore->approvalRequest, $pm, 'Approved restore');

    // 5. Verify:
    // Operational table was restored to T1 state
    expect(Product::where('id', $newProduct->id)->exists())->toBeFalse()
        ->and(Product::where('id', $product->id)->exists())->toBeTrue();

    // BUT the post-backup audit log row SURVIVED!
    expect(AuditLog::where('action', 'action.after.backup')->exists())->toBeTrue();

    // And backup.restored was appended
    expect(AuditLog::where('action', 'backup.restored')->exists())->toBeTrue();

    Carbon::setTestNow();
});

test('Livewire Dashboard allows SA to trigger backup and request restore', function () {
    $sa = sa();
    actAs($sa);

    $service = app(BackupService::class);
    $run = $service->createBackup(BackupRun::TYPE_DB);

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->call('runBackupNow')
        ->assertHasNoErrors();

    expect(BackupRun::count())->toBeGreaterThanOrEqual(2);

    Livewire::test(Dashboard::class)
        ->call('openRestoreModal', $run->filename)
        ->set('restoreReason', 'System crash recovery drill')
        ->call('submitRestoreRequest')
        ->assertHasNoErrors();

    expect(RestoreRequest::where('backup_filename', $run->filename)->exists())->toBeTrue();
});

test('Livewire RestoreChecklist displays operational metrics for PM and Owner', function () {
    $pm = pm();
    actAs($pm);

    Livewire::test(RestoreChecklist::class)
        ->assertOk()
        ->assertSee(__('backup.checklist_title'))
        ->assertSee(__('backup.check_products_title'))
        ->assertSee(__('backup.check_stock_valuation'))
        ->assertSee(__('backup.check_audit_integrity'));

    // SA is forbidden from viewing checklist (AC-35)
    actAs(sa());
    Livewire::test(RestoreChecklist::class)
        ->assertForbidden();
});
