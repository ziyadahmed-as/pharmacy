<?php

namespace App\Livewire\Catalogue;

use App\Actions\Catalogue\DuplicateProductException;
use App\Actions\Catalogue\SaveProduct;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
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

    public string $barcode = '';

    public string $storage_condition = '';

    public string $classification = 'otc';

    public bool $loose_allowed = false;

    public int $reorder_level = 0;

    public int $max_level = 0;

    public int $min_level = 0;

    public string $preferred_supplier_name = '';

    public array $units = [];

    // Duplicate detection state
    public ?int $duplicateProductId = null;

    public ?string $duplicateMessage = null;

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
            $this->barcode = $product->barcode ?? '';
            $this->storage_condition = $product->storage_condition ?? '';
            $this->classification = $product->classification ?? 'otc';
            $this->loose_allowed = $product->loose_allowed;
            $this->reorder_level = $product->reorder_level;
            $this->max_level = $product->max_level;
            $this->min_level = $product->min_level;
            $this->preferred_supplier_name = $product->preferred_supplier_name ?? '';

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
            'barcode' => $this->barcode,
            'storage_condition' => $this->storage_condition,
            'classification' => $this->classification,
            'loose_allowed' => $this->loose_allowed,
            'reorder_level' => $this->reorder_level,
            'max_level' => $this->max_level,
            'min_level' => $this->min_level,
            'preferred_supplier_name' => $this->preferred_supplier_name,
            'units' => $this->units,
        ];

        try {
            $product = $this->productId ? Product::findOrFail($this->productId) : null;
            $saved = $action->execute($data, $product, $user);

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
