<?php

namespace App\Livewire\Owner\Tiles;

use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class SalesTodayTile extends Component
{
    public function placeholder(): View
    {
        return view('livewire.owner.tiles.placeholder');
    }

    public function render(): View
    {
        $today = business_date();

        $grossSales = (int) Sale::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->sum('total');

        $salesCount = Sale::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->count();

        $refunds = (int) SaleReturn::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->sum('total_refund');

        $netSales = max(0, $grossSales - $refunds);

        return view('livewire.owner.tiles.sales-today-tile', [
            'grossSales' => $grossSales,
            'salesCount' => $salesCount,
            'refunds' => $refunds,
            'netSales' => $netSales,
            'reportUrl' => route('reports.run', ['type' => 'sales-summary']),
        ]);
    }
}
