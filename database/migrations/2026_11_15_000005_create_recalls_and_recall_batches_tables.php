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
        // 1. Create recalls table
        Schema::create('recalls', function (Blueprint $table) {
            $table->id();
            $table->string('recall_no')->unique();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('source_reference')->nullable();
            $table->text('reason');
            $table->date('recall_date');
            $table->string('severity')->default('class_ii'); // class_i, class_ii, class_iii
            $table->string('status')->default('open'); // open, closed
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        AppendOnly::addCheckConstraint(
            'recalls',
            'chk_recalls_status',
            "status IN ('open', 'closed')",
            "NEW.status IN ('open', 'closed')"
        );

        // 2. Create recall_batches table
        Schema::create('recall_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recall_id')->constrained('recalls')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->integer('quantity_found')->default(0);
            $table->integer('quantity_quarantined')->default(0);
            $table->string('action')->nullable(); // quarantined, returned_to_supplier, disposed
            $table->timestamps();

            $table->unique(['recall_id', 'batch_id']);
        });

        // 3. Ensure 'recall' sequence exists in document_sequences
        $exists = DB::table('document_sequences')->where('type', 'recall')->exists();
        if (! $exists) {
            DB::table('document_sequences')->insert([
                'type' => 'recall',
                'prefix' => 'RCL',
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        AppendOnly::dropCheckConstraint('recalls', 'chk_recalls_status');
        Schema::dropIfExists('recall_batches');
        Schema::dropIfExists('recalls');
        DB::table('document_sequences')->where('type', 'recall')->delete();
    }
};
