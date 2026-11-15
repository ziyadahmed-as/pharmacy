<?php

namespace App\Livewire\Owner\Tiles;

use App\Models\SaleLine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class TopSellersTile extends Component
{
    public function placeholder(): View
    {
        return view('livewire.owner.tiles.placeholder');
    }

    public function render(): View
    {
        $today = business_date();

        $topProducts = SaleLine::query()
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('products', 'sale_lines.product_id', '=', 'products.id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', $today)
            ->select(
                'products.id as product_id',
                'products.brand as brand',
                'products.generic_name as generic_name',
                DB::raw('SUM(sale_lines.quantity) as total_qty'),
                DB::raw('SUM(sale_lines.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.brand', 'products.generic_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // Fallback to recent 30 days if no sales today
        if ($topProducts->isEmpty()) {
            $topProducts = SaleLine::query()
                ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
                ->join('products', 'sale_lines.product_id', '=', 'products.id')
                ->where('sales.status', 'completed')
                ->where('sales.completed_at', '>=', now()->subDays(30))
                ->select(
                    'products.id as product_id',
                    'products.brand as brand',
                    'products.generic_name as generic_name',
                    DB::raw('SUM(sale_lines.quantity) as total_qty'),
                    DB::raw('SUM(sale_lines.subtotal) as total_revenue')
                )
                ->groupBy('products.id', 'products.brand', 'products.generic_name')
                ->orderByDesc('total_qty')
                ->limit(5)
                ->get();
        }

        return view('livewire.owner.tiles.top-sellers-tile', [
            'topProducts' => $topProducts,
            'reportUrl' => route('reports.run', ['type' => 'sales-by-product']),
        ]);
    }
}
