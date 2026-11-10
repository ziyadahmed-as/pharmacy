<?php

namespace Database\Seeders;

use App\Models\Till;
use Illuminate\Database\Seeder;

class TillSeeder extends Seeder
{
    public function run(): void
    {
        $tills = [
            ['name' => 'Till 1 - Main Dispensing', 'is_active' => true],
            ['name' => 'Till 2 - OTC Counter', 'is_active' => true],
            ['name' => 'Till 3 - Express / Consultation', 'is_active' => true],
        ];

        foreach ($tills as $t) {
            Till::firstOrCreate(['name' => $t['name']], $t);
        }
    }
}
