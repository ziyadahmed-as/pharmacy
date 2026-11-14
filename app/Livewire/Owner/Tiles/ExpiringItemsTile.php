<?php

namespace App\Livewire\Owner\Tiles;

use App\Models\Batch;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class ExpiringItemsTile extends Component
{
    public function placeholder(): View
    {
        return view('livewire.owner.tiles.placeholder');
    }

    public function render(): View
    {
        $today = now()->toDateString();
        $thresholdDays = (int) setting('inventory.near_expiry_days', 90);
        $thresholdDate = now()->addDays($thresholdDays)->toDateString();

        // Expired batches with remaining stock
        $expiredCount = Batch::query()
            ->where('expiry_date', '<', $today)
            ->whereHas('batchStocks', function ($q) {
                $q->where('quantity', '>', 0);
            })
            ->count();

        // Near-expiry batches with remaining stock
        $nearExpiryBatches = Batch::query()
            ->with('product')
            ->whereBetween('expiry_date', [$today, $thresholdDate])
            ->whereHas('batchStocks', function ($q) {
                $q->where('quantity', '>', 0);
            })
            ->orderBy('expiry_date', 'asc')
            ->limit(3)
            ->get();

        $nearExpiryCount = Batch::query()
            ->whereBetween('expiry_date', [$today, $thresholdDate])
            ->whereHas('batchStocks', function ($q) {
                $q->where('quantity', '>', 0);
            })
            ->count();

        return view('livewire.owner.tiles.expiring-items-tile', [
            'nearExpiryCount' => $nearExpiryCount,
            'expiredCount' => $expiredCount,
            'thresholdDays' => $thresholdDays,
            'nearExpiryBatches' => $nearExpiryBatches,
            'reportUrl' => route('reports.run', ['type' => 'inventory-near-expiry']),
        ]);
    }
}
