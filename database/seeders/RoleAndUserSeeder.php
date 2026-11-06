<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Roles
        $staffRole = Role::firstOrCreate(
            ['slug' => UserRole::PHARMACY_STAFF->value],
            [
                'name' => UserRole::PHARMACY_STAFF->label(),
                'description' => 'Frontline pharmacy employees (dispensing, inventory, sales).',
            ]
        );

        $managerRole = Role::firstOrCreate(
            ['slug' => UserRole::PHARMACY_MANAGER->value],
            [
                'name' => UserRole::PHARMACY_MANAGER->label(),
                'description' => 'Supervises pharmacy operations and staff.',
            ]
        );

        $ownerRole = Role::firstOrCreate(
            ['slug' => UserRole::OWNER->value],
            [
                'name' => UserRole::OWNER->label(),
                'description' => 'Final authority with executive oversight.',
            ]
        );

        $adminRole = Role::firstOrCreate(
            ['slug' => UserRole::SYSTEM_ADMINISTRATOR->value],
            [
                'name' => UserRole::SYSTEM_ADMINISTRATOR->label(),
                'description' => 'Technical system management. Strictly prohibited from accessing business data.',
            ]
        );

        // 2. Seed Default Role Accounts for Testing & Development
        // Staff
        $staffUser = User::firstOrCreate(
            ['username' => 'staff_john'],
            [
                'name' => 'John Pharmacist',
                'email' => 'staff@pms.local',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'failed_login_attempts' => 0,
            ]
        );
        $staffUser->roles()->syncWithoutDetaching([$staffRole->id]);

        // Manager (MFA enabled)
        $managerUser = User::firstOrCreate(
            ['username' => 'manager_sarah'],
            [
                'name' => 'Sarah Manager',
                'email' => 'manager@pms.local',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'failed_login_attempts' => 0,
                'two_factor_enabled' => true,
                'two_factor_secret' => '123456',
            ]
        );
        $managerUser->roles()->syncWithoutDetaching([$managerRole->id]);

        // Owner
        $ownerUser = User::firstOrCreate(
            ['username' => 'owner_david'],
            [
                'name' => 'David Owner',
                'email' => 'owner@pms.local',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'failed_login_attempts' => 0,
            ]
        );
        $ownerUser->roles()->syncWithoutDetaching([$ownerRole->id]);

        // System Administrator (MFA enabled)
        $adminUser = User::firstOrCreate(
            ['username' => 'admin_alex'],
            [
                'name' => 'Alex Administrator',
                'email' => 'admin@pms.local',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'failed_login_attempts' => 0,
                'two_factor_enabled' => true,
                'two_factor_secret' => '123456',
            ]
        );
        $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
    }
}

