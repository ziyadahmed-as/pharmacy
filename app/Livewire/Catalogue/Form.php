<?php

namespace App\Livewire\Catalogue;

use App\Actions\Catalogue\DuplicateProductException;
use App\Actions\Catalogue\SaveProduct;
use App\Models\Batch;
use App\Models\Category;
use App\Models\DosageForm;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\RouteOfAdministration;
use App\Models\SaleLine;
use App\Models\TherapeuticClass;
use App\Services\ProductImageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Add / Edit Medicine form (UC-01, UC-02, UC-04).
 * OW and PM only. PS is refused with 403 on mount.
 *
 * @uc UC-01, UC-02, UC-04
 * @fr FR-1.1, FR-1.2, FR-1.3, FR-1.4, FR-1.5, FR-2.1
 * @br BR-06, BR-20
 */
class Form extends Component
{
    use WithFileUploads;

    // -------------------------------------------------------------------------
    // Product identity
    // -------------------------------------------------------------------------

    #[Locked]
    public ?int $productId = null;

    // =========================================================================
    // SECTION 1: Product Identification
    // =========================================================================

    public string $brand               = '';
    public string $generic_name        = '';
    public string $product_code        = '';
    public string $product_type        = 'medicine';
    public string $product_description = '';
    public string $barcode             = '';
    public string $manufacturer        = '';
    public ?int   $manufacturer_id     = null;
    public string $country_of_origin   = '';

    // =========================================================================
    // SECTION 2: Pharmaceutical Specifications
    // =========================================================================

    public string $strength       = '';
    public string $strength_unit  = '';
    public string $dosage_form    = '';
    public ?int   $dosage_form_id = null;
    public ?int   $route_of_administration_id = null;
    public ?int   $therapeutic_class_id       = null;
    public string $atc_code          = '';
    public string $registration_number = '';

    /**
     * Dynamic active-ingredient rows.
     * Each row: [ingredient_name, ingredient_code, amount, strength_unit, sort_order]
     *
     * @var array<int, array{ingredient_name: string, ingredient_code: string, amount: string, strength_unit: string}>
     */
    public array $activeIngredients = [];

    // =========================================================================
    // SECTION 3: Packaging & Units
    // =========================================================================

    public string $pack_size    = '';
    public string $unit         = 'piece';
    public bool   $loose_allowed = false;

    /** @var array<int, array{name: string, factor: int}> */
    public array $units = [];

    // =========================================================================
    // SECTION 4: Classification & Regulatory Control
    // =========================================================================

    public string $classification       = 'otc';
    public ?int   $category_id          = null;
    public bool   $prescription_required = false;

    /** Confirmation when changing classification on an existing product. */
    public bool   $classificationConfirmed  = false;
    public string $originalClassification   = '';

    // =========================================================================
    // SECTION 5: Storage & Shelf Life
    // =========================================================================

    public string $storage_condition          = '';
    public bool   $is_cold_chain              = false;
    public ?int   $min_shelf_life_days        = null;
    public string $special_storage_instructions = '';

    // =========================================================================
    // SECTION 6: Pricing (OW/PM only)
    // =========================================================================

    /** Selling price as decimal ETB string (e.g. "45.00"). Empty = not set. */
    public string $sellingPrice          = '';
    public string $sellingPriceReason    = '';
    public string $sellingPriceEffective = '';

    // =========================================================================
    // SECTION 7: Inventory Configuration
    // =========================================================================

    public int  $min_level    = 0;
    public int  $reorder_level = 0;
    public int  $max_level    = 0;
    public bool $inventory_tracking_enabled = true;
    public bool $batch_tracking_enabled     = true;
    public bool $expiry_tracking_enabled    = true;

    // =========================================================================
    // SECTION 8: Clinical Drug Information
    // =========================================================================

    public bool   $clinicalOpen    = false;
    public string $indications     = '';
    public string $dosage_guidance = '';
    public string $administration_instructions    = '';
    public string $contraindications              = '';
    public string $side_effects                   = '';
    public string $interactions                   = '';
    public string $warnings_precautions           = '';
    public string $patient_counseling_information = '';

    // =========================================================================
    // Supporting / image / batch / misc
    // =========================================================================

    public string  $preferred_supplier_name = '';

    public mixed   $imageFile             = null;
    public ?string $currentImagePath      = null;
    public ?string $currentThumbnailPath  = null;

    // First batch (Add screen only)
    public string $batchNo          = '';
    public string $batchMfgDate     = '';
    public string $batchExpiryDate  = '';
    public string $batchBuyingPrice = '';
    public string $batchQuantity    = '';
    public string $batchSupplier    = '';
    public string $batchDeliveryNo  = '';
    public bool   $batchStarted     = false;

    public string $saveIdempotencyKey = '';

    // Duplicate detection
    public ?int    $duplicateProductId = null;
    public ?string $duplicateMessage   = null;

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function mount(?Product $product = null): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasPermission('catalogue.manage'), 403);

        $this->saveIdempotencyKey    = (string) Str::uuid();
        $this->sellingPriceEffective = now()->format('Y-m-d\TH:i');

        if (! $product || ! $product->exists) {
            return;
        }

        // --- Section 1: Product Identification ---
        $this->productId          = $product->id;
        $this->brand              = $product->brand;
        $this->generic_name       = $product->generic_name;
        $this->product_code       = $product->product_code ?? '';
        $this->product_type       = $product->product_type ?? 'medicine';
        $this->product_description = $product->product_description ?? '';
        $this->barcode            = $product->barcode ?? '';
        $this->manufacturer       = $product->manufacturer ?? '';
        $this->manufacturer_id    = $product->manufacturer_id;
        $this->country_of_origin  = $product->country_of_origin ?? '';

        // --- Section 2: Pharmaceutical Specifications ---
        $this->strength                   = $product->strength ?? '';
        $this->strength_unit              = $product->strength_unit ?? '';
        $this->dosage_form                = $product->dosage_form ?? '';
        $this->dosage_form_id             = $product->dosage_form_id;
        $this->route_of_administration_id = $product->route_of_administration_id;
        $this->therapeutic_class_id       = $product->therapeutic_class_id;
        $this->atc_code                   = $product->atc_code ?? '';
        $this->registration_number        = $product->registration_number ?? '';

        $this->activeIngredients = $product->activeIngredients
            ->map(fn ($ai) => [
                'ingredient_name' => $ai->ingredient_name,
                'ingredient_code' => $ai->ingredient_code ?? '',
                'amount'          => (string) $ai->amount,
                'strength_unit'   => $ai->strength_unit,
            ])
            ->toArray();

        // --- Section 3: Packaging ---
        $this->pack_size    = $product->pack_size ?? '';
        $this->unit         = $product->unit ?? 'piece';
        $this->loose_allowed = (bool) $product->loose_allowed;
        $this->units = $product->units
            ->map(fn ($u) => ['name' => $u->name, 'factor' => $u->factor])
            ->toArray();

        // --- Section 4: Classification ---
        $this->classification         = $product->classification ?? 'otc';
        $this->originalClassification = $product->classification ?? 'otc';
        $this->category_id            = $product->category_id;
        $this->prescription_required  = (bool) $product->prescription_required;

        // --- Section 5: Storage ---
        $this->storage_condition            = $product->storage_condition ?? '';
        $this->is_cold_chain                = (bool) $product->is_cold_chain;
        $this->min_shelf_life_days          = $product->min_shelf_life_days;
        $this->special_storage_instructions = $product->special_storage_instructions ?? '';

        // --- Section 6: Pricing ---
        $latestPrice = $product->latestPrice;
        if ($latestPrice) {
            $this->sellingPrice = number_format($latestPrice->price / 100, 2, '.', '');
        }

        // --- Section 7: Inventory Config ---
        $this->min_level                  = (int) $product->min_level;
        $this->reorder_level              = (int) $product->reorder_level;
        $this->max_level                  = (int) $product->max_level;
        $this->inventory_tracking_enabled = (bool) ($product->inventory_tracking_enabled ?? true);
        $this->batch_tracking_enabled     = (bool) ($product->batch_tracking_enabled ?? true);
        $this->expiry_tracking_enabled    = (bool) ($product->expiry_tracking_enabled ?? true);

        // --- Section 8: Clinical ---
        $this->preferred_supplier_name  = $product->preferred_supplier_name ?? '';
        $this->currentImagePath         = $product->image_path;
        $this->currentThumbnailPath     = $product->thumbnail_path ?? null;

        if ($product->drugInformation) {
            $di = $product->drugInformation;
            $this->indications                    = $di->indications ?? '';
            $this->dosage_guidance                = $di->dosage_guidance ?? '';
            $this->administration_instructions    = $di->administration_instructions ?? '';
            $this->contraindications              = $di->contraindications ?? '';
            $this->side_effects                   = $di->side_effects ?? '';
            $this->interactions                   = $di->interactions ?? '';
            $this->warnings_precautions           = $di->warnings_precautions ?? '';
            $this->patient_counseling_information = $di->patient_counseling_information ?? '';
            $this->clinicalOpen = $this->indications !== '' || $this->dosage_guidance !== '';
        }
    }

    // -------------------------------------------------------------------------
    // Computed properties
    // -------------------------------------------------------------------------

    #[Computed]
    public function unitIsLocked(): bool
    {
        if (! $this->productId) {
            return false;
        }
        $hasStock = DB::table('batches')
            ->where('product_id', $this->productId)
            ->where(function ($q) {
                $q->where('quantity_on_hand', '>', 0)
                  ->orWhere('quarantine_qty', '>', 0);
            })
            ->exists();

        if ($hasStock) {
            return true;
        }
        return SaleLine::whereHas('sale', fn ($q) => $q->where('status', 'completed'))
            ->where('product_id', $this->productId)
            ->exists();
    }

    #[Computed]
    public function priceBelowCostWarning(): ?string
    {
        $selling = $this->sellingPriceInSantim();
        $buying  = $this->batchBuyingPriceInSantim();
        if ($selling !== null && $buying !== null && $selling > 0 && $selling <= $buying) {
            return __('catalogue.price_below_buying_warning', [
                'selling' => number_format($selling / 100, 2),
                'buying'  => number_format($buying / 100, 2),
            ]);
        }
        return null;
    }

    #[Computed]
    public function classificationChanged(): bool
    {
        return $this->productId !== null
            && $this->classification !== $this->originalClassification;
    }

    // -------------------------------------------------------------------------
    // Watchers
    // -------------------------------------------------------------------------

    public function updatedBatchNo():          void { $this->batchStarted = $this->anyBatchFieldFilled(); }
    public function updatedBatchMfgDate():     void { $this->batchStarted = $this->anyBatchFieldFilled(); }
    public function updatedBatchExpiryDate():  void {
        $this->batchStarted = $this->anyBatchFieldFilled();
        if ($this->batchExpiryDate !== '' && $this->batchNo === '') {
            try {
                $this->batchNo = \Carbon\Carbon::parse($this->batchExpiryDate)->format('Y-m');
            } catch (\Throwable) {}
        }
    }
    public function updatedBatchBuyingPrice(): void { $this->batchStarted = $this->anyBatchFieldFilled(); }
    public function updatedBatchQuantity():    void { $this->batchStarted = $this->anyBatchFieldFilled(); }

    // -------------------------------------------------------------------------
    // Active ingredient rows
    // -------------------------------------------------------------------------

    public function addIngredientRow(): void
    {
        $this->activeIngredients[] = [
            'ingredient_name' => '',
            'ingredient_code' => '',
            'amount'          => '',
            'strength_unit'   => 'mg',
        ];
    }

    public function removeIngredientRow(int $index): void
    {
        unset($this->activeIngredients[$index]);
        $this->activeIngredients = array_values($this->activeIngredients);
    }

    // -------------------------------------------------------------------------
    // Unit rows
    // -------------------------------------------------------------------------

    public function addUnitRow(): void
    {
        $this->units[] = ['name' => '', 'factor' => 1];
    }

    public function removeUnitRow(int $index): void
    {
        unset($this->units[$index]);
        $this->units = array_values($this->units);
    }

    // -------------------------------------------------------------------------
    // Image upload
    // -------------------------------------------------------------------------

    public function updatedImageFile(): void
    {
        if (! $this->imageFile) {
            return;
        }
        try {
            $this->validate(['imageFile' => ['file', 'max:2048', 'mimes:jpeg,jpg,png,webp']]);
        } catch (ValidationException $e) {
            $this->imageFile = null;
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function removeImage(): void
    {
        abort_unless(Auth::user()?->hasPermission('catalogue.manage'), 403);
        if ($this->productId) {
            $product = Product::findOrFail($this->productId);
            app(ProductImageService::class)->delete($product->image_path, $product->thumbnail_path ?? null);
            $product->update(['image_path' => null, 'thumbnail_path' => null]);
        }
        $this->currentImagePath     = null;
        $this->currentThumbnailPath = null;
        $this->imageFile            = null;
    }

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------

    public function save(SaveProduct $action): mixed
    {
        $user = Auth::user();
        abort_unless($user && $user->hasPermission('catalogue.manage'), 403);

        $this->resetValidation();
        $this->duplicateProductId = null;
        $this->duplicateMessage   = null;

        // Cross-constraint: stock levels
        if ($this->min_level > $this->reorder_level && $this->reorder_level > 0) {
            $this->addError('reorder_level', __('catalogue.level_min_gt_reorder'));
        }
        if ($this->reorder_level > $this->max_level && $this->max_level > 0) {
            $this->addError('max_level', __('catalogue.level_reorder_gt_max'));
        }

        // Required fields
        if (empty(trim($this->unit))) {
            $this->addError('unit', __('catalogue.base_unit_required'));
        }
        if (empty($this->category_id)) {
            $this->addError('category_id', __('catalogue.category_required'));
        }

        if ($this->hasErrors()) {
            return null;
        }

        // Classification change requires pharmacist confirmation
        if ($this->classificationChanged && ! $this->classificationConfirmed) {
            $this->addError('classificationConfirmed', __('catalogue.classification_change_confirm_required'));
            return null;
        }

        // Locked base unit
        if ($this->unitIsLocked && $this->productId) {
            $existing = Product::find($this->productId);
            if ($existing && $existing->unit !== $this->unit) {
                $this->addError('unit', __('catalogue.base_unit_locked'));
                return null;
            }
        }

        // Active ingredient validation — skip empty rows, block partial rows
        $validIngredients = [];
        foreach ($this->activeIngredients as $i => $row) {
            $name   = trim($row['ingredient_name'] ?? '');
            $amount = trim($row['amount'] ?? '');
            $su     = trim($row['strength_unit'] ?? '');
            if ($name === '' && $amount === '') {
                continue; // completely blank row — skip silently
            }
            if ($name === '') {
                $this->addError("activeIngredients.{$i}.ingredient_name", __('catalogue.ingredient_name_required'));
            }
            if ($amount === '' || (float) $amount <= 0) {
                $this->addError("activeIngredients.{$i}.amount", __('catalogue.ingredient_amount_required'));
            }
            if ($su === '') {
                $this->addError("activeIngredients.{$i}.strength_unit", __('catalogue.ingredient_unit_required'));
            }
            if ($name !== '' && $amount !== '' && $su !== '') {
                $validIngredients[] = [
                    'ingredient_name' => $name,
                    'ingredient_code' => trim($row['ingredient_code'] ?? ''),
                    'amount'          => (float) $amount,
                    'strength_unit'   => $su,
                    'sort_order'      => $i,
                ];
            }
        }

        // Batch field validation
        if ($this->batchStarted || $this->anyBatchFieldFilled()) {
            $this->batchStarted = true;

            if (trim($this->batchNo) === '') {
                $this->addError('batchNo', __('catalogue.batch_no_required'));
            }
            if (trim($this->batchMfgDate) === '') {
                $this->addError('batchMfgDate', __('catalogue.mfg_date_required'));
            }
            if (trim($this->batchExpiryDate) === '') {
                $this->addError('batchExpiryDate', __('catalogue.expiry_date_required'));
            }
            if (trim($this->batchBuyingPrice) === '' || (float) $this->batchBuyingPrice <= 0) {
                $this->addError('batchBuyingPrice', __('catalogue.buying_price_required'));
            }
            if (trim($this->batchQuantity) === '' || (int) $this->batchQuantity <= 0) {
                $this->addError('batchQuantity', __('catalogue.quantity_must_be_positive'));
            }
            if (! empty($this->batchExpiryDate) && ! empty($this->batchMfgDate)) {
                if (strtotime($this->batchExpiryDate) <= strtotime($this->batchMfgDate)) {
                    $this->addError('batchExpiryDate', __('catalogue.expiry_must_be_after_mfg'));
                }
            }
            if (! empty($this->batchMfgDate) && strtotime($this->batchMfgDate) > time()) {
                $this->addError('batchMfgDate', __('catalogue.mfg_date_not_future'));
            }

            if ($this->hasErrors()) {
                return null;
            }
        }

        if ($this->hasErrors()) {
            return null;
        }

        $data = [
            // Section 1
            'brand'               => $this->brand,
            'generic_name'        => $this->generic_name,
            'product_code'        => $this->product_code ?: null,
            'product_type'        => $this->product_type ?: 'medicine',
            'product_description' => $this->product_description ?: null,
            'barcode'             => $this->barcode ?: null,
            'manufacturer'        => $this->manufacturer ?: null,
            'manufacturer_id'     => $this->manufacturer_id ?: null,
            'country_of_origin'   => $this->country_of_origin ?: null,
            // Section 2
            'strength'                   => $this->strength ?: null,
            'strength_unit'              => $this->strength_unit ?: null,
            'dosage_form'                => $this->dosage_form ?: null,
            'dosage_form_id'             => $this->dosage_form_id ?: null,
            'route_of_administration_id' => $this->route_of_administration_id ?: null,
            'therapeutic_class_id'       => $this->therapeutic_class_id ?: null,
            'atc_code'                   => $this->atc_code ?: null,
            'registration_number'        => $this->registration_number ?: null,
            'active_ingredients'         => $validIngredients,
            // Section 3
            'pack_size'    => $this->pack_size ?: null,
            'unit'         => $this->unit,
            'loose_allowed' => $this->loose_allowed,
            'units'        => $this->units,
            // Section 4
            'classification'          => $this->classification,
            'category_id'             => $this->category_id,
            'prescription_required'   => $this->prescription_required,
            'classification_confirmed' => $this->classificationConfirmed,
            // Section 5
            'storage_condition'            => $this->storage_condition ?: null,
            'is_cold_chain'                => $this->is_cold_chain,
            'min_shelf_life_days'          => $this->min_shelf_life_days,
            'special_storage_instructions' => $this->special_storage_instructions ?: null,
            // Section 6
            'selling_price_santim'    => $this->sellingPriceInSantim(),
            'selling_price_reason'    => $this->sellingPriceReason,
            'selling_price_effective' => $this->sellingPriceEffective ?: now()->toIso8601String(),
            // Section 7
            'min_level'                  => $this->min_level,
            'reorder_level'              => $this->reorder_level,
            'max_level'                  => $this->max_level,
            'inventory_tracking_enabled' => $this->inventory_tracking_enabled,
            'batch_tracking_enabled'     => $this->batch_tracking_enabled,
            'expiry_tracking_enabled'    => $this->expiry_tracking_enabled,
            // Section 8
            'drug_information' => [
                'indications'                    => $this->indications ?: null,
                'dosage_guidance'                => $this->dosage_guidance ?: null,
                'administration_instructions'    => $this->administration_instructions ?: null,
                'contraindications'              => $this->contraindications ?: null,
                'side_effects'                   => $this->side_effects ?: null,
                'interactions'                   => $this->interactions ?: null,
                'warnings_precautions'           => $this->warnings_precautions ?: null,
                'patient_counseling_information' => $this->patient_counseling_information ?: null,
            ],
            // Supporting
            'preferred_supplier_name' => $this->preferred_supplier_name ?: null,
            // First batch
            'first_batch' => ($this->productId === null && $this->batchStarted) ? [
                'batch_no'          => trim($this->batchNo),
                'manufacturing_date' => $this->batchMfgDate,
                'expiry_date'        => $this->batchExpiryDate,
                'unit_cost'          => $this->batchBuyingPriceInSantim(),
                'quantity'           => (int) $this->batchQuantity,
                'supplier_name'      => trim($this->batchSupplier),
                'delivery_note_no'   => trim($this->batchDeliveryNo),
                'idempotency_key'    => $this->saveIdempotencyKey,
            ] : null,
        ];

        try {
            $product = $this->productId ? Product::findOrFail($this->productId) : null;
            $saved   = $action->execute($data, $product, $user);

            if ($this->imageFile && $user->hasPermission('catalogue.manage')) {
                $imageService = app(ProductImageService::class);
                if ($saved->image_path) {
                    $imageService->delete($saved->image_path, $saved->thumbnail_path ?? null);
                }
                $paths = $imageService->store($this->imageFile);
                $saved->update(['image_path' => $paths['image_path'], 'thumbnail_path' => $paths['thumbnail_path']]);
            }

            session()->flash('message', __('catalogue.saved_successfully'));
            return redirect()->route('catalogue.show', $saved);

        } catch (DuplicateProductException $e) {
            $this->duplicateProductId = $e->existingProduct->id;
            $this->duplicateMessage   = $e->getMessage();
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function render()
    {
        $user       = Auth::user();
        $canManage  = $user?->hasPermission('catalogue.manage') ?? false;
        $isEdit     = $this->productId !== null;

        $existingBatches = collect();
        if ($isEdit) {
            $existingBatches = Batch::where('product_id', $this->productId)
                ->orderBy('expiry_date')
                ->get();
        }

        return view('livewire.catalogue.form', [
            'categories'       => Category::active()->orderBy('name')->get(),
            'manufacturers'    => \Illuminate\Support\Facades\Schema::hasTable('manufacturers')
                                    ? Manufacturer::active()->orderBy('name')->get()
                                    : collect(),
            'dosageForms'      => \Illuminate\Support\Facades\Schema::hasTable('dosage_forms')
                                    ? DosageForm::active()->orderBy('name')->get()
                                    : collect(),
            'routes'           => \Illuminate\Support\Facades\Schema::hasTable('routes_of_administration')
                                    ? RouteOfAdministration::active()->orderBy('name')->get()
                                    : collect(),
            'therapeuticClasses' => \Illuminate\Support\Facades\Schema::hasTable('therapeutic_classes')
                                    ? TherapeuticClass::active()->orderBy('name')->get()
                                    : collect(),
            'canManage'        => $canManage,
            'isEdit'           => $isEdit,
            'existingBatches'  => $existingBatches,
        ])->layout('layouts.app', [
            'title' => $isEdit ? __('catalogue.edit_product') : __('catalogue.new_medicine'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function anyBatchFieldFilled(): bool
    {
        return $this->batchNo !== ''
            || $this->batchMfgDate !== ''
            || $this->batchExpiryDate !== ''
            || $this->batchBuyingPrice !== ''
            || $this->batchQuantity !== '';
    }

    private function sellingPriceInSantim(): ?int
    {
        $v = trim($this->sellingPrice);
        if ($v === '' || (float) $v <= 0) {
            return null;
        }
        return (int) round((float) $v * 100);
    }

    private function batchBuyingPriceInSantim(): ?int
    {
        $v = trim($this->batchBuyingPrice);
        if ($v === '' || (float) $v <= 0) {
            return null;
        }
        return (int) round((float) $v * 100);
    }

    private function hasErrors(): bool
    {
        return $this->getErrorBag()->isNotEmpty();
    }
}
