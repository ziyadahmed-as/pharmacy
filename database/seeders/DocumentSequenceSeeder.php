<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentSequenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sequences = [
            ['type' => 'sale', 'prefix' => 'REC', 'next_value' => 1],
            ['type' => 'receipt', 'prefix' => 'REC', 'next_value' => 1],
            ['type' => 'stock_receipt', 'prefix' => 'REC', 'next_value' => 1],
            ['type' => 'stocktake', 'prefix' => 'STK', 'next_value' => 1],
            ['type' => 'disposal', 'prefix' => 'DSP', 'next_value' => 1],
            ['type' => 'adjustment', 'prefix' => 'ADJ', 'next_value' => 1],
            ['type' => 'return', 'prefix' => 'RET', 'next_value' => 1],
            ['type' => 'refund', 'prefix' => 'RFD', 'next_value' => 1],
            ['type' => 'prescription', 'prefix' => 'RX', 'next_value' => 1],
            ['type' => 'credit_payment', 'prefix' => 'CPR', 'next_value' => 1],
        ];

        foreach ($sequences as $seq) {
            $exists = DB::table('document_sequences')->where('type', $seq['type'])->exists();
            if (! $exists) {
                DB::table('document_sequences')->insert(array_merge($seq, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
}
