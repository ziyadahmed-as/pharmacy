<?php

namespace App\Livewire\Catalogue;

use App\Actions\Catalogue\DuplicateProductException;
use App\Actions\Catalogue\SaveProduct;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductImageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;
    #[Locked]
    public ?int $productId = null;

    public string $brand = '';

    public string $generic_name = '';

    public string $strength = '';

    public string $dosage_form = '';

    public string $pack_size = '';

    public string $unit = 'piece';

    public string $manufacturer = '';

    public ?int $category_id = null;

    public string $product_code = '';

    public string $barcode = '';

    public string $storage_condition = '';

    public bool $is_cold_chain = false;

    public string $classification = 'otc';

    public bool $loose_allowed = false;

    public int $reorder_level = 0;

    public int $max_level = 0;

    public int $min_level = 0;

    public ?int $min_shelf_life_days = null;

    public string $preferred_supplier_name = '';

    // Clinical Drug Information (DDD 5.3)
    public string $indications = '';

    public string $dosage_guidance = '';

    public string $side_effects = '';

    public string $contraindications = '';

    public string $interactions = '';

    public array $units = [];

    // Duplicate detection state
    public ?int $duplicateProductId = null;

    public ?string $duplicateMessage = null;

    // Image upload state (FR-1.1)
    public mixed $imageFile = null;           // the TemporaryUploadedFile while pending

    /** Current image_path stored on the product (private disk). */
    public ?string $currentImagePath = null;

    /** Current thumbnail_path stored on the product (public disk). */
    public ?string $currentThumbnailPath = null;

    public function mount(?Product $product = null): void
    {
        if ($product && $product->exists) {
            $this->productId = $product->id;
            $this->brand = $product->brand;
            $this->generic_name = $product->generic_name;
            $this->strength = $product->strength ?? '';
            $this->dosage_form = $product->dosage_form ?? '';
            $this->pack_size = $product->pack_size ?? '';
            $this->unit = $product->unit ?? 'piece';
            $this->manufacturer = $product->manufacturer ?? '';
            $this->category_id = $product->category_id;
            $this->product_code = $product->product_code ?? '';
            $this->barcode = $product->barcode ?? '';
            $this->storage_condition = $product->storage_condition ?? '';
            $this->is_cold_chain = (bool) $product->is_cold_chain;
            $this->classification = $product->classification ?? 'otc';
            $this->loose_allowed = $product->loose_allowed;
            $this->reorder_level = $product->reorder_level;
            $this->max_level = $product->max_level;
            $this->min_level = $product->min_level;
            $this->min_shelf_life_days = $product->min_shelf_life_days;
            $this->preferred_supplier_name = $product->preferred_supplier_name ?? '';
            $this->currentImagePath = $product->image_path;
            $this->currentThumbnailPath = $product->thumbnail_path ?? null;

            if ($product->drugInformation) {
                $this->indications = $product->drugInformation->indications ?? '';
                $this->dosage_guidance = $product->drugInformation->dosage_guidance ?? '';
                $this->side_effects = $product->drugInformation->side_effects ?? '';
                $this->contraindications = $product->drugInformation->contraindications ?? '';
                $this->interactions = $product->drugInformation->interactions ?? '';
            }

            $this->units = $product->units->map(fn ($u) => ['name' => $u->name, 'factor' => $u->factor])->toArray();
        }
    }

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
    // Image upload handlers (FR-1.1)
    // -------------------------------------------------------------------------

    /**
     * Called by Livewire after the file input changes.
     * Validates immediately (size + MIME) so the user gets instant feedback.
     */
    public function updatedImageFile(): void
    {
        if (! $this->imageFile) {
            return;
        }
        try {
            // Let ProductImageService do the real MIME + size check at save time.
            // Here we do a lightweight Livewire validation for fast UI feedback.
            $this->validate([
                'imageFile' => ['file', 'max:2048', 'mimes:jpeg,jpg,png,webp'],
            ]);
        } catch (ValidationException $e) {
            $this->imageFile = null;
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    /**
     * Remove the existing image from the product (OW/PM only).
     */
    public function removeImage(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('catalogue.manage')) {
            abort(403);
        }

        if ($this->productId) {
            $product = Product::findOrFail($this->productId);
            app(ProductImageService::class)->delete(
                $product->image_path,
                $product->thumbnail_path ?? null
            );
            $product->update(['image_path' => null, 'thumbnail_path' => null]);
        }

        $this->currentImagePath = null;
        $this->currentThumbnailPath = null;
        $this->imageFile = null;
    }

    public function save(SaveProduct $action)
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('catalogue.manage') && ! $user->hasPermission('catalogue.edit_basic'))) {
            abort(403);
        }

        $this->resetValidation();
        $this->duplicateProductId = null;
        $this->duplicateMessage = null;

        $data = [
            'brand' => $this->brand,
            'generic_name' => $this->generic_name,
            'strength' => $this->strength,
            'dosage_form' => $this->dosage_form,
            'pack_size' => $this->pack_size,
            'unit' => $this->unit,
            'manufacturer' => $this->manufacturer,
            'category_id' => $this->category_id,
            'product_code' => $this->product_code ?: null,
            'barcode' => $this->barcode,
            'storage_condition' => $this->storage_condition,
            'is_cold_chain' => $this->is_cold_chain,
            'classification' => $this->classification,
            'loose_allowed' => $this->loose_allowed,
            'reorder_level' => $this->reorder_level,
            'max_level' => $this->max_level,
            'min_level' => $this->min_level,
            'min_shelf_life_days' => $this->min_shelf_life_days,
            'preferred_supplier_name' => $this->preferred_supplier_name,
            'units' => $this->units,
            'drug_information' => [
                'indications' => $this->indications ?: null,
                'dosage_guidance' => $this->dosage_guidance ?: null,
                'side_effects' => $this->side_effects ?: null,
                'contraindications' => $this->contraindications ?: null,
                'interactions' => $this->interactions ?: null,
            ],
        ];

        try {
            $product = $this->productId ? Product::findOrFail($this->productId) : null;
            $saved = $action->execute($data, $product, $user);

            // Process uploaded image (FR-1.1) — OW/PM only
            if ($this->imageFile && $user->hasPermission('catalogue.manage')) {
                $imageService = app(ProductImageService::class);

                // Delete old files before storing new ones
                if ($saved->image_path) {
                    $imageService->delete($saved->image_path, $saved->thumbnail_path ?? null);
                }

                $paths = $imageService->store($this->imageFile);
                $saved->update([
                    'image_path'      => $paths['image_path'],
                    'thumbnail_path'  => $paths['thumbnail_path'],
                ]);
            }

            session()->flash('message', __('catalogue.saved_successfully'));

            return redirect()->route('catalogue.show', $saved);
        } catch (DuplicateProductException $e) {
            $this->duplicateProductId = $e->existingProduct->id;
            $this->duplicateMessage = $e->getMessage();
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function render()
    {
        $user = Auth::user();
        $canManage = $user?->hasPermission('catalogue.manage') ?? false;

        return view('livewire.catalogue.form', [
            'categories' => Category::orderBy('name')->get(),
            'canManage' => $canManage,
            'isEdit' => $this->productId !== null,
        ])->layout('layouts.app', ['title' => $this->productId ? __('catalogue.edit_product') : __('catalogue.new_product')]);
    }
}
