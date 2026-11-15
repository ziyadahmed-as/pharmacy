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

        $today = business_date();

        // 1. Revenue
        $grossSales = (int) Sale::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->sum('total');

        $refunds = (int) SaleReturn::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->sum('total_refund');

        $netRevenue = max(0, $grossSales - $refunds);

        // 4. Operating Expenses
        $expenses = (int) Expense::query()
            ->where('status', Expense::STATUS_POSTED)
            ->whereDate('date', $today)
            ->sum('amount');

        // BRD v2.0: show COGS/gross-profit only when the owner explicitly enables the setting.
        $showCostProfit = (bool) setting('reports.show_cost_profit', false);

        if ($showCostProfit) {
            // 2. Cost of Goods Sold (at batch unit cost)
            $cogs = (int) SaleLine::query()
                ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
                ->join('batches', 'sale_lines.batch_id', '=', 'batches.id')
                ->where('sales.status', 'completed')
                ->whereDate('sales.completed_at', $today)
                ->select(DB::raw('SUM(sale_lines.quantity * COALESCE(batches.unit_cost, 0)) as total_cogs'))
                ->value('total_cogs');

            // 3. Gross Profit
            $grossProfit = $netRevenue - $cogs;

            // 5. Net Profit (after COGS and expenses)
            $netProfit = $grossProfit - $expenses;
            $marginPercent = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 1) : 0.0;
        } else {
            // BRD v2.0 default: Operating Surplus = Net Sales − Operating Expenses (no COGS)
            $cogs = 0;
            $grossProfit = $netRevenue; // kept for backward-compat with existing tests
            $netProfit = $netRevenue - $expenses;
            $marginPercent = $netRevenue > 0 ? round(($netProfit / $netRevenue) * 100, 1) : 0.0;
        }

        return view('livewire.owner.tiles.profit-loss-tile', [
            'showCostProfit' => $showCostProfit,
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
