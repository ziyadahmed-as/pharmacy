<?php

namespace App\Approvals;

use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\User;

/**
 * One handler per approval type. Later use cases only implement this interface and
 * register it (README "How to add an approval type"). The engine owns the status
 * change, the transaction, the re-authentication and the audit trail.
 *
 * @uc UC-34 @fr FR-8.7
 */
interface ApprovalHandler
{
    /** Unique type key, `<module>.<entity>.<verb>`, e.g. `user.deactivate`. */
    public function type(): string;

    /**
     * Roles allowed to decide a request of this type.
     *
     * @param  array<string, mixed>  $payload
     * @return list<Role>
     */
    public function eligibleApproverRoles(User $initiator, array $payload): array;

    /** Run the real work. Throw to leave the request pending. */
    public function onApproved(ApprovalRequest $request, User $approver): void;

    public function onRejected(ApprovalRequest $request, User $approver): void;
}
