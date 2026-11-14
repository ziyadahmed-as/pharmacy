<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Annual Leave',
                'code' => 'annual',
                'days_per_year' => 16,
                'is_paid' => true,
                'requires_approval' => true,
            ],
            [
                'name' => 'Sick Leave',
                'code' => 'sick',
                'days_per_year' => 20,
                'is_paid' => true,
                'requires_approval' => true,
            ],
            [
                'name' => 'Compassionate Leave',
                'code' => 'compassionate',
                'days_per_year' => 3,
                'is_paid' => true,
                'requires_approval' => true,
            ],
            [
                'name' => 'Maternity / Paternity Leave',
                'code' => 'maternity',
                'days_per_year' => 120,
                'is_paid' => true,
                'requires_approval' => true,
            ],
            [
                'name' => 'Unpaid Leave',
                'code' => 'unpaid',
                'days_per_year' => 0,
                'is_paid' => false,
                'requires_approval' => true,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
