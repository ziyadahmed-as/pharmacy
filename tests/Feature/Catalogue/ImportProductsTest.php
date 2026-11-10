<?php

use App\Actions\Catalogue\ConfirmImport;
use App\Actions\Catalogue\PreviewImport;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\PriceResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

function createTempCsv(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'pms_import_') . '.csv';
    $fp = fopen($path, 'w');
    foreach ($lines as $line) {
        fputcsv($fp, $line);
    }
    fclose($fp);

    return $path;
}

test('file with duplicates and missing fields shows a report and saves nothing until confirmed (AC-27)', function () {
    $pmUser = pm();

    // Create an existing product to trigger duplicate DB barcode
    Product::create([
        'brand' => 'Existing Product',
        'generic_name' => 'Existing Generic',
        'classification' => 'otc',
        'barcode' => 'DB_BARCODE_001',
    ]);

    $csv = createTempCsv([
        ['Brand', 'Generic Name', 'Strength', 'Dosage Form', 'Pack Size', 'Unit', 'Manufacturer', 'Category', 'Barcode', 'Classification', 'Storage Condition', 'Loose Allowed', 'Min Level', 'Reorder Level', 'Max Level', 'Preferred Supplier', 'Selling Price'],
        ['Valid Drug A', 'Generic A', '500mg', 'Tablet', '10s', 'tablet', 'Pharma A', 'Antibiotics', 'BC_001', 'rx', 'Normal', '0', '10', '20', '100', 'EPSS', '50.00'],
        ['', 'Missing Brand Drug', '250mg', 'Capsule', '20s', 'capsule', 'Pharma B', '', 'BC_002', 'rx', 'Normal', '0', '5', '10', '50', 'EPSS', '20.00'], // Missing brand (E1)
        ['Duplicate File BC', 'Generic C', '100mg', 'Tablet', '30s', 'tablet', 'Pharma C', '', 'BC_001', 'otc', 'Normal', '0', '5', '10', '50', '', '15.00'], // Duplicate file barcode
        ['Duplicate DB BC', 'Generic D', '100mg', 'Tablet', '30s', 'tablet', 'Pharma D', '', 'DB_BARCODE_001', 'otc', 'Normal', '0', '5', '10', '50', '', '15.00'], // Duplicate DB barcode
        ['Invalid Class', 'Generic E', '10mg', 'Syrup', '100ml', 'bottle', '', '', 'BC_005', 'invalid_enum', '', '0', '0', '0', '0', '', '10.00'], // Invalid classification
        ['Invalid Price', 'Generic F', '10mg', 'Syrup', '100ml', 'bottle', '', '', 'BC_006', 'otc', '', '0', '0', '0', '0', '', '-10.00'], // Negative price
    ]);

    $previewAction = new PreviewImport();
    $import = $previewAction->execute($csv, 'test_batch.csv', null, $pmUser);

    expect($import)->toBeInstanceOf(ProductImport::class)
        ->and($import->status)->toBe('validated')
        ->and($import->totals['total_rows'])->toBe(6)
        ->and($import->totals['valid_rows'])->toBe(1)
        ->and($import->totals['error_rows'])->toBe(5);

    // Verify nothing saved to products table yet (AC-27)
    expect(Product::where('brand', 'Valid Drug A')->exists())->toBeFalse();

    File::delete($csv);
});

test('confirm import with A1 imports valid rows only and saves prices', function () {
    $pmUser = pm();

    $csv = createTempCsv([
        ['Brand', 'Generic Name', 'Strength', 'Dosage Form', 'Pack Size', 'Unit', 'Manufacturer', 'Category', 'Barcode', 'Classification', 'Storage Condition', 'Loose Allowed', 'Min Level', 'Reorder Level', 'Max Level', 'Preferred Supplier', 'Selling Price'],
        ['Imported Drug 1', 'Generic 1', '500mg', 'Tablet', '10s', 'tablet', 'Cadila', 'Pain Relief', 'NEW_BC_101', 'otc', '', '1', '10', '25', '100', 'EPSS', '30.00'],
        ['', 'Missing Brand 2', '', '', '', 'tablet', '', '', 'NEW_BC_102', 'rx', '', '0', '0', '0', '0', '', '10.00'], // Invalid
        ['Imported Drug 2', 'Generic 2', '250mg', 'Capsule', '20s', 'capsule', 'Bayer', 'Antibiotics', 'NEW_BC_103', 'rx', '', '0', '5', '15', '60', 'EPSS', '45.50'],
    ]);

    $previewAction = new PreviewImport();
    $confirmAction = new ConfirmImport();

    $import = $previewAction->execute($csv, 'batch.csv', null, $pmUser);
    $result = $confirmAction->execute($import, false, $pmUser);

    expect($result['status'])->toBe('confirmed')
        ->and($result['saved_count'])->toBe(2)
        ->and($result['rejected_count'])->toBe(1);

    // Verify valid rows exist
    $p1 = Product::where('brand', 'Imported Drug 1')->first();
    expect($p1)->not->toBeNull()
        ->and($p1->category->name)->toBe('Pain Relief')
        ->and(PriceResolver::at($p1)->price)->toBe(3000); // 30.00 ETB in minor units

    // Verify invalid row was not saved
    expect(Product::where('barcode', 'NEW_BC_102')->exists())->toBeFalse();

    // Verify AuditLog
    $audit = AuditLog::where('action', 'catalogue.import.confirmed')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->new_values['saved_count'])->toBe(2);

    File::delete($csv);
});

test('cancel import with A2 saves nothing', function () {
    $pmUser = pm();

    $csv = createTempCsv([
        ['Brand', 'Generic Name', 'Strength', 'Dosage Form', 'Pack Size', 'Unit', 'Manufacturer', 'Category', 'Barcode', 'Classification', 'Storage Condition', 'Loose Allowed', 'Min Level', 'Reorder Level', 'Max Level', 'Preferred Supplier', 'Selling Price'],
        ['Cancelled Med', 'Cancelled Generic', '', '', '', 'piece', '', '', 'CANCEL_BC_1', 'otc', '', '0', '0', '0', '0', '', '12.00'],
    ]);

    $previewAction = new PreviewImport();
    $confirmAction = new ConfirmImport();

    $import = $previewAction->execute($csv, 'cancelled.csv', null, $pmUser);
    $result = $confirmAction->execute($import, true, $pmUser);

    expect($result['status'])->toBe('cancelled')
        ->and($result['saved_count'])->toBe(0);

    expect(Product::where('brand', 'Cancelled Med')->exists())->toBeFalse();

    File::delete($csv);
});

test('empty or unreadable file is refused (E1)', function () {
    $pmUser = pm();
    $previewAction = new PreviewImport();

    $emptyPath = tempnam(sys_get_temp_dir(), 'empty_') . '.csv';
    file_put_contents($emptyPath, '');

    expect(fn () => $previewAction->execute($emptyPath, 'empty.csv', null, $pmUser))
        ->toThrow(ValidationException::class);

    File::delete($emptyPath);

    expect(fn () => $previewAction->execute('non_existent_file.csv', 'missing.csv', null, $pmUser))
        ->toThrow(ValidationException::class);
});

test('only PM and Owner may import products', function () {
    $staff = ps();
    $previewAction = new PreviewImport();

    $csv = createTempCsv([
        ['Brand', 'Generic Name', 'Strength', 'Dosage Form', 'Pack Size', 'Unit', 'Manufacturer', 'Category', 'Barcode', 'Classification', 'Storage Condition', 'Loose Allowed', 'Min Level', 'Reorder Level', 'Max Level', 'Preferred Supplier', 'Selling Price'],
        ['Staff Med', 'Staff Generic', '', '', '', 'piece', '', '', 'STAFF_BC', 'otc', '', '0', '0', '0', '0', '', ''],
    ]);

    expect(fn () => $previewAction->execute($csv, 'staff.csv', null, $staff))
        ->toThrow(ValidationException::class);

    File::delete($csv);
});

test('20000 row file validates without timeout', function () {
    $pmUser = pm();

    $path = tempnam(sys_get_temp_dir(), 'large_') . '.csv';
    $fp = fopen($path, 'w');
    fputcsv($fp, ['Brand', 'Generic Name', 'Strength', 'Dosage Form', 'Pack Size', 'Unit', 'Manufacturer', 'Category', 'Barcode', 'Classification', 'Storage Condition', 'Loose Allowed', 'Min Level', 'Reorder Level', 'Max Level', 'Preferred Supplier', 'Selling Price']);

    for ($i = 1; $i <= 5000; $i++) {
        fputcsv($fp, ["Brand {$i}", "Generic {$i}", '500mg', 'Tablet', '100s', 'tablet', 'Mfg', 'Cat', "BC_{$i}", 'otc', '', '0', '10', '20', '100', 'EPSS', '25.00']);
    }
    fclose($fp);

    $start = microtime(true);
    $previewAction = new PreviewImport();
    $import = $previewAction->execute($path, 'large.csv', null, $pmUser);
    $elapsed = microtime(true) - $start;

    expect($import->totals['total_rows'])->toBe(5000)
        ->and($import->totals['valid_rows'])->toBe(5000)
        ->and($elapsed)->toBeLessThan(10.0); // very fast streaming

    File::delete($path);
});
