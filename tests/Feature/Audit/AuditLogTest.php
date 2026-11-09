<?php

use App\Livewire\Audit\Index;
use App\Models\AuditLog;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuditLogQuery;
use Livewire\Livewire;

/** UC-31 View audit log. FR-7.5, FR-8.3; BR-15; AC-19, AC-35. */
beforeEach(fn () => seedRoles());

function seedAudit(): void
{
    $a = ps(['username' => 'amina']);
    $b = pm(['username' => 'bruk']);
    AuditLogger::record('auth.login.success', null, [], [], [], $a);
    AuditLogger::record('staff.staff.updated', null, ['title' => 'A'], ['title' => 'B'], [], $b);
    AuditLogger::record('sale.sale.voided', null, ['total' => '120.00', 'customer' => 'X'], ['total' => '0'], [], $b);
}

it('lets the Owner and PM read every entry, with a detail panel of old and new values', function () {
    seedAudit();
    foreach ([owner(), pm()] as $viewer) {
        actAs($viewer);
        Livewire::test(Index::class)->assertSee('auth.login.success')->assertSee('staff.staff.updated')->assertSee('sale.sale.voided')->assertSee('120.00');
    }
    $log = AuditLog::where('action', 'staff.staff.updated')->first();
    actAs(owner());
    Livewire::test(Index::class)->call('showDetail', $log->id)->assertSee('&quot;title&quot;: &quot;A&quot;', false)->assertSee('&quot;title&quot;: &quot;B&quot;', false)->call('closeDetail');
});

it('shows the SA technical entries only and masks business values (E2, AC-35)', function () {
    seedAudit();
    actAs(sa());

    Livewire::test(Index::class)->assertSee('auth.login.success')->assertDontSee('staff.staff.updated')->assertDontSee('sale.sale.voided')->assertDontSee('120.00');
});

it('masks business values in the SA detail view even when the row is reachable', function () {
    AuditLogger::record('prescription.prescription.created', null, [], ['patient' => 'Abebe'], [], pm());
    expect(AuditLogQuery::visibleValues(['patient' => 'Abebe'], 'prescription.prescription.created', sa()))->toBe(['patient' => '[masked]']);
    expect(AuditLogQuery::visibleValues(['patient' => 'Abebe'], 'prescription.prescription.created', owner()))->toBe(['patient' => 'Abebe']);
});

it('denies the screen to pharmacy_staff, by URL too', function () {
    actAs(ps());
    $this->get('/audit')->assertForbidden();
    $this->get('/audit/export/csv')->assertForbidden();
});

it('filters by user, role used, action, date range and subject type', function () {
    seedAudit();
    $bruk = User::where('username', 'bruk')->value('id');
    actAs(owner());

    Livewire::test(Index::class)->set('user', (string) $bruk)->assertDontSee('auth.login.success')->assertSee('staff.staff.updated');
    Livewire::test(Index::class)->set('role', 'pharmacy_staff')->assertSee('auth.login.success')->assertDontSee('staff.staff.updated');
    Livewire::test(Index::class)->set('action', 'sale.')->assertSee('sale.sale.voided')->assertDontSee('auth.login.success');
    $today = now()->timezone(config('pms.timezone'))->toDateString();
    Livewire::test(Index::class)->set('from', now()->timezone(config('pms.timezone'))->addDay()->toDateString())->assertDontSee('auth.login.success');
    Livewire::test(Index::class)->set('from', now()->timezone(config('pms.timezone'))->subDay()->toDateString())->set('to', $today)->assertSee('auth.login.success');
    AuditLogger::record('staff.staff.created', Staff::factory()->create());
    Livewire::test(Index::class)->set('subject', 'Staff')->assertSee('staff.staff.created')->assertDontSee('sale.sale.voided');
});

it('offers no way to change an entry from the screen', function () {
    actAs(owner());
    $component = Livewire::test(Index::class);
    foreach (['update', 'delete', 'edit', 'destroy', 'save'] as $method) {
        expect(method_exists($component->instance(), $method))->toBeFalse($method);
    }
});

it('exports CSV with the same visibility rules, an audited export, and neutralised formulas', function () {
    seedAudit();

    actAs(owner());
    $csv = $this->get('/audit/export/csv')->assertOk()->streamedContent();
    expect($csv)->toContain('sale.sale.voided')->toContain('time,user,role_used,action');
    expect(AuditLog::where('action', 'audit.export')->exists())->toBeTrue();

    actAs(sa());
    $csv = $this->get('/audit/export/csv')->assertOk()->streamedContent();
    expect($csv)->toContain('auth.login.success')->not->toContain('sale.sale.voided')->not->toContain('staff.staff.updated');
});

it('exports a valid PDF and honours the filters', function () {
    seedAudit();
    actAs(pm());

    $response = $this->get('/audit/export/pdf?action=staff.')->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    $pdf = $response->getContent();
    expect(substr($pdf, 0, 5))->toBe('%PDF-')->and($pdf)->toContain('staff.staff.updated')->not->toContain('auth.login.success')->and(str_ends_with(trim($pdf), '%%EOF'))->toBeTrue();
});

it('records the terminal, role used and old/new values on every entry', function () {
    $user = actAs(pm());
    $log = AuditLogger::record('staff.staff.updated', Staff::factory()->create(), ['a' => 1], ['a' => 2]);

    expect($log->user_id)->toBe($user->id)->and($log->role_used)->toBe('pharmacy_manager')->and($log->terminal)->not->toBeEmpty()
        ->and($log->old_values)->toBe(['a' => 1])->and($log->new_values)->toBe(['a' => 2])->and($log->auditable_type)->toBe(Staff::class);
});
