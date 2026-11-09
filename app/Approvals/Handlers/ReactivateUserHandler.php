<?php

namespace App\Approvals\Handlers;

use App\Models\ApprovalRequest;
use App\Models\User;

class ReactivateUserHandler extends UserAccountHandler
{
    public function type(): string
    {
        return 'user.reactivate';
    }

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $this->changes->reactivate($this->target($request), $request->payload['reason'] ?? null, $approver);
    }
}
