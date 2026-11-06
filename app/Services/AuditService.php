<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Record an audit log event.
     *
     * @param string $action Specific action code (e.g., login_success, login_failed)
     * @param string $category Category (e.g., authentication, security, authorization)
     * @param string $description Clear description of what happened
     * @param array $metadata Additional context (MUST NEVER contain passwords or raw secrets)
     * @param User|int|null $user The user associated with the event
     */
    public static function record(
        string $action,
        string $category,
        string $description,
        array $metadata = [],
        User|int|null $user = null
    ): AuditLog {
        $userId = null;

        if ($user instanceof User) {
            $userId = $user->id;
        } elseif (is_numeric($user)) {
            $userId = (int) $user;
        } elseif (Auth::check()) {
            $userId = Auth::id();
        }

        // Sanitize metadata to guarantee no credentials, passwords, or secrets are logged
        $sanitizedMetadata = self::sanitizeMetadata($metadata);

        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'event_category' => $category,
            'description' => $description,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent(),
            'session_identifier' => session()->getId(),
            'metadata' => $sanitizedMetadata ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively scrub sensitive keys from metadata.
     */
    protected static function sanitizeMetadata(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'two_factor_secret',
            'code',
            'secret',
            'token',
            'remember_token',
        ];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::sanitizeMetadata($value);
            }
        }

        return $data;
    }
}

