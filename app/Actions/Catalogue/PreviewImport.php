<?php

namespace App\Actions\Catalogue;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\User;
use App\Services\FileParser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parses, maps, and validates every row in the file.
 * Nothing is committed to products catalogue before confirmation (AC-27).
 *
 * @uc UC-03 @fr FR-1.8 @nfr NFR-10 @ac AC-27
 */
class PreviewImport
{
    /**
     * @param  array<string, int>|null  $mapping  Custom column mappings or defaults
     *
     * @throws ValidationException
     */
    public function execute(string $filePath, string $fileName, ?array $mapping = null, ?User $user = null): ProductImport
    {
        $actor = $user ?? Auth::user();

        // Only PM (and Owner acting with PM permissions) may import
        if (! $actor || ! $actor->hasPermission('catalogue.manage')) {
            throw ValidationException::withMessages([
                'file' => __('catalogue.unauthorized_import'),
            ]);
        }

        // Validate file readability upfront
        if (! file_exists($filePath) || ! is_readable($filePath) || filesize($filePath) === 0) {
            throw ValidationException::withMessages([
                'file' => __('catalogue.empty_file_refused'),
            ]);
        }

        try {
            $rowsGenerator = iterator_to_array(FileParser::parse($filePath));
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' => $e->getMessage(),
            ]);
        }

        // Default standard mapping
        $standardMap = $mapping ?? [
            'brand' => 0,
            'generic_name' => 1,
            'strength' => 2,
            'dosage_form' => 3,
            'pack_size' => 4,
            'unit' => 5,
            'manufacturer' => 6,
            'category' => 7,
            'barcode' => 8,
            'classification' => 9,
            'storage_condition' => 10,
            'loose_allowed' => 11,
            'min_level' => 12,
            'reorder_level' => 13,
            'max_level' => 14,
            'preferred_supplier_name' => 15,
            'price' => 16,
        ];

        return DB::transaction(function () use ($filePath, $fileName, $standardMap, $actor, $rowsGenerator) {
            $import = ProductImport::create([
                'file_path' => $filePath,
                'file_name' => $fileName,
                'status' => 'validating',
                'mapping' => $standardMap,
                'user_id' => $actor->id,
            ]);

            $seenBarcodes = [];
            $seenAttributes = [];
            $total = 0;
            $valid = 0;
            $errorsCount = 0;

            $headerSkipped = false;
            $rowBuffer = [];

            // Pre-load existing barcodes and attributes for duplicate check against DB
            $existingDbBarcodes = Product::whereNotNull('barcode')->pluck('id', 'barcode')->toArray();

            foreach ($rowsGenerator as $rowNum => $row) {
                // Skip header if first row has header names
                if (! $headerSkipped) {
                    $firstCell = strtolower($row[0] ?? '');
                    if ($firstCell === 'brand' || $firstCell === 'name' || $firstCell === 'product') {
                        $headerSkipped = true;

                        continue;
                    }
                    $headerSkipped = true;
                }

                $total++;
                $rowErrors = [];

                $get = fn (string $key) => isset($standardMap[$key], $row[$standardMap[$key]]) ? trim((string) $row[$standardMap[$key]]) : '';

                $brand = $get('brand');
                $generic = $get('generic_name');
                $strength = $get('strength');
                $form = $get('dosage_form');
                $pack = $get('pack_size');
                $unit = $get('unit') ?: 'piece';
                $categoryName = $get('category');
                $barcode = $get('barcode');
                $classification = strtolower($get('classification')) ?: 'otc';
                $priceStr = $get('price');

                // Mandatory checks
                if ($brand === '') {
                    $rowErrors[] = __('catalogue.import_err_missing_brand');
                }
                if ($generic === '') {
                    $rowErrors[] = __('catalogue.import_err_missing_generic');
                }

                // Classification check
                if (! in_array($classification, ['rx', 'otc', 'controlled', 'non_drug'], true)) {
                    $rowErrors[] = __('catalogue.import_err_invalid_classification', ['value' => $classification]);
                }

                // Price check (if provided, must be positive)
                if ($priceStr !== '') {
                    if (! is_numeric($priceStr) || (float) $priceStr <= 0) {
                        $rowErrors[] = __('catalogue.import_err_invalid_price', ['value' => $priceStr]);
                    }
                }

                // Barcode duplicate in database or in file
                if ($barcode !== '') {
                    if (isset($existingDbBarcodes[$barcode])) {
                        $rowErrors[] = __('catalogue.import_err_duplicate_db_barcode', ['barcode' => $barcode]);
                    } elseif (isset($seenBarcodes[$barcode])) {
                        $rowErrors[] = __('catalogue.import_err_duplicate_file_barcode', ['barcode' => $barcode, 'row' => $seenBarcodes[$barcode]]);
                    } else {
                        $seenBarcodes[$barcode] = $rowNum;
                    }
                }

                // Attribute combination duplicate in file
                $attrKey = strtolower("{$brand}|{$strength}|{$form}|{$pack}");
                if (isset($seenAttributes[$attrKey])) {
                    $rowErrors[] = __('catalogue.import_err_duplicate_file_attr', ['row' => $seenAttributes[$attrKey]]);
                } else {
                    $seenAttributes[$attrKey] = $rowNum;
                }

                $status = empty($rowErrors) ? 'valid' : 'error';
                if ($status === 'valid') {
                    $valid++;
                } else {
                    $errorsCount++;
                }

                $mappedData = [
                    'brand' => $brand,
                    'generic_name' => $generic,
                    'strength' => $strength,
                    'dosage_form' => $form,
                    'pack_size' => $pack,
                    'unit' => $unit,
                    'manufacturer' => $get('manufacturer'),
                    'category' => $categoryName,
                    'barcode' => $barcode,
                    'classification' => $classification,
                    'storage_condition' => $get('storage_condition'),
                    'loose_allowed' => in_array(strtolower($get('loose_allowed')), ['1', 'true', 'yes'], true),
                    'min_level' => (int) $get('min_level'),
                    'reorder_level' => (int) $get('reorder_level'),
                    'max_level' => (int) $get('max_level'),
                    'preferred_supplier_name' => $get('preferred_supplier_name'),
                    'price' => $priceStr !== '' ? (float) $priceStr : null,
                ];

                $rowBuffer[] = [
                    'product_import_id' => $import->id,
                    'row_number' => $rowNum,
                    'raw_data' => json_encode($mappedData),
                    'errors' => ! empty($rowErrors) ? json_encode($rowErrors) : null,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Batch insert chunks of 500
                if (count($rowBuffer) >= 500) {
                    ProductImportRow::insert($rowBuffer);
                    $rowBuffer = [];
                }
            }

            if (! empty($rowBuffer)) {
                ProductImportRow::insert($rowBuffer);
            }

            $import->update([
                'status' => 'validated',
                'totals' => [
                    'total_rows' => $total,
                    'valid_rows' => $valid,
                    'error_rows' => $errorsCount,
                ],
            ]);

            return $import;
        });
    }
}
