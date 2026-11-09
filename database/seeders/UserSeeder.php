<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'System Admin',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => 'password',
                'role' => RoleEnum::SystemAdmin->value,
            ],
            [
                'name' => 'Pharmacy Owner',
                'username' => 'owner_user',
                'email' => 'owner@example.com',
                'password' => 'password',
                'role' => RoleEnum::Owner->value,
            ],
            [
                'name' => 'Pharmacy Manager',
                'username' => 'manager_user',
                'email' => 'manager@example.com',
                'password' => 'password',
                'role' => RoleEnum::PharmacyManager->value,
            ],
            [
                'name' => 'Pharmacy Staff',
                'username' => 'staff_user',
                'email' => 'staff@example.com',
                'password' => 'password',
                'role' => RoleEnum::PharmacyStaff->value,
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['username' => $data['username']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'is_active' => true,
                    'account_status' => User::STATUS_ACTIVE,
                    'must_change_password' => false,
                ]
            );

            if (isset($data['role'])) {
                $role = Role::where('slug', $data['role'])->first();
                if ($role && !$user->roles()->where('role_id', $role->id)->exists()) {
                    $user->roles()->attach($role->id);
                }
            }
        }
    }
}
