<?php

namespace App\Actions\Catalogue;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a product record with duplicate checks and permission constraints.
 *
 * @uc UC-01 @fr FR-1.1, FR-1.2, FR-1.3, FR-1.4
 */
class SaveProduct
{
    /**
     * @param array<string, mixed> $data
     * @param Product|null $product
     * @param User|null $user
     * @return Product
     * @throws ValidationException
     */
    public function execute(array $data, ?Product $product = null, ?User $user = null): Product
    {
        $actor = $user ?? Auth::user();

        if (! $actor || (! $actor->hasPermission('catalogue.manage') && ! $actor->hasPermission('catalogue.edit_basic'))) {
            throw ValidationException::withMessages([
                'brand' => __('catalogue.unauthorized_catalogue_manage'),
            ]);
        }

        $isStaffOnly = ! $actor->hasPermission('catalogue.manage') && $actor->hasPermission('catalogue.edit_basic');

        // Mandatory fields check (E1)
        if (empty($data['brand']) || empty($data['generic_name']) || empty($data['classification'])) {
            throw ValidationException::withMessages([
                'generic_name' => __('catalogue.mandatory_fields_required'),
            ]);
        }

        // Staff cannot change levels (E2)
        if ($isStaffOnly) {
            if ($product) {
                // If updating existing product, staff cannot alter levels
                if (isset($data['reorder_level']) && (int) $data['reorder_level'] !== (int) $product->reorder_level) {
                    throw ValidationException::withMessages(['reorder_level' => __('catalogue.staff_cannot_change_levels')]);
                }
                if (isset($data['max_level']) && (int) $data['max_level'] !== (int) $product->max_level) {
                    throw ValidationException::withMessages(['max_level' => __('catalogue.staff_cannot_change_levels')]);
                }
                if (isset($data['min_level']) && (int) $data['min_level'] !== (int) $product->min_level) {
                    throw ValidationException::withMessages(['min_level' => __('catalogue.staff_cannot_change_levels')]);
                }
            } else {
                // If creating, staff must not specify custom stock thresholds
                if (! empty($data['reorder_level']) || ! empty($data['max_level']) || ! empty($data['min_level'])) {
                    throw ValidationException::withMessages(['reorder_level' => __('catalogue.staff_cannot_change_levels')]);
                }
            }
        }

        // Duplicate Check 1: Barcode (A1)
        if (! empty($data['barcode'])) {
            $duplicateQuery = Product::where('barcode', $data['barcode']);
            if ($product) {
                $duplicateQuery->where('id', '!=', $product->id);
            }
            $existing = $duplicateQuery->first();
            if ($existing) {
                throw DuplicateProductException::withProduct($existing, __('catalogue.duplicate_barcode_detected', ['barcode' => $data['barcode']]));
            }
        }

        // Duplicate Check 2: Brand + Strength + Form + Pack (A1)
        $brand = trim($data['brand'] ?? '');
        $strength = trim($data['strength'] ?? '');
        $form = trim($data['dosage_form'] ?? '');
        $pack = trim($data['pack_size'] ?? '');

        $duplicateAttributesQuery = Product::where('brand', $brand)
            ->where('strength', $strength)
            ->where('dosage_form', $form)
            ->where('pack_size', $pack);

        if ($product) {
            $duplicateAttributesQuery->where('id', '!=', $product->id);
        }

        $existingAttr = $duplicateAttributesQuery->first();
        if ($existingAttr) {
            throw DuplicateProductException::withProduct($existingAttr, __('catalogue.duplicate_attributes_detected'));
        }

        $isNew = $product === null;
        $oldClassification = $product?->classification;

        return DB::transaction(function () use ($data, $product, $actor, $isNew, $oldClassification) {
            $target = $product ?? new Product();

            $target->brand = $data['brand'];
            $target->generic_name = $data['generic_name'];
            $target->strength = $data['strength'] ?? null;
            $target->dosage_form = $data['dosage_form'] ?? null;
            $target->pack_size = $data['pack_size'] ?? null;
            $target->unit = $data['unit'] ?? 'piece';
            $target->manufacturer = $data['manufacturer'] ?? null;
            $target->category_id = $data['category_id'] ?? null;
            $target->barcode = ! empty($data['barcode']) ? $data['barcode'] : null;
            $target->storage_condition = $data['storage_condition'] ?? null;
            $target->classification = $data['classification'];
            $target->loose_allowed = (bool) ($data['loose_allowed'] ?? false);
            $target->reorder_level = (int) ($data['reorder_level'] ?? 0);
            $target->max_level = (int) ($data['max_level'] ?? 0);
            $target->min_level = (int) ($data['min_level'] ?? 0);
            $target->preferred_supplier_name = $data['preferred_supplier_name'] ?? null;

            if ($isNew) {
                $target->is_active = true;
            }

            $target->save();

            // Save units if provided
            if (isset($data['units']) && is_array($data['units'])) {
                foreach ($data['units'] as $u) {
                    if (! empty($u['name']) && ! empty($u['factor'])) {
                        ProductUnit::updateOrCreate(
                            ['product_id' => $target->id, 'name' => $u['name']],
                            ['factor' => (int) $u['factor']]
                        );
                    }
                }
            }

            // Audit
            if ($isNew) {
                AuditLogger::record(
                    'catalogue.product.created',
                    $target,
                    [],
                    $target->toArray(),
                    ['brand' => $target->brand],
                    $actor
                );
            } else {
                if ($oldClassification !== $target->classification) {
                    AuditLogger::record(
                        'catalogue.classification.changed',
                        $target,
                        ['classification' => $oldClassification],
                        ['classification' => $target->classification],
                        ['brand' => $target->brand, 'note' => 'Confirmed by pharmacist in charge'],
                        $actor
                    );
                }

                AuditLogger::record(
                    'catalogue.product.updated',
                    $target,
                    [],
                    $target->getChanges(),
                    ['brand' => $target->brand],
                    $actor
                );
            }

            return $target;
        });
    }
}
