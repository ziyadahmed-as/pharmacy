<?php

namespace App\Approvals\Handlers;

use App\Models\ApprovalRequest;
use App\Models\User;

class DeactivateUserHandler extends UserAccountHandler
{
    public function type(): string
    {
        return 'user.deactivate';
    }

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $this->changes->deactivate($this->target($request), $request->payload['reason'] ?? null, $approver);
    }
}
