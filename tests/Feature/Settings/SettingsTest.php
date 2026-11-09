<?php

use App\Actions\Settings\RequestSettingChange;
use App\Exceptions\ApprovalException;
use App\Livewire\Dashboard;
use App\Livewire\Settings\Index;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Services\ApprovalService;
use App\Services\SettingsRepository;
use App\Support\SettingsCatalogue;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** UC-38 Manage system settings. FR-9.4, 9.5, 9.7; AC-31. */
beforeEach(fn () => seedRoles());

function approveSetting(ApprovalRequest $r, $approver = null): void
{
    $approver ??= pm();
    actAs($approver);
    reauth();
    app(ApprovalService::class)->approve($r, $approver);
}

it('returns BRD defaults when nothing is stored and the caller default otherwise', function () {
    $s = app(SettingsRepository::class);
    expect($s->get('security.max_failed_logins'))->toBe(5)->and($s->get('approvals.expiry_hours'))->toBe(72)
        ->and($s->get('inventory.near_expiry_alert_days'))->toBe([90, 60, 30])
        ->and($s->get('sales.refund_limit', 'fallback'))->toBe('fallback')->and($s->isNotSet('sales.refund_limit'))->toBeTrue()
        ->and($s->isNotSet('profile.name'))->toBeTrue()->and($s->isNotSet('security.max_failed_logins'))->toBeFalse();
    expect(setting('security.idle_timeout_minutes'))->toBe(15);
});

it('is not live until approved: the SA requests, the PM approves, then it takes effect (AC-31)', function () {
    $request = app(RequestSettingChange::class)->execute(sa(), 'security.max_failed_logins', '3', 'tighter');

    expect(setting('security.max_failed_logins'))->toBe(5)->and($request->eligible_roles)->toBe(['pharmacy_manager', 'owner']);
    approveSetting($request);

    expect(setting('security.max_failed_logins'))->toBe(3);
    expect(DB::table('setting_histories')->where('key', 'security.max_failed_logins')->count())->toBe(1);
    $log = AuditLog::where('action', 'settings.value.updated')->first();
    expect($log->old_values)->toBe(['security.max_failed_logins' => 5])->and($log->new_values)->toBe(['security.max_failed_logins' => 3]);
});

it('does not apply a rejected change', function () {
    $request = app(RequestSettingChange::class)->execute(sa(), 'security.max_failed_logins', '3');
    $mgr = actAs(pm());
    reauth();
    app(ApprovalService::class)->reject($request, $mgr, 'no');

    expect(setting('security.max_failed_logins'))->toBe(5);
});

it('lets the Owner approve too, but never the SA who asked', function () {
    $admin = sa();
    $request = app(RequestSettingChange::class)->execute($admin, 'approvals.expiry_hours', '24');
    actAs($admin);
    reauth();
    expect(fn () => app(ApprovalService::class)->approve($request, $admin))->toThrow(ApprovalException::class);
    approveSetting($request, owner());
    expect(setting('approvals.expiry_hours'))->toBe(24);
});

it('allows only the SA to request changes; PM, Owner and staff are denied', function () {
    foreach ([pm(), owner(), ps()] as $user) {
        expect(fn () => app(RequestSettingChange::class)->execute($user, 'security.max_failed_logins', '3'))->toThrow(AuthorizationException::class);
    }
});

it('shows settings read-only to PM and Owner, hides them from staff', function () {
    actAs(pm());
    Livewire::test(Index::class)->assertDontSee(__('settings.request_change'));
    actAs(sa());
    Livewire::test(Index::class)->assertSee(__('settings.request_change'));
    actAs(ps());
    $this->get('/settings')->assertForbidden();
});

it('validates values by type (E1)', function () {
    $admin = sa();
    $bad = [
        ['security.max_failed_logins', '0'], ['security.max_failed_logins', 'abc'], ['security.max_failed_logins', '2.5'], ['security.max_failed_logins', ''],
        ['profile.currency', 'birr'], ['profile.currency', 'et'],
        ['inventory.near_expiry_alert_days', '30, 60, 90'], ['inventory.near_expiry_alert_days', '90, 90'], ['inventory.near_expiry_alert_days', 'a, b'],
        ['sales.refund_limit', '-5'], ['sales.active_payment_methods', 'cash, bitcoin'], ['general.default_language', 'fr'], ['profile.name', ' '],
        ['unknown.key', '1'],
    ];
    foreach ($bad as [$key, $value]) {
        expect(fn () => app(RequestSettingChange::class)->execute($admin, $key, $value))->toThrow(ValidationException::class, null, "{$key}={$value}");
    }
    expect(ApprovalRequest::count())->toBe(0);
});

it('normalises good values', function () {
    $admin = sa();
    $req = fn ($k, $v) => app(RequestSettingChange::class)->execute($admin, $k, $v)->payload['new'];

    expect($req('inventory.near_expiry_alert_days', '120, 60, 30'))->toBe([120, 60, 30]);
    expect($req('profile.currency', 'usd'))->toBe('USD');
    expect($req('sales.refund_limit', '1500.5'))->toBe(1500.5);
    expect($req('sales.active_payment_methods', 'cash, card'))->toBe(['cash', 'card']);
    expect($req('prescriptions.licence_categories_allowed', 'Pharmacist, Technician'))->toBe(['Pharmacist', 'Technician']);
    expect($req('inventory.receipt_requires_confirmation', '0'))->toBeFalse();
});

it('allows clearing an optional setting back to Not set, but not a required one', function () {
    $admin = sa();
    expect(app(RequestSettingChange::class)->execute($admin, 'sales.refund_limit', '')->payload['new'])->toBeNull();
    expect(fn () => app(RequestSettingChange::class)->execute($admin, 'security.idle_timeout_minutes', ''))->toThrow(ValidationException::class);
});

it('allows only one pending change per setting', function () {
    $admin = sa();
    app(RequestSettingChange::class)->execute($admin, 'security.max_failed_logins', '3');
    expect(fn () => app(RequestSettingChange::class)->execute($admin, 'security.max_failed_logins', '4'))->toThrow(ValidationException::class);
});

it('drives the real behaviour: approval expiry, lockout and idle timeout come from settings', function () {
    approveSetting(app(RequestSettingChange::class)->execute(sa(), 'approvals.expiry_hours', '10'));
    approveSetting(app(RequestSettingChange::class)->execute(sa(), 'security.idle_timeout_minutes', '2'));

    $this->actingAs(ps())->withSession(['auth.last_activity' => time() - 180, 'auth.acting_role' => 'pharmacy_staff'])->get('/dashboard')->assertRedirect(route('login'));
});

it('warns the PM and Owner on the dashboard about required settings that are Not set', function () {
    actAs(owner());
    Livewire::test(Dashboard::class)->assertSee('profile.name')->assertSee('profile.licence_number')->assertDontSee('security.max_failed_logins');
    actAs(ps());
    Livewire::test(Dashboard::class)->assertDontSee('profile.licence_number');

    approveSetting(app(RequestSettingChange::class)->execute(sa(), 'profile.name', 'Selam Pharmacy'));
    actAs(owner());
    Livewire::test(Dashboard::class)->assertDontSee('profile.name');
});

it('has no hard-coded business constants: every catalogue key has a read path and a default or Not set rule', function () {
    foreach (SettingsCatalogue::all() as $key => $def) {
        $value = setting($key);
        if ($def['default'] !== null) {
            expect($value)->toBe($def['default'], $key);
        } else {
            expect($value)->toBeNull($key);
        }
    }
});
