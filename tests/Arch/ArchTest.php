<?php

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Staff;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\RoleCombinationRule;
use App\Support\SettingsCatalogue;
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

it('refuses StockMovement updates and deletes (append-only ledger)', function () {
    $user = User::factory()->create();
    $product = Product::create([
        'brand' => 'Arch Test Product',
        'generic_name' => 'Arch Test',
        'classification' => 'otc',
        'is_active' => true,
    ]);
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'ARCH-B1',
        'expiry_date' => now()->addYear()->format('Y-m-d'),
    ]);
    $movement = new StockMovement([
        'batch_id' => $batch->id,
        'type' => 'receipt',
        'quantity' => 10,
        'balance_after' => 10,
        'stock_status' => 'sellable',
        'source_type' => 'stock_receipt',
        'source_id' => 1,
        'user_id' => $user->id,
        'created_at' => now(),
    ]);
    $movement->saveQuietly();

    expect(fn () => $movement->update(['quantity' => 20]))->toThrow(RuntimeException::class);
    expect(fn () => $movement->delete())->toThrow(RuntimeException::class);
});

it('only allows StockMovementService to write BatchStock across the application', function () {
    // Scan all PHP files in app/ for direct creation/updating of BatchStock outside of StockMovementService
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));
    $violators = [];

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getRealPath();
        if (str_contains($path, 'StockMovementService.php') || str_contains($path, 'BatchStock.php') || str_contains($path, 'Batch.php')) {
            continue;
        }

        $contents = file_get_contents($path);
        if (preg_match('/BatchStock::(create|update|insert|upsert|firstOrCreate)/i', $contents) ||
            preg_match('/\$batchStock->(save|update|delete)/i', $contents)) {
            $violators[] = $file->getFilename();
        }
    }

    expect($violators)->toBe([]);
});

it('has no discount, price-override or tax field in sales schema, API or settings (AC-36, BR-20)', function () {
    // 1. Database schema: sales, sale_lines, payments have no discount, tax or price override columns
    foreach (['sales', 'sale_lines', 'payments'] as $table) {
        $columns = Schema::getColumnListing($table);
        foreach (['discount', 'tax', 'vat', 'price_override', 'override_price', 'custom_price'] as $forbidden) {
            expect($columns)->not->toContain($forbidden, "Forbidden column {$forbidden} found on {$table}");
        }
    }

    // 2. Settings: no settings key mentioning discount or tax
    $allSettings = array_keys(SettingsCatalogue::all());
    foreach ($allSettings as $key) {
        expect(str_contains(strtolower($key), 'discount'))->toBeFalse("Forbidden setting {$key}");
        expect(str_contains(strtolower($key), 'tax'))->toBeFalse("Forbidden setting {$key}");
    }
});
