<?php

namespace App\Livewire\Owner\Tiles;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class CashTrendsTile extends Component
{
    public function placeholder(): View
    {
        return view('livewire.owner.tiles.placeholder');
    }

    public function render(): View
    {
        $today = now()->toDateString();

        // 1. Today's tender breakdown
        $cashTender = (int) Payment::query()
            ->join('sales', 'payments.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', $today)
            ->where('payments.method', 'cash')
            ->sum('payments.amount');

        $digitalTender = (int) Payment::query()
            ->join('sales', 'payments.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', $today)
            ->whereIn('payments.method', ['mobile_money', 'telebirr', 'cbe_birr', 'card', 'bank_transfer'])
            ->sum('payments.amount');

        // 2. 7-Day Sales Trend
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $dayGross = (int) Sale::where('status', 'completed')->whereDate('completed_at', $date)->sum('total');
            $dayRefunds = (int) SaleReturn::where('status', 'completed')->whereDate('completed_at', $date)->sum('total_refund');
            $dayNet = max(0, $dayGross - $dayRefunds);

            $trend[] = [
                'day' => now()->subDays($i)->format('D'),
                'date' => $date,
                'amount' => $dayNet,
                'amount_etb' => number_format($dayNet / 100, 2),
            ];
        }

        $maxTrendAmount = max(1, ...array_column($trend, 'amount'));

        return view('livewire.owner.tiles.cash-trends-tile', [
            'cashTender' => $cashTender,
            'digitalTender' => $digitalTender,
            'trend' => $trend,
            'maxTrendAmount' => $maxTrendAmount,
            'reportUrl' => route('finance.cash-revenue'),
        ]);
    }
}
