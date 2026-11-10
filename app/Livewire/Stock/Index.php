<?php

namespace App\Livewire\Stock;

use App\Models\Category;
use App\Models\Product;
use App\Services\StockOnHandQuery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Stock on Hand view with batch-level expansion and quarantine separation (UC-11 Step 2).
 * Strictly hides cost from pharmacy_staff (BR-06, AC-16).
 *
 * @uc UC-11 @fr FR-2.10, 7.2 @br BR-06 @ac AC-16
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = '';

    #[Url]
    public ?int $category_id = null;

    /** @var array<int, bool> */
    public array $expanded = [];

    public function mount(): void
    {
        $this->authorizeView();
    }

    private function authorizeView(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasPermission('stock.view'), 403);
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'filter', 'category_id'], true)) {
            $this->resetPage();
        }
    }

    public function toggleExpand(int $productId): void
    {
        if (isset($this->expanded[$productId])) {
            unset($this->expanded[$productId]);
        } else {
            $this->expanded[$productId] = true;
        }
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_filter([
            'search' => $this->search ?: null,
            'filter' => $this->filter ?: null,
            'category_id' => $this->category_id ?: null,
        ]);
    }

    public function render()
    {
        $this->authorizeView();
        $viewer = Auth::user();
        $canViewCost = $viewer->hasPermission('cost.view');

        $query = new StockOnHandQuery($this->filters());
        $products = $query->builder($viewer)->paginate(20);

        // Pre-compute metrics for each product on the page
        $productMetrics = [];
        foreach ($products as $product) {
            $productMetrics[$product->id] = StockOnHandQuery::metrics($product, $canViewCost);
        }

        return view('livewire.stock.index', [
            'products' => $products,
            'productMetrics' => $productMetrics,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'canViewCost' => $canViewCost,
            'canExport' => $canViewCost,
            'exportQuery' => http_build_query($this->filters()),
        ])->layout('layouts.app', ['title' => __('nav.stock_on_hand')]);
    }
}
