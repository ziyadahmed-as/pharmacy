<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes a user's rows from `sessions` so open browsers are logged out on the
 * next request (UC-33 step 8). Used by UC-35, UC-36 and UC-39.
 */
final class EndUserSessions
{
    public function __invoke(User $user): int
    {
        return $this->handle($user);
    }

    public function handle(User $user): int
    {
        if (! Schema::hasTable('sessions')) {
            return 0;
        }

        return DB::table('sessions')->where('user_id', $user->getKey())->delete();
    }
}
