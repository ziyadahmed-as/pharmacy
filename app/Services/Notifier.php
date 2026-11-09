<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Database notifications to users or to every active holder of a role (FR-9.1).
 * `dedupe_key` makes alerts idempotent (UC-39 licence alerts).
 */
final class Notifier
{
    /**
     * @param  array<string, mixed>  $data
     * @return int number of notifications created
     */
    public static function toRoles(array $roles, string $type, array $data, ?string $dedupeKey = null, ?int $exceptUserId = null): int
    {
        $values = array_map(fn ($r) => $r instanceof Role ? $r->value : $r, $roles);

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', $values))
            ->when($exceptUserId, fn ($q) => $q->where('users.id', '!=', $exceptUserId))
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $count += self::toUser($user, $type, $data, $dedupeKey) ? 1 : 0;
        }

        return $count;
    }

    /** @param  array<string, mixed>  $data */
    public static function toUser(User $user, string $type, array $data, ?string $dedupeKey = null): bool
    {
        if ($dedupeKey !== null) {
            $exists = DB::table('notifications')
                ->where('notifiable_type', $user->getMorphClass())
                ->where('notifiable_id', $user->getKey())
                ->where('type', $type)
                ->where('data', 'like', '%"dedupe":"'.addcslashes($dedupeKey, '%_\\').'"%')
                ->exists();
            if ($exists) {
                return false;
            }
            $data['dedupe'] = $dedupeKey;
        }

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }
}
