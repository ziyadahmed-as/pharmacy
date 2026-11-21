<?php

namespace App\Actions\Catalogue;

use App\Models\Batch;
use App\Models\DrugInformation;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductActiveIngredient;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\StockReceipt;
use App\Models\StockReceiptLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentSequence;
use App\Services\StockMovementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a product record with duplicate checks and permission constraints.
 * On Add, optionally saves a first batch + sets selling price in one atomic transaction.
 *
 * @uc UC-01, UC-02, UC-04
 * @fr FR-1.1, FR-1.2, FR-1.3, FR-1.4, FR-1.5, FR-2.1
 * @br BR-06, BR-20
 */
class SaveProduct
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(array $data, ?Product $product = null, ?User $user = null): Product
    {
        $actor = $user ?? Auth::user();

        // OW/PM only. PP is refused at the controller level too, but double-enforce here.
        if (! $actor || (! $actor->hasPermission('catalogue.manage') && ! $actor->hasPermission('catalogue.edit_basic'))) {
            throw ValidationException::withMessages([
                'brand' => __('catalogue.unauthorized_catalogue_manage'),
            ]);
        }

        $isStaffOnly = ! $actor->hasPermission('catalogue.manage') && $actor->hasPermission('catalogue.edit_basic');

        // Mandatory fields (E1)
        if (empty(trim($data['brand'] ?? ''))
            || empty(trim($data['generic_name'] ?? ''))
            || empty(trim($data['classification'] ?? ''))) {
            throw ValidationException::withMessages([
                'brand' => __('catalogue.mandatory_fields_required'),
            ]);
        }

        // Unit defaults to 'piece' if not provided
        if (empty(trim($data['unit'] ?? ''))) {
            $data['unit'] = 'piece';
        }

        // Category is optional in the action (enforced by the UI form for OW/PM)
        // Stock-level cross-validation: min <= reorder <= max
        $minLevel     = (int) ($data['min_level'] ?? 0);
        $reorderLevel = (int) ($data['reorder_level'] ?? 0);
        $maxLevel     = (int) ($data['max_level'] ?? 0);

        // Staff cannot change levels (E2)
        if ($isStaffOnly && $product) {
            if (isset($data['reorder_level']) && (int) $data['reorder_level'] !== (int) $product->reorder_level) {
                throw ValidationException::withMessages(['reorder_level' => __('catalogue.staff_cannot_change_levels')]);
            }
            if (isset($data['max_level']) && (int) $data['max_level'] !== (int) $product->max_level) {
                throw ValidationException::withMessages(['max_level' => __('catalogue.staff_cannot_change_levels')]);
            }
            if (isset($data['min_level']) && (int) $data['min_level'] !== (int) $product->min_level) {
                throw ValidationException::withMessages(['min_level' => __('catalogue.staff_cannot_change_levels')]);
            }
        }

        if ($isStaffOnly && ! $product) {
            // Staff creating a new product must not set custom stock thresholds.
            if (! empty($data['reorder_level']) || ! empty($data['max_level']) || ! empty($data['min_level'])) {
                throw ValidationException::withMessages(['reorder_level' => __('catalogue.staff_cannot_change_levels')]);
            }
        }

        if ($maxLevel > 0 && $reorderLevel > $maxLevel) {
            throw ValidationException::withMessages([
                'max_level' => __('catalogue.level_reorder_gt_max'),
            ]);
        }
        if ($reorderLevel > 0 && $minLevel > $reorderLevel) {
            throw ValidationException::withMessages([
                'reorder_level' => __('catalogue.level_min_gt_reorder'),
            ]);
        }

        // Duplicate Check 1: Barcode (A1)
        if (! empty($data['barcode'])) {
            $duplicateQuery = Product::where('barcode', $data['barcode']);
            if ($product) {
                $duplicateQuery->where('id', '!=', $product->id);
            }
            $existing = $duplicateQuery->first();
            if ($existing) {
                throw DuplicateProductException::withProduct(
                    $existing,
                    __('catalogue.duplicate_barcode_detected', ['barcode' => $data['barcode']])
                );
            }
        }

        // Duplicate Check 2: Brand + Strength + Form + Pack (A1)
        $brand    = trim($data['brand'] ?? '');
        $strength = trim($data['strength'] ?? '');
        $form     = trim($data['dosage_form'] ?? '');
        $pack     = trim($data['pack_size'] ?? '');

        $existingAttr = Product::where('brand', $brand)
            ->where('strength', $strength)
            ->where('dosage_form', $form)
            ->where('pack_size', $pack)
            ->when($product, fn ($q) => $q->where('id', '!=', $product->id))
            ->first();

        if ($existingAttr) {
            throw DuplicateProductException::withProduct($existingAttr, __('catalogue.duplicate_attributes_detected'));
        }

        // Auto-generate product_code if not provided
        if (empty(trim($data['product_code'] ?? ''))) {
            $data['product_code'] = self::generateProductCode();
        }

        $isNew              = $product === null;
        $oldClassification  = $product?->classification;

        return DB::transaction(function () use ($data, $product, $actor, $isNew, $oldClassification) {
            // ----------------------------------------------------------------
            // 1. Save product
            // ----------------------------------------------------------------
            $target = $product ?? new Product;

            // --- Original fields ---
            $target->brand                   = $data['brand'];
            $target->generic_name            = $data['generic_name'];
            $target->strength                = $data['strength'] ?? null;
            $target->dosage_form             = $data['dosage_form'] ?? null;
            $target->pack_size               = $data['pack_size'] ?? null;
            $target->product_code            = $data['product_code'];
            $target->unit                    = $data['unit'] ?? 'piece';
            $target->manufacturer            = $data['manufacturer'] ?? null;
            $target->category_id             = $data['category_id'] ?? null;
            $target->barcode                 = ! empty($data['barcode']) ? $data['barcode'] : null;
            $target->storage_condition       = $data['storage_condition'] ?? null;
            $target->is_cold_chain           = (bool) ($data['is_cold_chain'] ?? false);
            $target->classification          = $data['classification'];
            $target->loose_allowed           = (bool) ($data['loose_allowed'] ?? false);
            $target->reorder_level           = (int) ($data['reorder_level'] ?? 0);
            $target->max_level               = (int) ($data['max_level'] ?? 0);
            $target->min_level               = (int) ($data['min_level'] ?? 0);
            $target->min_shelf_life_days     = isset($data['min_shelf_life_days']) ? (int) $data['min_shelf_life_days'] : null;
            $target->preferred_supplier_name = $data['preferred_supplier_name'] ?? null;

            // --- New pharmaceutical fields (all optional; null = not set) ---
            $target->product_type                 = $data['product_type'] ?? $target->product_type ?? 'medicine';
            $target->product_description          = $data['product_description'] ?? $target->product_description ?? null;
            $target->country_of_origin            = $data['country_of_origin'] ?? $target->country_of_origin ?? null;
            $target->strength_unit                = $data['strength_unit'] ?? $target->strength_unit ?? null;
            $target->atc_code                     = $data['atc_code'] ?? $target->atc_code ?? null;
            $target->registration_number          = $data['registration_number'] ?? $target->registration_number ?? null;
            $target->special_storage_instructions = $data['special_storage_instructions'] ?? $target->special_storage_instructions ?? null;
            $target->prescription_required        = (bool) ($data['prescription_required'] ?? $target->prescription_required ?? false);
            $target->inventory_tracking_enabled   = (bool) ($data['inventory_tracking_enabled'] ?? $target->inventory_tracking_enabled ?? true);
            $target->batch_tracking_enabled       = (bool) ($data['batch_tracking_enabled'] ?? $target->batch_tracking_enabled ?? true);
            $target->expiry_tracking_enabled      = (bool) ($data['expiry_tracking_enabled'] ?? $target->expiry_tracking_enabled ?? true);

            // FK lookups — only update when the caller explicitly passes a value
            if (array_key_exists('manufacturer_id', $data)) {
                $target->manufacturer_id = $data['manufacturer_id'] ?: null;
            }
            if (array_key_exists('dosage_form_id', $data)) {
                $target->dosage_form_id = $data['dosage_form_id'] ?: null;
            }
            if (array_key_exists('route_of_administration_id', $data)) {
                $target->route_of_administration_id = $data['route_of_administration_id'] ?: null;
            }
            if (array_key_exists('therapeutic_class_id', $data)) {
                $target->therapeutic_class_id = $data['therapeutic_class_id'] ?: null;
            }

            // Auto-resolve manufacturer FK from free-text when possible
            if (empty($target->manufacturer_id) && ! empty($target->manufacturer)) {
                $mfr = Manufacturer::where('name', $target->manufacturer)->first();
                if ($mfr) {
                    $target->manufacturer_id = $mfr->id;
                }
            }

            if ($isNew) {
                $target->is_active = true;
            }

            $target->save();

            // ----------------------------------------------------------------
            // 2. Drug Information (original + new clinical fields)
            // ----------------------------------------------------------------
            if (isset($data['drug_information']) && is_array($data['drug_information'])) {
                DrugInformation::saveFor($target, $data['drug_information'], $actor);
            }

            // ----------------------------------------------------------------
            // 2b. Active Ingredients
            // ----------------------------------------------------------------
            if (isset($data['active_ingredients']) && is_array($data['active_ingredients'])) {
                ProductActiveIngredient::syncFor($target, $data['active_ingredients']);
            }

            // ----------------------------------------------------------------
            // 3. Alternative Units
            // ----------------------------------------------------------------
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

            // ----------------------------------------------------------------
            // 4. Selling price (OW/PM only; set when provided)
            // ----------------------------------------------------------------
            $sellingPriceSantim = $data['selling_price_santim'] ?? null;
            if ($sellingPriceSantim !== null && $sellingPriceSantim > 0) {
                $effective = ! empty($data['selling_price_effective'])
                    ? Carbon::parse($data['selling_price_effective'])
                    : now();

                $reason = trim($data['selling_price_reason'] ?? '');
                if ($reason === '') {
                    $reason = $isNew
                        ? __('catalogue.initial_price_reason')
                        : __('catalogue.price_updated_reason');
                }

                // Get old price for audit
                $oldPrice = ProductPrice::where('product_id', $target->id)
                    ->whereNull('unit_id')
                    ->orderByDesc('effective_from')
                    ->orderByDesc('id')
                    ->value('price');

                ProductPrice::create([
                    'product_id'    => $target->id,
                    'unit_id'       => null,
                    'price'         => $sellingPriceSantim,
                    'effective_from'=> $effective,
                    'reason'        => $reason,
                    'set_by'        => $actor->id,
                    'created_at'    => now(),
                ]);

                AuditLogger::record(
                    'price.changed',
                    $target,
                    ['price' => $oldPrice],
                    ['price' => $sellingPriceSantim, 'reason' => $reason, 'effective_from' => $effective->toIso8601String()],
                    ['product_id' => $target->id, 'brand' => $target->brand],
                    $actor
                );
            }

            // ----------------------------------------------------------------
            // 5. First batch (Add screen only)
            // ----------------------------------------------------------------
            $batchData = $data['first_batch'] ?? null;
            if ($isNew && $batchData !== null && ! empty($batchData['batch_no'])) {
                $this->saveFirstBatch($target, $batchData, $actor);
            }

            // ----------------------------------------------------------------
            // 6. Audit
            // ----------------------------------------------------------------
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
                        [
                            'brand'            => $target->brand,
                            'confirmed'        => (bool) ($data['classification_confirmed'] ?? false),
                            'note'             => 'Confirmed by pharmacist in charge',
                        ],
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Save a first batch for a newly-created product.
     * Uses a StockReceipt so the full ledger trail is written.
     * OW/PM batches are confirmed immediately → sellable unless expired or short-dated.
     *
     * @param  array<string, mixed>  $batchData
     */
    private function saveFirstBatch(Product $product, array $batchData, User $actor): void
    {
        $idempotencyKey = $batchData['idempotency_key'] ?? (string) \Illuminate\Support\Str::uuid();

        // Idempotency: if a receipt with this key already exists, skip.
        if (StockReceipt::where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $receiptDate = now();
        $expiryDate  = Carbon::parse($batchData['expiry_date']);
        $mfgDate     = ! empty($batchData['manufacturing_date'])
            ? Carbon::parse($batchData['manufacturing_date'])
            : null;

        // Determine batch status
        $isExpired       = $expiryDate->isPast();
        $minShelfLife    = (int) setting('inventory.minimum_shelf_life_days', 0);
        $isShortDated    = $minShelfLife > 0
            && $receiptDate->diffInDays($expiryDate, false) < $minShelfLife;
        // OW/PM batches confirmed immediately — but expired/short-dated go to quarantine.
        $stockStatus     = ($isExpired || $isShortDated) ? 'quarantine' : 'sellable';

        $reference = DocumentSequence::next('stock_receipt', 'REC');

        // Create a posted receipt (OW/PM can confirm immediately — BR-19 waived for first batch).
        $receipt = StockReceipt::create([
            'reference'        => $reference,
            'supplier_name'    => $batchData['supplier_name'] ?? $product->preferred_supplier_name ?? 'Opening stock',
            'delivery_note_no' => $batchData['delivery_note_no'] ?? null,
            'receipt_date'     => $receiptDate,
            'status'           => 'posted',
            'idempotency_key'  => $idempotencyKey,
            'notes'            => "First batch for {$product->brand}",
            'created_by'       => $actor->id,
            'confirmed_by'     => $actor->id,
            'confirmed_at'     => now(),
        ]);

        StockReceiptLine::create([
            'stock_receipt_id'   => $receipt->id,
            'product_id'         => $product->id,
            'batch_no'           => $batchData['batch_no'],
            'manufacturing_date' => $mfgDate,
            'expiry_date'        => $expiryDate,
            'quantity'           => (int) $batchData['quantity'],
            'unit_cost'          => $batchData['unit_cost'] ?? null,
            'flag'               => $stockStatus === 'quarantine' ? 'damaged' : 'ok',
        ]);

        $batch = Batch::firstOrCreate(
            ['product_id' => $product->id, 'batch_no' => $batchData['batch_no']],
            [
                'manufacturing_date' => $mfgDate,
                'expiry_date'        => $expiryDate,
                'unit_cost'          => $batchData['unit_cost'] ?? null,
                'supplier_name'      => $receipt->supplier_name,
                'delivery_note_no'   => $receipt->delivery_note_no,
                'registered_by'      => $actor->id,
                'registered_at'      => now(),
                'status'             => Batch::STATUS_SELLABLE,
            ]
        );

        StockMovementService::post(
            batch: $batch,
            signedQuantity: (int) $batchData['quantity'],
            type: 'receipt',
            source: $receipt,
            user: $actor,
            status: $stockStatus,
            description: "First batch {$batchData['batch_no']} via Add Product ({$reference})"
        );

        AuditLogger::record(
            'stock.receipt.confirmed',
            $receipt,
            [],
            [
                'reference'    => $reference,
                'batch_no'     => $batchData['batch_no'],
                'quantity'     => $batchData['quantity'],
                'stock_status' => $stockStatus,
            ],
            ['product_id' => $product->id, 'brand' => $product->brand],
            $actor
        );
    }

    /**
     * Generate a sequential product code: PRD-YYYY-NNNNN.
     */
    public static function generateProductCode(): string
    {
        $year  = now()->year;
        $last  = Product::where('product_code', 'like', "PRD-{$year}-%")
            ->orderByDesc('product_code')
            ->value('product_code');

        $seq   = 1;
        if ($last && preg_match('/PRD-\d{4}-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('PRD-%d-%05d', $year, $seq);
    }
}
