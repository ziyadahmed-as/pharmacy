<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Single entry point for the append-only audit trail (BR-15, BR-7).
 *
 * Action keys follow `<module>.<entity>.<verb>` in lower case, for example
 * `auth.login.failed`, `approval.request.approved`, `settings.value.updated`.
 *
 * @uc S0 @br BR-15 @ac AC-19
 */
final class AuditLogger
{
    /** Keys whose values are never written to the log (S0 step 3). */
    public const MASKED_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password', 'temporary_password',
        'token', 'remember_token', 'secret', 'two_factor_secret', 'code',
        'id_number',
        // health fields (BRD FR-5.6, BR-06)
        'allergies', 'diagnosis', 'medical_history', 'health_notes', 'medical_conditions', 'chronic_conditions',
    ];

    public const MASK = '[masked]';

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $metadata  extra context (note, target ids, ...), masked the same way
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        array $metadata = [],
        User|int|null $user = null,
        ?string $description = null,
    ): AuditLog {
        $userModel = $user instanceof User ? $user : (is_int($user) ? User::find($user) : Auth::user());
        $userId = $userModel?->getKey();

        $roleUsed = $userModel ? ActingRole::for($userModel)?->value : null;

        $parts = explode('.', $action);

        return AuditLog::create([
            'user_id' => $userId,
            'role_used' => $roleUsed,
            'terminal' => self::terminal(),
            'action' => $action,
            'event_category' => $parts[0] ?? null,
            'auditable_type' => $subject ? $subject->getMorphClass() : null,
            'auditable_id' => $subject?->getKey() !== null ? (string) $subject->getKey() : null,
            'old_values' => $old === [] ? null : self::mask($old),
            'new_values' => $new === [] ? null : self::mask($new),
            'description' => $description,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent(),
            'session_identifier' => self::sessionId(),
            'metadata' => $metadata === [] ? null : self::mask($metadata),
            'created_at' => now(),
        ]);
    }

    /** @param  array<string|int, mixed>  $data */
    public static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::MASKED_KEYS, true)) {
                $data[$key] = self::MASK;

                continue;
            }
            if (is_array($value)) {
                $data[$key] = self::mask($value);
            }
        }

        return $data;
    }

    private static function terminal(): string
    {
        $ip = Request::ip() ?? 'cli';
        $ua = (string) Request::userAgent();

        return trim($ip.' '.mb_substr($ua, 0, 80));
    }

    private static function sessionId(): ?string
    {
        try {
            return session()->isStarted() ? session()->getId() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
