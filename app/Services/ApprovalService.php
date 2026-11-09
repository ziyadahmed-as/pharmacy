<?php

namespace App\Services;

use App\Approvals\ApprovalHandlerRegistry;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one approval engine (BR-14, AGENTS.md rule 5). Later use cases only register
 * an ApprovalHandler; they never re-implement these rules.
 *
 *  - the approver is never the initiator, whatever role is used (E1);
 *  - approve and reject need password re-entry, proven server-side (E2);
 *  - reject needs a comment (A1);
 *  - requests expire after setting approvals.expiry_hours (E3);
 *  - approving runs the handler and changes the status in ONE transaction under a row
 *    lock; approving twice runs the handler once; a failing handler leaves the request pending.
 *
 * @uc UC-34 @fr FR-8.7,FR-8.2,FR-8.8 @br BR-14 @ac AC-28
 */
class ApprovalService
{
    public const REAUTH_PURPOSE = 'approval.decide';

    public function __construct(private ApprovalHandlerRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function request(
        string $type,
        User $initiator,
        array $payload,
        string $summary,
        ?Model $subject = null,
        ?User $concerns = null,
        ?string $comment = null,
    ): ApprovalRequest {
        Gate::forUser($initiator)->authorize('approvals.request');

        $handler = $this->registry->for($type);
        $roles = array_map(fn ($r) => $r->value, $handler->eligibleApproverRoles($initiator, $payload));

        if ($roles === []) {
            throw new ApprovalException(__('approvals.error.no_approver'));
        }

        $hours = max(1, (int) setting('approvals.expiry_hours', 72));

        $request = DB::transaction(function () use ($type, $initiator, $payload, $summary, $subject, $concerns, $comment, $roles, $hours) {
            $request = ApprovalRequest::create([
                'type' => $type,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'payload' => $payload,
                'summary' => mb_substr($summary, 0, 500),
                'initiator_id' => $initiator->id,
                'initiator_role' => ActingRole::for($initiator)?->value,
                'concerns_user_id' => $concerns?->id,
                'eligible_roles' => $roles,
                'status' => ApprovalRequest::PENDING,
                'initiator_comment' => $comment,
                'expires_at' => now()->addHours($hours),
            ]);

            AuditLogger::record('approval.request.created', $request, [], [
                'type' => $type,
                'eligible_roles' => $roles,
                'expires_at' => (string) $request->expires_at,
            ], ['summary' => $summary], $initiator);

            return $request;
        });

        Notifier::toRoles($roles, 'approval.requested', [
            'message_key' => 'approvals.notify.requested',
            'message_params' => ['summary' => $summary, 'initiator' => $initiator->name],
            'approval_request_id' => $request->id,
        ], exceptUserId: $initiator->id);

        return $request;
    }

    /** @throws ApprovalException */
    public function approve(ApprovalRequest $request, User $approver, ?string $comment = null): ApprovalRequest
    {
        $this->requireReauth();

        $handler = null;
        $failure = null;

        $result = $this->denyAudited($request, $approver, function () use ($request, $approver, $comment, &$handler, &$failure) {
            return DB::transaction(function () use ($request, $approver, $comment, &$handler, &$failure) {
                $locked = ApprovalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
                $this->guardDecision($locked, $approver);

                $handler = $this->registry->for($locked->type);

                try {
                    // Savepoint so a failing handler never leaves half-done work behind.
                    DB::transaction(fn () => $handler->onApproved($locked, $approver));
                } catch (Throwable $e) {
                    $failure = $e;

                    return $locked; // status stays pending
                }

                $locked->forceFill([
                    'status' => ApprovalRequest::APPROVED,
                    'approver_id' => $approver->id,
                    'approver_role' => ActingRole::for($approver)?->value,
                    'decision_comment' => $comment,
                    'decided_at' => now(),
                ])->save();

                AuditLogger::record('approval.request.approved', $locked, ['status' => ApprovalRequest::PENDING], ['status' => ApprovalRequest::APPROVED], [
                    'type' => $locked->type,
                    'comment' => $comment,
                    'initiator_id' => $locked->initiator_id,
                ], $approver);

                return $locked;
            });
        });

        if ($failure !== null) {
            Log::error('Approval handler failed', ['request' => $request->id, 'type' => $request->type, 'error' => $failure->getMessage()]);
            AuditLogger::record('approval.request.failed', $request, [], [], [
                'type' => $request->type,
                'error' => class_basename($failure),
            ], $approver);

            throw ApprovalException::handlerFailed();
        }

        $this->notifyInitiator($result, 'approved');

        return $result->refresh();
    }

    /** @throws ApprovalException */
    public function reject(ApprovalRequest $request, User $approver, string $comment): ApprovalRequest
    {
        if (trim($comment) === '') {
            throw ApprovalException::commentRequired();
        }
        $this->requireReauth();

        $result = $this->denyAudited($request, $approver, fn () => DB::transaction(function () use ($request, $approver, $comment) {
            $locked = ApprovalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->guardDecision($locked, $approver);

            $this->registry->for($locked->type)->onRejected($locked, $approver);

            $locked->forceFill([
                'status' => ApprovalRequest::REJECTED,
                'approver_id' => $approver->id,
                'approver_role' => ActingRole::for($approver)?->value,
                'decision_comment' => $comment,
                'decided_at' => now(),
            ])->save();

            AuditLogger::record('approval.request.rejected', $locked, ['status' => ApprovalRequest::PENDING], ['status' => ApprovalRequest::REJECTED], [
                'type' => $locked->type,
                'comment' => $comment,
            ], $approver);

            return $locked;
        }));

        $this->notifyInitiator($result, 'rejected');

        return $result->refresh();
    }

    /** Runs a decision; a refusal for deciding one's own request is audited OUTSIDE the rolled-back transaction. */
    private function denyAudited(ApprovalRequest $request, User $approver, callable $decide): ApprovalRequest
    {
        try {
            return $decide();
        } catch (ApprovalException $e) {
            if ($request->initiator_id === $approver->id || $request->concerns_user_id === $approver->id) {
                AuditLogger::record('approval.request.denied_own', $request, [], [], ['type' => $request->type], $approver);
            }
            throw $e;
        }
    }

    /** The initiator withdraws a request that is still pending. */
    public function cancel(ApprovalRequest $request, User $actor): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $locked = ApprovalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->initiator_id !== $actor->id) {
                throw ApprovalException::notEligible();
            }
            if (! $locked->isPending()) {
                throw ApprovalException::notPending();
            }

            $locked->forceFill(['status' => ApprovalRequest::CANCELLED, 'decided_at' => now()])->save();
            AuditLogger::record('approval.request.cancelled', $locked, ['status' => ApprovalRequest::PENDING], ['status' => ApprovalRequest::CANCELLED], ['type' => $locked->type], $actor);

            // Rejection hook releases whatever the request was holding (e.g. a pending account).
            $this->registry->for($locked->type)->onRejected($locked, $actor);

            return $locked;
        });
    }

    /** Hourly: pending requests past their expiry become `expired` (E3). */
    public function expireOverdue(): int
    {
        $count = 0;

        ApprovalRequest::query()->pending()->where('expires_at', '<=', now())->orderBy('id')->each(function (ApprovalRequest $r) use (&$count) {
            DB::transaction(function () use ($r, &$count) {
                $locked = ApprovalRequest::whereKey($r->id)->lockForUpdate()->first();
                if (! $locked || ! $locked->isPending()) {
                    return;
                }
                $locked->forceFill(['status' => ApprovalRequest::EXPIRED, 'decided_at' => now()])->save();
                AuditLogger::record('approval.request.expired', $locked, ['status' => ApprovalRequest::PENDING], ['status' => ApprovalRequest::EXPIRED], ['type' => $locked->type]);
                $this->registry->for($locked->type)->onRejected($locked, $locked->initiator);
                $count++;
            });
            $this->notifyInitiator($r->refresh(), 'expired');
        });

        return $count;
    }

    /** Throws unless this approver may decide this request right now. */
    public function guardDecision(ApprovalRequest $request, User $approver): void
    {
        if (! $request->isPending()) {
            throw ApprovalException::notPending();
        }
        if ($request->isExpired()) {
            throw ApprovalException::expired();
        }
        if ($request->initiator_id === $approver->id || $request->concerns_user_id === $approver->id) {
            throw ApprovalException::own();
        }

        $eligible = false;
        foreach ($approver->roleEnums() as $role) {
            if (in_array($role->value, $request->eligible_roles ?? [], true)) {
                $eligible = true;
                break;
            }
        }
        if (! $eligible) {
            throw ApprovalException::notEligible();
        }
    }

    private function requireReauth(): void
    {
        if (! ReAuthentication::consume(self::REAUTH_PURPOSE)) {
            throw ApprovalException::reauthRequired();
        }
    }

    private function notifyInitiator(ApprovalRequest $request, string $outcome): void
    {
        if ($request->initiator) {
            Notifier::toUser($request->initiator, 'approval.'.$outcome, [
                'message_key' => 'approvals.notify.'.$outcome,
                'message_params' => ['summary' => $request->summary],
                'approval_request_id' => $request->id,
            ]);
        }
    }
}
