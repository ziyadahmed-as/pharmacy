<?php

namespace App\Actions\Catalogue;

use App\Models\Category;
use App\Models\ProductImport;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirms import (or cancels it).
 * A1: excludes error rows and imports only valid rows.
 * A2: cancel marks as cancelled and saves nothing.
 *
 * @uc UC-03 @fr FR-1.8 @ac AC-27
 */
class ConfirmImport
{
    /**
     * @param  bool  $cancel  If true, aborts and saves nothing (A2)
     * @return array{status: string, saved_count: int, rejected_count: int}
     *
     * @throws ValidationException
     */
    public function execute(ProductImport $import, bool $cancel = false, ?User $user = null): array
    {
        $actor = $user ?? Auth::user();

        if (! $actor || ! $actor->hasPermission('catalogue.manage')) {
            throw ValidationException::withMessages([
                'import' => __('catalogue.unauthorized_import'),
            ]);
        }

        if ($import->status !== 'validated') {
            throw ValidationException::withMessages([
                'import' => __('catalogue.import_not_ready_to_confirm'),
            ]);
        }

        // A2: Cancel saves nothing
        if ($cancel) {
            $import->update(['status' => 'cancelled']);
            $import->rows()->update(['status' => 'skipped']);

            AuditLogger::record(
                'catalogue.import.cancelled',
                $import,
                [],
                ['file_name' => $import->file_name],
                ['import_id' => $import->id],
                $actor
            );

            return [
                'status' => 'cancelled',
                'saved_count' => 0,
                'rejected_count' => $import->totals['total_rows'] ?? 0,
            ];
        }

        // A1: Exclude error rows and import only valid rows
        $savedCount = 0;
        $rejectedCount = 0;

        DB::transaction(function () use ($import, $actor, &$savedCount, &$rejectedCount) {
            $saveProductAction = new SaveProduct;
            $setSellingPriceAction = new SetSellingPrice;

            // Cache categories
            $categoriesCache = [];

            $rows = $import->rows()->where('status', 'valid')->cursor();

            foreach ($rows as $row) {
                $data = $row->raw_data;

                // Category lookup or creation
                $categoryId = null;
                if (! empty($data['category'])) {
                    $catName = trim($data['category']);
                    if (! isset($categoriesCache[$catName])) {
                        $cat = Category::firstOrCreate(['name' => $catName]);
                        $categoriesCache[$catName] = $cat->id;
                    }
                    $categoryId = $categoriesCache[$catName];
                }

                $productData = [
                    'brand' => $data['brand'],
                    'generic_name' => $data['generic_name'],
                    'strength' => $data['strength'],
                    'dosage_form' => $data['dosage_form'],
                    'pack_size' => $data['pack_size'],
                    'unit' => $data['unit'] ?: 'piece',
                    'manufacturer' => $data['manufacturer'],
                    'category_id' => $categoryId,
                    'barcode' => $data['barcode'],
                    'classification' => $data['classification'] ?: 'otc',
                    'storage_condition' => $data['storage_condition'],
                    'loose_allowed' => $data['loose_allowed'] ?? false,
                    'reorder_level' => $data['reorder_level'] ?? 0,
                    'max_level' => $data['max_level'] ?? 0,
                    'min_level' => $data['min_level'] ?? 0,
                    'preferred_supplier_name' => $data['preferred_supplier_name'],
                ];

                try {
                    $product = $saveProductAction->execute($productData, null, $actor);

                    // If price provided, set price via UC-02 action
                    if (! empty($data['price']) && (float) $data['price'] > 0) {
                        $minorPrice = (int) round(((float) $data['price']) * 100);
                        $setSellingPriceAction->execute(
                            $product,
                            $minorPrice,
                            now(),
                            'Initial price from file import '.$import->file_name,
                            null,
                            $actor
                        );
                    }

                    $row->update(['status' => 'imported']);
                    $savedCount++;
                } catch (\Throwable $e) {
                    $row->update([
                        'status' => 'error',
                        'errors' => array_merge($row->errors ?? [], [$e->getMessage()]),
                    ]);
                    $rejectedCount++;
                }
            }

            // Mark remaining error rows as skipped
            $import->rows()->where('status', 'error')->update(['status' => 'skipped']);
            $rejectedCount += $import->rows()->where('status', 'skipped')->count();

            $import->update([
                'status' => 'confirmed',
                'totals' => array_merge($import->totals ?? [], [
                    'saved_count' => $savedCount,
                    'rejected_count' => $rejectedCount,
                ]),
            ]);

            AuditLogger::record(
                'catalogue.import.confirmed',
                $import,
                [],
                [
                    'file_name' => $import->file_name,
                    'saved_count' => $savedCount,
                    'rejected_count' => $rejectedCount,
                ],
                ['import_id' => $import->id],
                $actor
            );
        });

        return [
            'status' => 'confirmed',
            'saved_count' => $savedCount,
            'rejected_count' => $rejectedCount,
        ];
    }
}
