<?php

use App\Support\AppendOnly;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create prescribers table
        Schema::create('prescribers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('licence_number')->nullable()->unique();
            $table->string('facility')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Add prescriber_id to prescriptions
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('prescriber_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('prescribers')
                ->restrictOnDelete();
        });

        // 3. Add prescriber_id to controlled_drug_register
        Schema::table('controlled_drug_register', function (Blueprint $table) {
            $table->foreignId('prescriber_id')
                ->nullable()
                ->after('second_person_id')
                ->constrained('prescribers')
                ->restrictOnDelete();
        });

        // 4. Migrate existing names into prescribers (merge duplicates by normalised name)
        $existingPrescriptions = DB::table('prescriptions')
            ->select('id', 'prescriber_name', 'prescriber_registration_no')
            ->whereNotNull('prescriber_name')
            ->where('prescriber_name', '!=', '')
            ->get();

        $prescribersByNorm = [];
        $assignedLicences = [];

        foreach ($existingPrescriptions as $p) {
            $norm = trim(mb_strtolower($p->prescriber_name));
            if ($norm === '') {
                continue;
            }

            if (! isset($prescribersByNorm[$norm])) {
                $licence = ! empty($p->prescriber_registration_no) ? trim($p->prescriber_registration_no) : null;
                if ($licence && isset($assignedLicences[$licence])) {
                    $licence = null;
                }
                if ($licence) {
                    $assignedLicences[$licence] = true;
                }

                $prescriberId = DB::table('prescribers')->insertGetId([
                    'name' => trim($p->prescriber_name),
                    'licence_number' => $licence,
                    'facility' => null,
                    'phone' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $prescribersByNorm[$norm] = $prescriberId;
            }

            DB::table('prescriptions')
                ->where('id', $p->id)
                ->update(['prescriber_id' => $prescribersByNorm[$norm]]);
        }

        // Migrate controlled_drug_register prescriber_id
        $cdEntries = DB::table('controlled_drug_register')
            ->select('id', 'prescriber_name', 'sale_id')
            ->get();

        foreach ($cdEntries as $entry) {
            $prescriberId = null;
            if (! empty($entry->prescriber_name)) {
                $norm = trim(mb_strtolower($entry->prescriber_name));
                $prescriberId = $prescribersByNorm[$norm] ?? null;
            }

            if (! $prescriberId && ! empty($entry->sale_id)) {
                $rxId = DB::table('sales')->where('id', $entry->sale_id)->value('prescription_id');
                if ($rxId) {
                    $prescriberId = DB::table('prescriptions')->where('id', $rxId)->value('prescriber_id');
                }
            }

            if ($prescriberId) {
                DB::table('controlled_drug_register')
                    ->where('id', $entry->id)
                    ->update(['prescriber_id' => $prescriberId]);
            }
        }

        // 5. Re-apply AppendOnly immutability trigger on controlled_drug_register
        // (SQLite rebuild during column addition drops table triggers)
        AppendOnly::create('controlled_drug_register', blockUpdate: true, blockDelete: true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('controlled_drug_register', function (Blueprint $table) {
            $table->dropForeign(['prescriber_id']);
            $table->dropColumn('prescriber_id');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropForeign(['prescriber_id']);
            $table->dropColumn('prescriber_id');
        });

        Schema::dropIfExists('prescribers');

        AppendOnly::create('controlled_drug_register', blockUpdate: true, blockDelete: true);
    }
};
