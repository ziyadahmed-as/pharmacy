<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('id');
            $table->string('email')->nullable()->change();
            $table->boolean('is_active')->default(true)->after('password');
            $table->unsignedInteger('failed_login_attempts')->default(0)->after('is_active');
            $table->timestamp('locked_at')->nullable()->after('failed_login_attempts');
            $table->boolean('must_change_password')->default(false)->after('locked_at');
            $table->boolean('two_factor_enabled')->default(false)->after('must_change_password');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_secret');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            $table->index(['username', 'is_active']);
            $table->index('locked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['username', 'is_active']);
            $table->dropIndex(['locked_at']);
            $table->dropColumn([
                'username',
                'is_active',
                'failed_login_attempts',
                'locked_at',
                'must_change_password',
                'two_factor_enabled',
                'two_factor_secret',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};

