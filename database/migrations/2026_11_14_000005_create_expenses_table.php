<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // rent, salaries, utilities, transport, other
            $table->integer('amount'); // signed in santim (negative for reversal)
            $table->date('date');
            $table->text('description');
            $table->string('proof_path')->nullable();
            $table->string('status')->default('posted'); // posted, pending_approval, rejected
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
            $table->index(['category', 'status']);
        });

        $this->createDeleteTrigger();
    }

    public function down(): void
    {
        $this->dropDeleteTrigger();
        Schema::dropIfExists('expenses');
    }

    private function createDeleteTrigger(): void
    {
        $driver = DB::connection()->getDriverName();
        $msg = 'Expenses cannot be deleted; corrections must be made via reversal entries.';

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER expenses_no_delete BEFORE DELETE ON expenses BEGIN SELECT RAISE(ABORT, '{$msg}'); END;");
        } elseif ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER expenses_no_delete BEFORE DELETE ON expenses FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$msg}'");
        }
    }

    private function dropDeleteTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS expenses_no_delete');
    }
};
