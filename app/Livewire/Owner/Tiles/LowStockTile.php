<?php

namespace App\Livewire\Owner\Tiles;

use App\Models\Product;
use App\Models\StockAlert;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class LowStockTile extends Component
{
    public function placeholder(): View
    {
        return view('livewire.owner.tiles.placeholder');
    }

    public function render(): View
    {
        // 1. Total low stock alert count
        $activeAlertsCount = StockAlert::where('status', 'active')
            ->whereIn('type', ['low_stock', 'critical_stock', 'out_of_stock'])
            ->count();

        // 2. Query low stock items: products where total sellable stock <= reorder_level
        $lowStockProducts = Product::query()
            ->where('is_active', true)
            ->where('reorder_level', '>', 0)
            ->withSum(['batchStocks as sellable_stock' => function ($q) {
                $q->where('batch_stocks.status', 'sellable');
            }], 'quantity')
            ->get()
            ->filter(fn ($prod) => ((int) $prod->sellable_stock) <= (int) $prod->reorder_level)
            ->sortBy('sellable_stock')
            ->take(4);

        $totalLowStockCount = max($activeAlertsCount, $lowStockProducts->count());

        return view('livewire.owner.tiles.low-stock-tile', [
            'totalLowStockCount' => $totalLowStockCount,
            'lowStockProducts' => $lowStockProducts,
            'reportUrl' => route('reports.run', ['type' => 'inventory-low-stock']),
        ]);
    }
}
