<?php

namespace Tests\Feature\Authentication;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SecurityControlsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Audit Log Immutability: Updates are prevented
     */
    public function test_audit_logs_cannot_be_updated(): void
    {
        $log = AuditLog::create([
            'action' => 'test_action',
            'event_category' => 'test',
            'description' => 'Test log description',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit log entries are immutable and cannot be updated.');

        $log->description = 'Tampered log';
        $log->save();
    }

    /**
     * Test Audit Log Immutability: Deletions are prevented
     */
    public function test_audit_logs_cannot_be_deleted(): void
    {
        $log = AuditLog::create([
            'action' => 'test_action',
            'event_category' => 'test',
            'description' => 'Test log description',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit log entries are immutable and cannot be deleted.');

        $log->delete();
    }

    /**
     * Test that audit metadata sanitizes passwords and secrets
     */
    public function test_audit_sanitizes_passwords_and_secrets(): void
    {
        $log = \App\Services\AuditService::record(
            action: 'test_scrub',
            category: 'test',
            description: 'Scrub test',
            metadata: [
                'username' => 'test_user',
                'password' => 'SecretPassword123!',
                'two_factor_secret' => '123456',
                'nested' => [
                    'token' => 'abc-xyz',
                    'safe_info' => 'visible',
                ],
            ]
        );

        $this->assertEquals('test_user', $log->metadata['username']);
        $this->assertArrayNotHasKey('password', $log->metadata);
        $this->assertArrayNotHasKey('two_factor_secret', $log->metadata);
        $this->assertArrayNotHasKey('token', $log->metadata['nested']);
        $this->assertEquals('visible', $log->metadata['nested']['safe_info']);
    }

    /**
     * Test System Administrator cannot access business clinical or sales data
     */
    public function test_system_administrator_cannot_access_business_data(): void
    {
        $adminRole = Role::create([
            'name' => UserRole::SYSTEM_ADMINISTRATOR->label(),
            'slug' => UserRole::SYSTEM_ADMINISTRATOR->value,
        ]);

        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $this->assertFalse($admin->canAccessBusinessData());
        $this->assertTrue($admin->hasRole(UserRole::SYSTEM_ADMINISTRATOR));
    }
}

