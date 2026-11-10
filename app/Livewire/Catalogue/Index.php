<?php

namespace App\Livewire\Catalogue;

use App\Actions\Catalogue\DeactivateProduct;
use App\Actions\Catalogue\ReactivateProduct;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $classification = '';

    #[Url]
    public string $categoryId = '';

    #[Url]
    public string $status = 'all'; // all, active, inactive

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function deactivate(int $productId, DeactivateProduct $action): void
    {
        $product = Product::findOrFail($productId);
        $action->execute($product, 'Deactivated via catalogue screen', Auth::user());
        session()->flash('message', __('catalogue.product_deactivated_success'));
    }

    public function reactivate(int $productId, ReactivateProduct $action): void
    {
        $product = Product::findOrFail($productId);
        $action->execute($product, 'Reactivated via catalogue screen', Auth::user());
        session()->flash('message', __('catalogue.product_reactivated_success'));
    }

    public function render()
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('catalogue.view') && ! $user->hasPermission('catalogue.manage'))) {
            abort(403);
        }

        $query = Product::query()->with(['category', 'units', 'latestPrice']);

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('brand', 'like', "%{$term}%")
                    ->orWhere('generic_name', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            });
        }

        if ($this->classification !== '') {
            $query->where('classification', $this->classification);
        }

        if ($this->categoryId !== '') {
            $query->where('category_id', $this->categoryId);
        }

        if ($this->status === 'active') {
            $query->where('is_active', true);
        } elseif ($this->status === 'inactive') {
            $query->where('is_active', false);
        }

        $products = $query->orderBy('brand')->paginate(20);
        $categories = Category::orderBy('name')->get();

        return view('livewire.catalogue.index', [
            'products' => $products,
            'categories' => $categories,
            'canManage' => $user->hasPermission('catalogue.manage'),
            'canEditBasic' => $user->hasPermission('catalogue.edit_basic'),
        ])->layout('layouts.app', ['title' => __('catalogue.title')]);
    }
}
