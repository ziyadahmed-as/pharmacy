<?php

namespace App\Approvals\Handlers;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\EndUserSessions;

class ChangeRolesHandler extends UserAccountHandler
{
    public function type(): string
    {
        return 'user.roles.update';
    }

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $user = $this->target($request);
        $this->changes->syncRoles($user, $request->payload['new_roles'], $request->initiator, $approver, $request->payload['reason'] ?? null);
        app(EndUserSessions::class)->handle($user); // new permissions apply at next sign-in
    }
}
