<?php

namespace App\Approvals\Handlers;

use App\Actions\Users\UserAccountChanges;
use App\Models\ApprovalRequest;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CredentialRevealService;
use App\Services\PasswordPolicy;
use Illuminate\Support\Str;

class CreateUserHandler extends UserAccountHandler
{
    public function __construct(UserAccountChanges $changes, private CredentialRevealService $reveals)
    {
        parent::__construct($changes);
    }

    public function type(): string
    {
        return 'user.create';
    }

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $user = $this->target($request);
        $staff = Staff::findOrFail($request->payload['staff_id']);

        $temporary = Str::password(14, symbols: false);
        PasswordPolicy::set($user, $temporary, mustChange: true);

        $user->forceFill(['is_active' => true, 'account_status' => User::STATUS_ACTIVE])->save();
        $staff->forceFill(['user_id' => $user->id])->save();

        $this->reveals->store($request->initiator, $user, $temporary);

        AuditLogger::record('user.account.activated', $user, ['account_status' => User::STATUS_PENDING], ['account_status' => User::STATUS_ACTIVE], [
            'staff_id' => $staff->id, 'approver_id' => $approver->id,
        ]);
        $this->changes->syncRoles($user, $request->payload['roles'], $request->initiator, $approver, 'account created');
    }

    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        $user = User::find($request->payload['user_id'] ?? 0);
        if ($user && $user->account_status === User::STATUS_PENDING) {
            // Free the username; the row stays (no hard delete).
            $user->forceFill(['account_status' => User::STATUS_REJECTED, 'username' => $user->username.'.rejected.'.$user->id])->save();
            AuditLogger::record('user.account.rejected', $user, [], [], ['approver_id' => $approver->id]);
        }
    }
}
