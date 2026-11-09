<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first SA, Owner and Pharmacy Manager from .env values.
 * No password is ever hard-coded: if a password variable is empty a random one is
 * generated and printed once on the console. Accounts must change it on first login.
 * Safe to run again: existing usernames are left untouched.
 */
class BootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $accounts = [
            [RoleEnum::SystemAdmin, 'sa'],
            [RoleEnum::Owner, 'owner'],
            [RoleEnum::PharmacyManager, 'pm'],
        ];

        foreach ($accounts as [$role, $prefix]) {
            $username = config("pms.bootstrap.{$prefix}_username");
            if (User::where('username', $username)->exists()) {
                continue;
            }

            $password = config("pms.bootstrap.{$prefix}_password");
            $generated = false;
            if (! $password) {
                $password = Str::password(16, symbols: false);
                $generated = true;
            }

            $user = User::create([
                'name' => config("pms.bootstrap.{$prefix}_name"),
                'username' => $username,
                'password' => $password,           // hashed by the model cast
                'is_active' => true,
                'account_status' => User::STATUS_ACTIVE,
                'must_change_password' => true,
            ]);
            $user->roles()->attach(Role::where('slug', $role->value)->value('id'));

            if ($generated && $this->command) {
                $this->command->warn("Generated password for {$role->value} '{$username}': {$password}  (shown once)");
            }
        }
    }
}
