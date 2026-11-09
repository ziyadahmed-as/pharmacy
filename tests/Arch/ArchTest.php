<?php

use App\Models\AuditLog;
use App\Models\Staff;
use App\Services\AuditLogger;
use App\Support\RoleCombinationRule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** S0 step 9 and AGENTS.md section 10. */
it('has no PurchaseOrder, Supplier, Discount or Tax model class', function () {
    foreach (['PurchaseOrder', 'Supplier', 'Discount', 'Tax'] as $name) {
        expect(class_exists("App\\Models\\{$name}"))->toBeFalse($name);
    }
});

it('refuses AuditLog updates and deletes through Eloquent', function () {
    $log = AuditLogger::record('test.entry.created');

    expect(fn () => $log->update(['action' => 'x']))->toThrow(RuntimeException::class);
    expect(fn () => $log->delete())->toThrow(RuntimeException::class);
    expect(fn () => $log->forceDelete())->toThrow(RuntimeException::class);
    expect(fn () => AuditLog::destroy($log->id))->toThrow(RuntimeException::class);
    expect(fn () => AuditLog::truncate())->toThrow(RuntimeException::class);
});

it('refuses AuditLog updates and deletes through the query builder and raw SQL (database trigger)', function () {
    $log = AuditLogger::record('test.entry.created');

    expect(fn () => AuditLog::query()->where('id', $log->id)->update(['action' => 'tampered']))->toThrow(QueryException::class);
    expect(fn () => AuditLog::query()->where('id', $log->id)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'x']))->toThrow(QueryException::class);
    expect(fn () => DB::statement('DELETE FROM audit_logs'))->toThrow(QueryException::class);

    expect(AuditLog::find($log->id)->action)->toBe('test.entry.created');
});

it('never exposes an update or delete route for the audit log', function () {
    foreach (app('router')->getRoutes() as $route) {
        if (str_contains($route->uri(), 'audit')) {
            expect($route->methods())->not->toContain('PUT')->not->toContain('PATCH')->not->toContain('DELETE');
        }
    }
});

it('never hard-deletes staff', function () {
    $staff = Staff::factory()->create();
    expect(fn () => $staff->delete())->toThrow(RuntimeException::class);
});

it('masks passwords, tokens, secrets, ID numbers and health fields in audit entries', function () {
    $log = AuditLogger::record('test.entry.created', null, ['password' => 'a'], ['id_number' => '123', 'allergies' => 'penicillin', 'name' => 'ok'], ['token' => 't', 'nested' => ['secret' => 's']]);

    expect($log->old_values['password'])->toBe('[masked]')
        ->and($log->new_values['id_number'])->toBe('[masked]')
        ->and($log->new_values['allergies'])->toBe('[masked]')
        ->and($log->new_values['name'])->toBe('ok')
        ->and($log->metadata['token'])->toBe('[masked]')
        ->and($log->metadata['nested']['secret'])->toBe('[masked]');
});

it('keeps the SA role exclusive (C-4)', function () {
    expect(RoleCombinationRule::allows(['system_admin']))->toBeTrue()
        ->and(RoleCombinationRule::allows(['system_admin', 'owner']))->toBeFalse()
        ->and(RoleCombinationRule::allows(['owner', 'pharmacy_manager']))->toBeTrue()
        ->and(RoleCombinationRule::allows([]))->toBeFalse();
});
