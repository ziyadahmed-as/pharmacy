<?php

namespace App\Livewire\Owner\Tiles;

use App\Enums\Role;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class ProfitLossTile extends Component
{
    public function placeholder(): View
    {
        return view('livewire.owner.tiles.placeholder');
    }

    public function render(): View
    {
        // Owner only guard
        $user = Auth::user();
        if (! $user || ! $user->hasRole(Role::Owner)) {
            return view('livewire.owner.tiles.unauthorized-tile');
        }

        $today = now()->toDateString();

        // 1. Revenue
        $grossSales = (int) Sale::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->sum('total');

        $refunds = (int) SaleReturn::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->sum('total_refund');

        $netRevenue = max(0, $grossSales - $refunds);

        // 2. Cost of Goods Sold (at batch unit cost)
        $cogs = (int) SaleLine::query()
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('batches', 'sale_lines.batch_id', '=', 'batches.id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', $today)
            ->select(DB::raw('SUM(sale_lines.quantity * batches.unit_cost) as total_cogs'))
            ->value('total_cogs');

        // 3. Gross Profit
        $grossProfit = $netRevenue - $cogs;

        // 4. Operating Expenses
        $expenses = (int) Expense::query()
            ->where('status', Expense::STATUS_POSTED)
            ->whereDate('date', $today)
            ->sum('amount');

        // 5. Net Profit
        $netProfit = $grossProfit - $expenses;

        $marginPercent = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 1) : 0.0;

        return view('livewire.owner.tiles.profit-loss-tile', [
            'netRevenue' => $netRevenue,
            'cogs' => $cogs,
            'grossProfit' => $grossProfit,
            'expenses' => $expenses,
            'netProfit' => $netProfit,
            'marginPercent' => $marginPercent,
            'reportUrl' => route('finance.cash-revenue'),
        ]);
    }
}
