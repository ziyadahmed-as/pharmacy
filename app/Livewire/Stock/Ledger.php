<?php

namespace App\Livewire\Stock;

use App\Models\Batch;
use App\Models\Product;
use App\Services\LedgerQuery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Append-only stock movements ledger view (UC-11 Step 3).
 * Read-only; no modification methods exist (E1).
 *
 * @uc UC-11 @fr FR-2.10, 7.2 @ac AC-16
 */
class Ledger extends Component
{
    use WithPagination;

    #[Url]
    public ?int $product_id = null;

    #[Url]
    public ?int $batch_id = null;

    #[Url]
    public string $type = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $search = '';

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
        if (in_array($property, ['product_id', 'batch_id', 'type', 'from', 'to', 'search'], true)) {
            $this->resetPage();
        }
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_filter([
            'product_id' => $this->product_id ?: null,
            'batch_id' => $this->batch_id ?: null,
            'type' => $this->type ?: null,
            'from' => $this->from ?: null,
            'to' => $this->to ?: null,
            'search' => $this->search ?: null,
        ]);
    }

    public function render()
    {
        $this->authorizeView();
        $viewer = Auth::user();
        $canViewCost = $viewer->hasPermission('cost.view');

        $query = new LedgerQuery($this->filters());
        $movements = $query->builder($viewer)->paginate(25);

        $selectedProduct = $this->product_id ? Product::find($this->product_id) : null;
        $selectedBatch = $this->batch_id ? Batch::with('product')->find($this->batch_id) : null;

        return view('livewire.stock.ledger', [
            'movements' => $movements,
            'selectedProduct' => $selectedProduct,
            'selectedBatch' => $selectedBatch,
            'canExport' => $canViewCost,
            'exportQuery' => http_build_query($this->filters()),
        ])->layout('layouts.app', ['title' => __('nav.stock_ledger')]);
    }
}

