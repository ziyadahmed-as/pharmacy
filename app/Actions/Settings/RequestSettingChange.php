<?php

namespace App\Actions\Settings;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\SettingsRepository;
use App\Support\SettingsCatalogue;
use App\Support\SettingValidator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The SA enters a value; it is NOT live until the PM or Owner approves (UC-38 step 3).
 *
 * @uc UC-38 @fr FR-9.4,FR-9.5,FR-9.7 @ac AC-31
 */
class RequestSettingChange
{
    public function __construct(private ApprovalService $approvals, private SettingsRepository $settings) {}

    /** @throws ValidationException */
    public function execute(User $sa, string $key, mixed $raw, ?string $reason = null): ApprovalRequest
    {
        Gate::forUser($sa)->authorize('settings.edit');

        $def = SettingsCatalogue::find($key) ?? throw ValidationException::withMessages(['key' => __('settings.error.unknown')]);
        $new = SettingValidator::normalise($key, $raw);
        $old = $this->settings->get($key);

        $pending = ApprovalRequest::pending()->where('type', 'settings.update')->where('payload->key', $key)->exists();
        if ($pending) {
            throw ValidationException::withMessages(['value' => __('settings.error.pending_exists')]);
        }

        return $this->approvals->request(
            'settings.update', $sa,
            ['key' => $key, 'group' => $def['group'], 'old' => $old, 'new' => $new],
            __('settings.summary', ['key' => $key]),
            null, null, $reason,
        );
    }
}
