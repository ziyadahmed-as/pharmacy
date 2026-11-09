<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * Seeds the four roles. Permissions come from App\Support\Permissions (the BRD
 * matrix), where only Stage 1 permissions are active. Idempotent.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $descriptions = [
            RoleEnum::SystemAdmin->value => 'Technical administration. Never sees patient, prescription, sales, cost or financial data.',
            RoleEnum::Owner->value => 'Business owner with final authority.',
            RoleEnum::PharmacyManager->value => 'Supervises operations, staff and approvals.',
            RoleEnum::PharmacyStaff->value => 'Pharmacists and technicians: dispensing, sales and stock counts.',
        ];

        foreach (RoleEnum::cases() as $role) {
            Role::updateOrCreate(
                ['slug' => $role->value],
                ['name' => ucwords(str_replace('_', ' ', $role->value)), 'description' => $descriptions[$role->value]]
            );
        }

        // Fail loudly if the matrix references nothing: a safeguard against an empty registry.
        foreach (RoleEnum::cases() as $role) {
            if (Permissions::forRole($role) === []) {
                throw new \RuntimeException("Role {$role->value} has no active permissions.");
            }
        }
    }
}
