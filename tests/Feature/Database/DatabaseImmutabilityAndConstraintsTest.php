<?php

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Till;
use App\Models\User;
use App\Support\AppendOnly;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

function createTestProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Amoxicillin '.Str::random(5),
        'generic_name' => 'Amoxicillin',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'capsule',
        'is_active' => true,
        'loose_allowed' => true,
    ], $attributes));
}

function createTestBatch(Product $product, array $attributes = []): Batch
{
    return Batch::create(array_merge([
        'product_id' => $product->id,
        'batch_no' => 'B-'.strtoupper(Str::random(6)),
        'expiry_date' => Carbon::now()->addYear()->format('Y-m-d'),
        'unit_cost' => 500,
    ], $attributes));
}

test('audit_logs forbids raw SQL update and delete', function () {
    $logId = DB::table('audit_logs')->insertGetId([
        'action' => 'test.action',
        'user_id' => $this->user->id,
        'created_at' => now(),
    ]);

    // Raw UPDATE must throw QueryException
    expect(fn () => DB::table('audit_logs')->where('id', $logId)->update(['action' => 'hacked.action']))
        ->toThrow(QueryException::class);

    // Raw DELETE must throw QueryException
    expect(fn () => DB::table('audit_logs')->where('id', $logId)->delete())
        ->toThrow(QueryException::class);
});

test('controlled_drug_register forbids raw SQL update and delete', function () {
    $product = createTestProduct(['classification' => 'controlled']);
    $batch = createTestBatch($product);

    $regId = DB::table('controlled_drug_register')->insertGetId([
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'entry_type' => 'receipt',
        'quantity' => 10,
        'running_balance' => 10,
        'user_id' => $this->user->id,
        'created_at' => now(),
    ]);

    expect(fn () => DB::table('controlled_drug_register')->where('id', $regId)->update(['quantity' => 99]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('controlled_drug_register')->where('id', $regId)->delete())
        ->toThrow(QueryException::class);
});

test('stock_movements ledger forbids raw SQL update and delete', function () {
    $product = createTestProduct();
    $batch = createTestBatch($product);

    $movementId = DB::table('stock_movements')->insertGetId([
        'batch_id' => $batch->id,
        'type' => 'receipt',
        'quantity' => 25,
        'balance_after' => 25,
        'stock_status' => 'sellable',
        'source_type' => 'App\\Models\\Batch',
        'source_id' => $batch->id,
        'user_id' => $this->user->id,
        'created_at' => now(),
    ]);

    expect(fn () => DB::table('stock_movements')->where('id', $movementId)->update(['quantity' => 50]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('stock_movements')->where('id', $movementId)->delete())
        ->toThrow(QueryException::class);
});

test('approval_requests allows updates but forbids delete', function () {
    $reqId = DB::table('approval_requests')->insertGetId([
        'initiator_id' => $this->user->id,
        'type' => 'price.change',
        'status' => 'pending',
        'summary' => 'Test request',
        'payload' => json_encode(['foo' => 'bar']),
        'eligible_roles' => json_encode(['owner']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Update is allowed
    DB::table('approval_requests')->where('id', $reqId)->update(['status' => 'approved']);
    expect(DB::table('approval_requests')->where('id', $reqId)->value('status'))->toBe('approved');

    // Delete is forbidden
    expect(fn () => DB::table('approval_requests')->where('id', $reqId)->delete())
        ->toThrow(QueryException::class);
});

test('sales table permits updating only status and reprint_count, forbids delete and other columns once completed', function () {
    $till = Till::create(['name' => 'POS-1', 'is_active' => true]);
    $shift = Shift::create([
        'till_id' => $till->id,
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'opening_float' => 1000,
        'status' => 'open',
    ]);

    $saleId = DB::table('sales')->insertGetId([
        'receipt_no' => 'REC-TEST-001',
        'shift_id' => $shift->id,
        'user_id' => $this->user->id,
        'status' => 'completed',
        'total' => 5000,
        'idempotency_key' => Str::uuid()->toString(),
        'reprint_count' => 0,
        'completed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Allowed updates on completed sale: status and reprint_count
    DB::table('sales')->where('id', $saleId)->update(['reprint_count' => 1]);
    expect(DB::table('sales')->where('id', $saleId)->value('reprint_count'))->toBe(1);

    DB::table('sales')->where('id', $saleId)->update(['status' => 'voided']);
    expect(DB::table('sales')->where('id', $saleId)->value('status'))->toBe('voided');

    // Forbidden updates on completed/voided sale: total, shift_id, user_id, receipt_no, etc.
    expect(fn () => DB::table('sales')->where('id', $saleId)->update(['total' => 99999]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('sales')->where('id', $saleId)->update(['receipt_no' => 'REC-CHANGED']))
        ->toThrow(QueryException::class);

    // Delete is forbidden once completed
    expect(fn () => DB::table('sales')->where('id', $saleId)->delete())
        ->toThrow(QueryException::class);
});

test('sale_lines and payments cannot be updated or deleted once sale is completed', function () {
    $till = Till::create(['name' => 'POS-2', 'is_active' => true]);
    $shift = Shift::create([
        'till_id' => $till->id,
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'opening_float' => 1000,
        'status' => 'open',
    ]);

    $product = createTestProduct();
    $batch = createTestBatch($product);

    $saleId = DB::table('sales')->insertGetId([
        'receipt_no' => 'REC-TEST-002',
        'shift_id' => $shift->id,
        'user_id' => $this->user->id,
        'status' => 'completed',
        'total' => 2000,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $lineId = DB::table('sale_lines')->insertGetId([
        'sale_id' => $saleId,
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'quantity' => 2,
        'unit_price' => 1000,
        'subtotal' => 2000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $paymentId = DB::table('payments')->insertGetId([
        'sale_id' => $saleId,
        'method' => 'cash',
        'amount' => 2000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Updating completed sale_lines is forbidden
    expect(fn () => DB::table('sale_lines')->where('id', $lineId)->update(['quantity' => 5]))
        ->toThrow(QueryException::class);

    // Deleting completed sale_lines is forbidden
    expect(fn () => DB::table('sale_lines')->where('id', $lineId)->delete())
        ->toThrow(QueryException::class);

    // Updating completed payments is forbidden
    expect(fn () => DB::table('payments')->where('id', $paymentId)->update(['amount' => 1500]))
        ->toThrow(QueryException::class);

    // Deleting completed payments is forbidden
    expect(fn () => DB::table('payments')->where('id', $paymentId)->delete())
        ->toThrow(QueryException::class);
});

test('check constraint batch_stocks.quantity >= 0 rejects negative values', function () {
    $product = createTestProduct();
    $batch = createTestBatch($product);

    expect(fn () => DB::table('batch_stocks')->insert([
        'batch_id' => $batch->id,
        'status' => 'sellable',
        'quantity' => -5,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('check constraint sale_lines.quantity > 0 and subtotal >= 0 reject invalid values', function () {
    $till = Till::create(['name' => 'POS-3', 'is_active' => true]);
    $shift = Shift::create([
        'till_id' => $till->id,
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'opening_float' => 1000,
        'status' => 'open',
    ]);
    $product = createTestProduct();
    $batch = createTestBatch($product);
    $sale = Sale::create([
        'receipt_no' => 'REC-TEST-003',
        'shift_id' => $shift->id,
        'user_id' => $this->user->id,
        'status' => 'completed',
        'total' => 1000,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
    ]);

    // Quantity <= 0 is rejected
    expect(fn () => DB::table('sale_lines')->insert([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'quantity' => 0,
        'unit_price' => 100,
        'subtotal' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // Subtotal < 0 is rejected
    expect(fn () => DB::table('sale_lines')->insert([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'quantity' => 1,
        'unit_price' => 100,
        'subtotal' => -10,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('check constraint payments.amount > 0 rejects zero and negative values', function () {
    $till = Till::create(['name' => 'POS-4', 'is_active' => true]);
    $shift = Shift::create([
        'till_id' => $till->id,
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'opening_float' => 1000,
        'status' => 'open',
    ]);
    $sale = Sale::create([
        'receipt_no' => 'REC-TEST-004',
        'shift_id' => $shift->id,
        'user_id' => $this->user->id,
        'status' => 'completed',
        'total' => 1000,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
    ]);

    expect(fn () => DB::table('payments')->insert([
        'sale_id' => $sale->id,
        'method' => 'cash',
        'amount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('payments')->insert([
        'sale_id' => $sale->id,
        'method' => 'cash',
        'amount' => -100,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('check constraints reject invalid status or type values on main tables', function () {
    $product = createTestProduct();
    $batch = createTestBatch($product);

    // batch_stocks status must be sellable or quarantine
    expect(fn () => DB::table('batch_stocks')->insert([
        'batch_id' => $batch->id,
        'status' => 'invalid_status',
        'quantity' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // stock_movements type must be in allowed list
    expect(fn () => DB::table('stock_movements')->insert([
        'batch_id' => $batch->id,
        'type' => 'invalid_type',
        'quantity' => 10,
        'balance_after' => 10,
        'stock_status' => 'sellable',
        'source_type' => 'App\\Models\\Batch',
        'source_id' => $batch->id,
        'user_id' => $this->user->id,
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('foreign key restrictOnDelete prevents deleting parent records with dependent audit, sale, or stock data', function () {
    // 1. Audit: user cannot be deleted if audit_log references it
    $logUser = User::factory()->create();
    AuditLog::create([
        'action' => 'test.restrict',
        'user_id' => $logUser->id,
        'role_used' => 'pharmacy_staff',
        'terminal' => 'POS-1',
        'created_at' => now(),
    ]);
    expect(fn () => $logUser->delete())->toThrow(QueryException::class);

    // 2. Stock: batch cannot be deleted if batch_stocks exists
    $product = createTestProduct();
    $batch = createTestBatch($product);
    BatchStock::create([
        'batch_id' => $batch->id,
        'status' => 'sellable',
        'quantity' => 10,
    ]);
    expect(fn () => $batch->delete())->toThrow(QueryException::class);

    // 3. Product cannot be deleted if batches exist
    expect(fn () => $product->delete())->toThrow(QueryException::class);
});

test('AppendOnly generates valid DDL statements across sqlite, mysql and pgsql drivers', function () {
    // 1. PostgreSQL PL/pgSQL generation
    $pgSqlAudit = AppendOnly::getTriggerSql('pgsql', 'audit_logs', true, true);
    expect($pgSqlAudit)->toHaveCount(4); // 2 functions, 2 triggers
    expect($pgSqlAudit[0])->toContain('LANGUAGE plpgsql');
    expect($pgSqlAudit[0])->toContain('RAISE EXCEPTION');
    expect($pgSqlAudit[1])->toContain('CREATE TRIGGER audit_logs_no_update');

    $pgSqlSales = AppendOnly::getTriggerSql('pgsql', 'sales', true, true);
    expect($pgSqlSales[0])->toContain('prevent_sales_modification');
    expect($pgSqlSales[0])->toContain('LANGUAGE plpgsql');
    expect($pgSqlSales[0])->toContain('IS DISTINCT FROM');

    $pgSqlLines = AppendOnly::getTriggerSql('pgsql', 'sale_lines', true, true);
    expect($pgSqlLines[0])->toContain('prevent_sale_lines_modification');
    expect($pgSqlLines[0])->toContain('SELECT status INTO v_status FROM sales');

    // 2. MySQL trigger generation
    $mysqlAudit = AppendOnly::getTriggerSql('mysql', 'audit_logs', true, true);
    expect($mysqlAudit)->toHaveCount(2);
    expect($mysqlAudit[0])->toContain("SIGNAL SQLSTATE '45000'");

    $mysqlSales = AppendOnly::getTriggerSql('mysql', 'sales', true, true);
    expect($mysqlSales[0])->toContain('reprint_count');
    expect($mysqlSales[0])->toContain('<=>');

    // 3. SQLite trigger generation
    $sqliteAudit = AppendOnly::getTriggerSql('sqlite', 'audit_logs', true, true);
    expect($sqliteAudit)->toHaveCount(2);
    expect($sqliteAudit[0])->toContain('SELECT RAISE(ABORT');

    $sqliteSales = AppendOnly::getTriggerSql('sqlite', 'sales', true, true);
    expect($sqliteSales[0])->toContain('reprint_count');
    expect($sqliteSales[0])->toContain('IS NOT');
});

test('raw SQL update and delete on protected tables fail on MySQL driver', function () {
    config([
        'database.connections.mysql_test' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'pharmacy',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
    ]);

    try {
        $mysql = DB::connection('mysql_test');
        $mysql->getPdo();
    } catch (Throwable) {
        $this->markTestSkipped('MySQL server at 127.0.0.1:3306 not available');
    }

    // 1. Test audit_logs immutability on MySQL
    $logId = $mysql->table('audit_logs')->insertGetId([
        'action' => 'mysql.test.action',
        'created_at' => now(),
    ]);

    expect(fn () => $mysql->table('audit_logs')->where('id', $logId)->update(['action' => 'hacked']))
        ->toThrow(QueryException::class);

    expect(fn () => $mysql->table('audit_logs')->where('id', $logId)->delete())
        ->toThrow(QueryException::class);

    // 2. Test approval_requests delete protection on MySQL
    $reqId = $mysql->table('approval_requests')->insertGetId([
        'initiator_id' => 1,
        'type' => 'test.type',
        'status' => 'pending',
        'summary' => 'MySQL test',
        'payload' => json_encode([]),
        'eligible_roles' => json_encode(['owner']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $mysql->table('approval_requests')->where('id', $reqId)->delete())
        ->toThrow(QueryException::class);

    // Clean up approval request by updating status if needed (since update is allowed)
    $mysql->table('approval_requests')->where('id', $reqId)->update(['status' => 'cancelled']);
});
