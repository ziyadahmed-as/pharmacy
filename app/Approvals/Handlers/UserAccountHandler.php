<?php

namespace App\Approvals\Handlers;

use App\Actions\Users\UserAccountChanges;
use App\Approvals\ApprovalHandler;
use App\Approvals\ApprovalRouter;
use App\Models\ApprovalRequest;
use App\Models\User;

/** Base for UC-35 account handlers: PM approves; Owner if it concerns the PM; PM if the Owner. */
abstract class UserAccountHandler implements ApprovalHandler
{
    public function __construct(protected UserAccountChanges $changes) {}

    public function eligibleApproverRoles(User $initiator, array $payload): array
    {
        $target = isset($payload['user_id']) ? User::find($payload['user_id']) : null;

        return ApprovalRouter::forAccountChange($target, $payload['roles'] ?? $payload['new_roles'] ?? null);
    }

    public function onRejected(ApprovalRequest $request, User $approver): void {}

    protected function target(ApprovalRequest $r): User
    {
        return User::findOrFail($r->payload['user_id']);
    }
}
