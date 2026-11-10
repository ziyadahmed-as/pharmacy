<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\Staff;
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
                'email' => 'admin@pms.local',
                'password' => 'password',
                'role' => RoleEnum::SystemAdmin->value,
            ],
            [
                'name' => 'System Administrator',
                'username' => 'sysadmin',
                'email' => 'sysadmin@pms.local',
                'password' => 'password',
                'role' => RoleEnum::SystemAdmin->value,
            ],
            [
                'name' => 'Pharmacy Owner',
                'username' => 'owner',
                'email' => 'owner@pms.local',
                'password' => 'password',
                'role' => RoleEnum::Owner->value,
            ],
            [
                'name' => 'Pharmacy Owner',
                'username' => 'owner_user',
                'email' => 'owner_user@pms.local',
                'password' => 'password',
                'role' => RoleEnum::Owner->value,
            ],
            [
                'name' => 'Pharmacy Manager',
                'username' => 'manager',
                'email' => 'manager@pms.local',
                'password' => 'password',
                'role' => RoleEnum::PharmacyManager->value,
            ],
            [
                'name' => 'Pharmacy Manager',
                'username' => 'manager_user',
                'email' => '
                ',
                'password' => 'password',
                'role' => RoleEnum::PharmacyManager->value,
            ],
            [
                'name' => 'Pharmacy Staff',
                'username' => 'staff',
                'email' => 'staff@pms.local',
                'password' => 'password',
                'role' => RoleEnum::PharmacyStaff->value,
            ],
            [
                'name' => 'Pharmacy Staff',
                'username' => 'staff_user',
                'email' => 'staff_user@pms.local',
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
                if ($role && ! $user->roles()->where('role_id', $role->id)->exists()) {
                    $user->roles()->attach($role->id);
                }
            }

            // Create Staff record for non-admin accounts if not already linked
            if ($data['role'] !== RoleEnum::SystemAdmin->value && ! Staff::where('user_id', $user->id)->exists()) {
                Staff::create([
                    'name' => $data['name'],
                    'phone' => '+251911000000',
                    'title' => ucwords(str_replace('_', ' ', $data['role'])),
                    'hire_date' => now()->subYear()->format('Y-m-d'),
                    'status' => 'active',
                    'user_id' => $user->id,
                ]);
            }

            // Create Staff record for non-admin accounts if not already linked
            if ($data['role'] !== RoleEnum::SystemAdmin->value && ! Staff::where('user_id', $user->id)->exists()) {
                Staff::create([
                    'name' => $data['name'],
                    'phone' => '+251911000000',
                    'title' => ucwords(str_replace('_', ' ', $data['role'])),
                    'hire_date' => now()->subYear()->format('Y-m-d'),
                    'status' => 'active',
                    'user_id' => $user->id,
                ]);
            }
        }
    }
}
