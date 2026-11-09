<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Approvals\ApprovalRouter;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\DB;

class SettingsUpdateHandler implements ApprovalHandler
{
    public function __construct(private SettingsRepository $settings) {}

    public function type(): string
    {
        return 'settings.update';
    }

    public function eligibleApproverRoles(User $initiator, array $payload): array
    {
        return ApprovalRouter::managementEither();
    }

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $key = $request->payload['key'];
        $old = $this->settings->get($key);
        $new = $request->payload['new'];

        $this->settings->put($key, $new, $approver->id);

        DB::table('setting_histories')->insert([
            'key' => $key,
            'old_value' => json_encode($old),
            'new_value' => json_encode($new),
            'requested_by' => $request->initiator_id,
            'approved_by' => $approver->id,
            'created_at' => now(),
        ]);

        AuditLogger::record('settings.value.updated', null, [$key => $old], [$key => $new], ['approver_id' => $approver->id, 'requested_by' => $request->initiator_id], $approver);
    }

    public function onRejected(ApprovalRequest $request, User $approver): void {}
}
