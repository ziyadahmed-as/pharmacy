<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S0 foundation + UC-33 completion:
 *  - audit_logs gains role_used, terminal, auditable morph, old/new values
 *  - append-only enforcement by database triggers (BR-15, AGENTS rule 6)
 *  - users.locale, users.password_changed_at, password_histories
 *  - role slug system_administrator -> system_admin (AGENTS section 3)
 *  - notifications table (Notifier, database channel)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('role_used', 40)->nullable()->after('user_id');
            $table->string('terminal', 255)->nullable()->after('role_used');
            $table->string('auditable_type')->nullable()->after('action');
            $table->string('auditable_id', 64)->nullable()->after('auditable_type');
            $table->json('old_values')->nullable()->after('auditable_id');
            $table->json('new_values')->nullable()->after('old_values');
            $table->index('role_used');
            $table->index(['auditable_type', 'auditable_id']);
        });

        // description was mandatory in the first iteration; the new contract
        // (action, subject, old, new) does not require prose.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->string('event_category', 50)->nullable()->change();
        });

        $this->createAppendOnlyTriggers();

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('email');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
        });

        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password_hash');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'id']);
        });

        DB::table('roles')->where('slug', 'system_administrator')->update([
            'slug' => 'system_admin',
            'name' => 'System Administrator',
        ]);

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $this->dropAppendOnlyTriggers();
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('password_histories');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['locale', 'password_changed_at']));
        DB::table('roles')->where('slug', 'system_admin')->update(['slug' => 'system_administrator']);
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['role_used']);
            $table->dropIndex(['auditable_type', 'auditable_id']);
            $table->dropColumn(['role_used', 'terminal', 'auditable_type', 'auditable_id', 'old_values', 'new_values']);
        });
    }

    private function createAppendOnlyTriggers(): void
    {
        $driver = DB::connection()->getDriverName();
        $msg = 'audit_logs is append-only: updates and deletes are forbidden';

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, '{$msg}'); END;");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, '{$msg}'); END;");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$msg}'");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$msg}'");
        }
    }

    private function dropAppendOnlyTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
    }
};
