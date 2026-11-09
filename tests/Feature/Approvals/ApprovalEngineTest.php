<?php

use App\Approvals\ApprovalHandler;
use App\Approvals\ApprovalHandlerRegistry;
use App\Approvals\ApprovalRouter;
use App\Enums\Role;
use App\Exceptions\ApprovalException;
use App\Livewire\Approvals\Queue;
use App\Livewire\Approvals\Show;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** UC-34 Process approval request (the reusable engine). FR-8.7; BR-14; AC-28. */
beforeEach(function () {
    seedRoles();
    $GLOBALS['demo_runs'] = 0;
    $GLOBALS['demo_fail'] = false;
    app(ApprovalHandlerRegistry::class)->register(new class implements ApprovalHandler
    {
        public function type(): string
        {
            return 'demo.thing';
        }

        public function eligibleApproverRoles(User $initiator, array $payload): array
        {
            return ApprovalRouter::byInitiator($initiator);
        }

        public function onApproved(ApprovalRequest $request, User $approver): void
        {
            if ($GLOBALS['demo_fail']) {
                DB::table('settings')->insert(['group' => 'x', 'key' => 'half.done', 'type' => 't']); // must be rolled back
                throw new RuntimeException('boom');
            }
            $GLOBALS['demo_runs']++;
        }

        public function onRejected(ApprovalRequest $request, User $approver): void {}
    });
});

function ask(User $initiator, string $type = 'demo.thing'): ApprovalRequest
{
    return app(ApprovalService::class)->request($type, $initiator, ['x' => 1], 'Do the demo thing', null, null, 'because');
}

it('routes by role: staff to PM, PM to Owner, Owner to PM (A2)', function () {
    expect(ask(ps())->eligible_roles)->toBe(['pharmacy_manager']);
    expect(ask(pm())->eligible_roles)->toBe(['owner']);
    expect(ask(owner())->eligible_roles)->toBe(['pharmacy_manager']);
});

it('routes SA requests: account changes to PM, to Owner when about a PM, to PM when about the Owner', function () {
    expect(ApprovalRouter::forAccountChange(ps()))->toBe([Role::PharmacyManager]);
    expect(ApprovalRouter::forAccountChange(pm()))->toBe([Role::Owner]);
    expect(ApprovalRouter::forAccountChange(owner()))->toBe([Role::PharmacyManager]);
    expect(ApprovalRouter::managementEither())->toBe([Role::PharmacyManager, Role::Owner]);
});

it('never lets the initiator approve their own request, whatever role they use (AC-28, E1)', function () {
    $both = makeUser([Role::PharmacyManager, Role::Owner]);
    $request = ask($both); // a PM/Owner initiator: eligible roles include both of their roles
    actAs($both);
    reauth();

    expect(fn () => app(ApprovalService::class)->approve($request, $both))->toThrow(ApprovalException::class);
    expect($request->refresh()->status)->toBe('pending')->and($GLOBALS['demo_runs'])->toBe(0);
    expect(AuditLog::where('action', 'approval.request.denied_own')->exists())->toBeTrue();
});

it('needs the password re-entered, proven server-side (AC-28, E2)', function () {
    $request = ask(ps());
    $mgr = actAs(pm());

    expect(fn () => app(ApprovalService::class)->approve($request, $mgr))->toThrow(ApprovalException::class, __('approvals.error.reauth'));
    expect($request->refresh()->status)->toBe('pending');

    reauth();
    app(ApprovalService::class)->approve($request, $mgr);
    expect($request->refresh()->status)->toBe('approved');

    // the proof is single-use
    $second = ask(ps());
    expect(fn () => app(ApprovalService::class)->approve($second, $mgr))->toThrow(ApprovalException::class);
});

it('refuses an approver whose role is not eligible', function () {
    $request = ask(ps());
    $own = actAs(owner());
    reauth();

    expect(fn () => app(ApprovalService::class)->approve($request, $own))->toThrow(ApprovalException::class);
});

it('runs the handler once when approved twice', function () {
    $request = ask(ps());
    $mgr = actAs(pm());
    $svc = app(ApprovalService::class);

    reauth();
    $svc->approve($request, $mgr);
    reauth();
    expect(fn () => $svc->approve($request, $mgr))->toThrow(ApprovalException::class, __('approvals.error.not_pending'));

    expect($GLOBALS['demo_runs'])->toBe(1);
});

it('leaves the request pending and rolls back when the handler fails', function () {
    $request = ask(ps());
    $mgr = actAs(pm());
    $GLOBALS['demo_fail'] = true;
    reauth();

    expect(fn () => app(ApprovalService::class)->approve($request, $mgr))->toThrow(ApprovalException::class, __('approvals.error.handler_failed'));

    expect($request->refresh()->status)->toBe('pending')->and(DB::table('settings')->where('key', 'half.done')->exists())->toBeFalse();
    expect(AuditLog::where('action', 'approval.request.failed')->exists())->toBeTrue();

    $GLOBALS['demo_fail'] = false; // and it can be approved later
    reauth();
    app(ApprovalService::class)->approve($request, $mgr);
    expect($request->refresh()->status)->toBe('approved');
});

it('requires a comment to reject (A1) and audits the decision', function () {
    $request = ask(ps());
    $mgr = actAs(pm());
    reauth();

    expect(fn () => app(ApprovalService::class)->reject($request, $mgr, '  '))->toThrow(ApprovalException::class);
    reauth();
    app(ApprovalService::class)->reject($request, $mgr, 'Not needed');

    expect($request->refresh()->status)->toBe('rejected')->and($request->decision_comment)->toBe('Not needed');
    $log = AuditLog::where('action', 'approval.request.rejected')->first();
    expect($log->user_id)->toBe($mgr->id)->and($log->role_used)->toBe('pharmacy_manager');
});

it('cannot approve an expired request and the hourly command expires it (E3)', function () {
    $request = ask(ps());
    $mgr = actAs(pm());

    $this->travel(73)->hours();
    reauth();
    expect(fn () => app(ApprovalService::class)->approve($request, $mgr))->toThrow(ApprovalException::class, __('approvals.error.expired'));

    $this->artisan('approvals:expire')->assertSuccessful();
    expect($request->refresh()->status)->toBe('expired');
    expect(AuditLog::where('action', 'approval.request.expired')->exists())->toBeTrue();
});

it('takes the expiry from the approvals.expiry_hours setting', function () {
    app(SettingsRepository::class)->put('approvals.expiry_hours', 1);
    $request = ask(ps());

    expect((int) round(now()->diffInMinutes($request->expires_at)))->toBe(60);
});

it('lets the initiator withdraw a pending request but nobody else', function () {
    $starter = ps();
    $request = ask($starter);

    expect(fn () => app(ApprovalService::class)->cancel($request, pm()))->toThrow(ApprovalException::class);
    app(ApprovalService::class)->cancel($request, $starter);
    expect($request->refresh()->status)->toBe('cancelled');
});

it('notifies approvers on creation and the initiator on decision', function () {
    $mgr = pm();
    $starter = ps();
    $request = ask($starter);
    expect(DB::table('notifications')->where('notifiable_id', $mgr->id)->where('type', 'approval.requested')->count())->toBe(1);

    actAs($mgr);
    reauth();
    app(ApprovalService::class)->approve($request, $mgr);
    expect(DB::table('notifications')->where('notifiable_id', $starter->id)->where('type', 'approval.approved')->count())->toBe(1);
});

it('lists only what the user may decide and the Owner and staff cannot see a PM-only item', function () {
    $mgr = pm();
    ask(ps());
    actAs($mgr);
    Livewire::test(Queue::class)->assertSee('Do the demo thing');

    actAs(owner());
    Livewire::test(Queue::class)->assertDontSee('Do the demo thing');
});

it('decides through the screen only after the password modal grants re-authentication', function () {
    $request = ask(ps());
    actAs(pm());

    $c = Livewire::test(Show::class, ['approvalRequest' => $request]);
    $c->call('startDecision', 'approve')->assertDispatched('confirm-password');

    // a forged browser event WITHOUT a server-side grant does nothing
    $c->dispatch('password-confirmed', purpose: 'approval.decide')->assertHasErrors('decision');
    expect($request->refresh()->status)->toBe('pending');

    reauth();
    Livewire::test(Show::class, ['approvalRequest' => $request])->call('startDecision', 'approve')->dispatch('password-confirmed', purpose: 'approval.decide')->assertRedirect(route('approvals.index'));
    expect($request->refresh()->status)->toBe('approved');
});

it('denies a user who may not see the request, also by URL', function () {
    $request = ask(ps());
    actAs(owner());
    $this->get(route('approvals.show', $request))->assertForbidden();
    actAs(pm());
    $this->get(route('approvals.show', $request))->assertOk();
});

it('requires a registered handler', function () {
    expect(fn () => app(ApprovalService::class)->request('nope.nope', ps(), [], 's'))->toThrow(InvalidArgumentException::class);
});
