<?php

namespace App\Livewire\Catalogue;

use App\Actions\Catalogue\DuplicateProductException;
use App\Actions\Catalogue\SaveProduct;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\SaleLine;
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
 * Add / Edit Product form (UC-01, UC-02, UC-04).
 * OW and PM only. PP is refused with 403 on mount.
 *
 * @uc UC-01, UC-02, UC-04
 * @fr FR-1.1, FR-1.2, FR-1.3, FR-1.4, FR-1.5, FR-2.1
 * @br BR-06, BR-20
 */
class Form extends Component
{
    use WithFileUploads;

    // -------------------------------------------------------------------------
    // Product identity (locked after save so it can't be swapped by wire:model)
    // -------------------------------------------------------------------------

    #[Locked]
    public ?int $productId = null;

    // -------------------------------------------------------------------------
    // Section 1: Product Identification
    // -------------------------------------------------------------------------

    public string $brand            = '';
    public string $generic_name     = '';
    public string $strength         = '';
    public string $dosage_form      = '';
    public string $pack_size        = '';
    public string $unit             = 'piece';
    public string $product_code     = '';

    // -------------------------------------------------------------------------
    // Section 2: Classification & Sourcing
    // -------------------------------------------------------------------------

    public string $classification       = 'otc';
    public ?int   $category_id          = null;
    public string $barcode              = '';
    public string $manufacturer         = '';
    public string $storage_condition    = '';
    public string $preferred_supplier_name = '';
    public ?int   $min_shelf_life_days  = null;
    public bool   $loose_allowed        = false;
    public bool   $is_cold_chain        = false;

    /**
     * When the user changes classification on an existing product we require
     * them to tick this box, confirming the pharmacist-in-charge reviewed it.
     */
    public bool   $classificationConfirmed = false;
    public string $originalClassification  = '';

    // -------------------------------------------------------------------------
    // Section 3: Pricing (OW/PM only)
    // -------------------------------------------------------------------------

    /** Selling price as decimal ETB string (e.g. "45.00"). Empty = not set. */
    public string $sellingPrice         = '';
    public string $sellingPriceReason   = '';
    public string $sellingPriceEffective = '';

    // -------------------------------------------------------------------------
    // Section 4: Stock Levels (OW/PM only)
    // -------------------------------------------------------------------------

    public int $reorder_level = 0;
    public int $max_level     = 0;
    public int $min_level     = 0;

    // -------------------------------------------------------------------------
    // Section 5: Alternative Packaging Units
    // -------------------------------------------------------------------------

    /** @var array<int, array{name: string, factor: int}> */
    public array $units = [];

    // -------------------------------------------------------------------------
    // Section 6: Image (OW/PM only)
    // -------------------------------------------------------------------------

    public mixed  $imageFile           = null;
    public ?string $currentImagePath   = null;
    public ?string $currentThumbnailPath = null;

    // -------------------------------------------------------------------------
    // Section 7: First Batch (Add screen only) / Batch table (Edit screen)
    // -------------------------------------------------------------------------

    public string $batchNo          = '';
    public string $batchMfgDate     = '';
    public string $batchExpiryDate  = '';
    public string $batchBuyingPrice = '';   // decimal ETB
    public string $batchQuantity    = '';
    public string $batchSupplier    = '';
    public string $batchDeliveryNo  = '';

    /**
     * Whether the user has started filling in the batch fields (makes them required).
     * Computed reactively — any batch field filled = started.
     */
    public bool $batchStarted = false;

    /**
     * Idempotency key for the atomic product + batch save.
     * Regenerated after each successful save.
     */
    public string $saveIdempotencyKey = '';

    // -------------------------------------------------------------------------
    // Section 8: Clinical Drug Information
    // -------------------------------------------------------------------------

    public bool   $clinicalOpen    = false;
    public string $indications     = '';
    public string $dosage_guidance = '';
    public string $side_effects    = '';
    public string $contraindications = '';
    public string $interactions    = '';

    // -------------------------------------------------------------------------
    // Duplicate detection state
    // -------------------------------------------------------------------------

    public ?int    $duplicateProductId = null;
    public ?string $duplicateMessage   = null;

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function mount(?Product $product = null): void
    {
        $user = Auth::user();

        // PP is refused on the server, not just hidden in the UI.
        abort_unless($user && $user->hasPermission('catalogue.manage'), 403);

        $this->saveIdempotencyKey = (string) Str::uuid();
        $this->sellingPriceEffective = now()->format('Y-m-d\TH:i');

        if ($product && $product->exists) {
            $this->productId = $product->id;
            $this->brand = $product->brand;
            $this->generic_name = $product->generic_name;
            $this->strength = $product->strength ?? '';
            $this->dosage_form = $product->dosage_form ?? '';
            $this->pack_size = $product->pack_size ?? '';
            $this->unit = $product->unit ?? 'piece';
            $this->product_code = $product->product_code ?? '';
            $this->barcode = $product->barcode ?? '';
            $this->manufacturer = $product->manufacturer ?? '';
            $this->category_id = $product->category_id;
            $this->storage_condition = $product->storage_condition ?? '';
            $this->is_cold_chain = (bool) $product->is_cold_chain;
            $this->classification = $product->classification ?? 'otc';
            $this->originalClassification = $product->classification ?? 'otc';
            $this->loose_allowed = (bool) $product->loose_allowed;
            $this->reorder_level = (int) $product->reorder_level;
            $this->max_level = (int) $product->max_level;
            $this->min_level = (int) $product->min_level;
            $this->min_shelf_life_days = $product->min_shelf_life_days;
            $this->preferred_supplier_name = $product->preferred_supplier_name ?? '';
            $this->currentImagePath = $product->image_path;
            $this->currentThumbnailPath = $product->thumbnail_path ?? null;

            // Load current selling price (base unit only)
            $latestPrice = $product->latestPrice;
            if ($latestPrice) {
                $this->sellingPrice = number_format($latestPrice->price / 100, 2, '.', '');
            }

            if ($product->drugInformation) {
                $this->indications     = $product->drugInformation->indications ?? '';
                $this->dosage_guidance = $product->drugInformation->dosage_guidance ?? '';
                $this->side_effects    = $product->drugInformation->side_effects ?? '';
                $this->contraindications = $product->drugInformation->contraindications ?? '';
                $this->interactions    = $product->drugInformation->interactions ?? '';
                // Auto-open clinical section if any field has content.
                $this->clinicalOpen = $this->indications !== '' || $this->dosage_guidance !== '';
            }

            $this->units = $product->units
                ->map(fn ($u) => ['name' => $u->name, 'factor' => $u->factor])
                ->toArray();
        }
    }

    // -------------------------------------------------------------------------
    // Computed properties
    // -------------------------------------------------------------------------

    /**
     * True when the existing product already has stock or completed sales.
     * When true, the base unit field is locked.
     */
    #[Computed]
    public function unitIsLocked(): bool
    {
        if (! $this->productId) {
            return false;
        }

        // Has any sellable stock? Read from SSOT column on batches.
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

        // Has any completed SaleLine?
        return SaleLine::whereHas('sale', fn ($q) => $q->where('status', 'completed'))
            ->where('product_id', $this->productId)
            ->exists();
    }

    /**
     * Price-below-cost warning (non-blocking): selling price <= buying price.
     * Returns the warning string or null.
     */
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

    /**
     * Whether classification has been changed on an existing product.
     */
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
    public function updatedBatchExpiryDate():  void { $this->batchStarted = $this->anyBatchFieldFilled(); }
    public function updatedBatchBuyingPrice(): void { $this->batchStarted = $this->anyBatchFieldFilled(); }
    public function updatedBatchQuantity():    void { $this->batchStarted = $this->anyBatchFieldFilled(); }

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

        $this->currentImagePath    = null;
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

        // --- Validate stock levels cross-constraint ---
        if ($this->min_level > $this->reorder_level && $this->reorder_level > 0) {
            $this->addError('reorder_level', __('catalogue.level_min_gt_reorder'));
        }
        if ($this->reorder_level > $this->max_level && $this->max_level > 0) {
            $this->addError('max_level', __('catalogue.level_reorder_gt_max'));
        }

        // --- UI-level required field checks ---
        if (empty(trim($this->unit))) {
            $this->addError('unit', __('catalogue.base_unit_required'));
        }
        if (empty($this->category_id)) {
            $this->addError('category_id', __('catalogue.category_required'));
        }

        if ($this->hasErrors()) {
            return null;
        }

        // --- Classification change requires confirmation ---
        if ($this->classificationChanged && ! $this->classificationConfirmed) {
            $this->addError('classificationConfirmed', __('catalogue.classification_change_confirm_required'));
            return null;
        }

        // --- Unit locked check ---
        if ($this->unitIsLocked && $this->productId) {
            $existing = Product::find($this->productId);
            if ($existing && $existing->unit !== $this->unit) {
                $this->addError('unit', __('catalogue.base_unit_locked'));
                return null;
            }
        }

        // --- Batch field validation (only when user has started filling them) ---
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

            // Expiry must be after mfg date
            if (! empty($this->batchExpiryDate) && ! empty($this->batchMfgDate)) {
                if (strtotime($this->batchExpiryDate) <= strtotime($this->batchMfgDate)) {
                    $this->addError('batchExpiryDate', __('catalogue.expiry_must_be_after_mfg'));
                }
            }

            // Manufacturing date must not be in the future
            if (! empty($this->batchMfgDate) && strtotime($this->batchMfgDate) > time()) {
                $this->addError('batchMfgDate', __('catalogue.mfg_date_not_future'));
            }

            if ($this->hasErrors()) {
                return null;
            }
        }

        $data = [
            'brand'                  => $this->brand,
            'generic_name'           => $this->generic_name,
            'strength'               => $this->strength,
            'dosage_form'            => $this->dosage_form,
            'pack_size'              => $this->pack_size,
            'unit'                   => $this->unit,
            'manufacturer'           => $this->manufacturer,
            'category_id'            => $this->category_id,
            'product_code'           => $this->product_code ?: null,   // null → auto-generate in action
            'barcode'                => $this->barcode,
            'storage_condition'      => $this->storage_condition,
            'is_cold_chain'          => $this->is_cold_chain,
            'classification'         => $this->classification,
            'loose_allowed'          => $this->loose_allowed,
            'reorder_level'          => $this->reorder_level,
            'max_level'              => $this->max_level,
            'min_level'              => $this->min_level,
            'min_shelf_life_days'    => $this->min_shelf_life_days,
            'preferred_supplier_name'=> $this->preferred_supplier_name,
            'classification_confirmed' => $this->classificationConfirmed,
            'units'                  => $this->units,
            'drug_information'       => [
                'indications'      => $this->indications      ?: null,
                'dosage_guidance'  => $this->dosage_guidance  ?: null,
                'side_effects'     => $this->side_effects     ?: null,
                'contraindications'=> $this->contraindications ?: null,
                'interactions'     => $this->interactions     ?: null,
            ],
            // Pricing
            'selling_price_santim'    => $this->sellingPriceInSantim(),
            'selling_price_reason'    => $this->sellingPriceReason,
            'selling_price_effective' => $this->sellingPriceEffective ?: now()->toIso8601String(),
            // First batch (Add screen only; ignored if productId set)
            'first_batch'            => ($this->productId === null && $this->batchStarted) ? [
                'batch_no'          => trim($this->batchNo),
                'manufacturing_date'=> $this->batchMfgDate,
                'expiry_date'       => $this->batchExpiryDate,
                'unit_cost'         => $this->batchBuyingPriceInSantim(),
                'quantity'          => (int) $this->batchQuantity,
                'supplier_name'     => trim($this->batchSupplier),
                'delivery_note_no'  => trim($this->batchDeliveryNo),
                'idempotency_key'   => $this->saveIdempotencyKey,
            ] : null,
        ];

        try {
            $product = $this->productId ? Product::findOrFail($this->productId) : null;
            $saved   = $action->execute($data, $product, $user);

            // Handle image upload
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

        // Batch table for edit screen
        $existingBatches = collect();
        if ($isEdit) {
            $existingBatches = Batch::where('product_id', $this->productId)
                ->orderBy('expiry_date')
                ->get();   // quantity_on_hand / quarantine_qty are columns on the row
        }

        return view('livewire.catalogue.form', [
            'categories'      => Category::orderBy('name')->get(),
            'canManage'       => $canManage,
            'isEdit'          => $isEdit,
            'existingBatches' => $existingBatches,
        ])->layout('layouts.app', ['title' => $isEdit ? __('catalogue.edit_product') : __('catalogue.new_product')]);
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
