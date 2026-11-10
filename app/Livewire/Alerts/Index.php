<?php

namespace App\Livewire\Alerts;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\StockAlert;
use App\Services\ReorderListQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Stock Alerts, Reorder List, and Near-Expiry Decision Panel (UC-05 Steps 3, 4, 5).
 * PM/Owner manage alerts; pharmacy_staff see alerts read-only.
 *
 * @uc UC-05 @fr FR-2.6, 2.7, 9.1; D-06 @ac AC-33
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'alerts'; // alerts | reorder | near_expiry

    #[Url]
    public string $type = ''; // '', 'critical_stock', 'low_stock', 'near_expiry', 'expired'

    public function mount(): void
    {
        $this->authorizeView();
    }

    private function authorizeView(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasPermission('stock.view'), 403);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function markSeen(int $alertId): void
    {
        $user = Auth::user();
        abort_unless($user && ($user->hasRole(Role::PharmacyManager) || $user->hasRole(Role::Owner)), 403);

        $alert = StockAlert::find($alertId);
        if ($alert && $alert->status === 'active') {
            $alert->update([
                'status' => 'seen',
                'seen_at' => now(),
                'seen_by' => $user->id,
            ]);
        }
    }

    public function markAllSeen(): void
    {
        $user = Auth::user();
        abort_unless($user && ($user->hasRole(Role::PharmacyManager) || $user->hasRole(Role::Owner)), 403);

        StockAlert::where('status', 'active')->update([
            'status' => 'seen',
            'seen_at' => now(),
            'seen_by' => $user->id,
        ]);
    }

    public function render()
    {
        $this->authorizeView();
        $user = Auth::user();
        $canManage = $user->hasRole(Role::PharmacyManager) || $user->hasRole(Role::Owner);

        // Alerts query
        $alerts = StockAlert::query()
            ->with(['product', 'batch'])
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(15);

        // Unseen alert counts
        $counts = [
            'total' => StockAlert::where('status', 'active')->count(),
            'critical' => StockAlert::where('status', 'active')->where('type', 'critical_stock')->count(),
            'low' => StockAlert::where('status', 'active')->where('type', 'low_stock')->count(),
            'near_expiry' => StockAlert::where('status', 'active')->where('type', 'near_expiry')->count(),
            'expired' => StockAlert::where('status', 'active')->where('type', 'expired')->count(),
        ];

        // Reorder list items
        $reorderItems = ($this->tab === 'reorder') ? ReorderListQuery::get() : collect();

        // Near-expiry panel batches grouped by window
        $nearExpiryBatches = collect();
        if ($this->tab === 'near_expiry') {
            $today = now()->timezone(config('pms.timezone'))->startOfDay();
            $maxDays = 90;

            $batches = Batch::query()
                ->with(['product', 'stocks'])
                ->whereHas('stocks', fn ($q) => $q->where('quantity', '>', 0))
                ->where('expiry_date', '<=', $today->copy()->addDays($maxDays)->format('Y-m-d'))
                ->orderBy('expiry_date')
                ->get();

            $nearExpiryBatches = $batches->map(function ($b) use ($today) {
                $exp = Carbon::parse($b->expiry_date)->startOfDay();
                $diff = (int) $today->diffInDays($exp, false);
                $qty = (int) $b->stocks->where('status', 'sellable')->sum('quantity');

                $window = 'expired';
                if ($diff > 60) {
                    $window = '61_90';
                } elseif ($diff > 30) {
                    $window = '31_60';
                } elseif ($diff > 0) {
                    $window = '0_30';
                }

                return [
                    'batch' => $b,
                    'days_remaining' => $diff,
                    'sellable_qty' => $qty,
                    'window' => $window,
                ];
            })->groupBy('window');
        }

        return view('livewire.alerts.index', [
            'alerts' => $alerts,
            'counts' => $counts,
            'reorderItems' => $reorderItems,
            'nearExpiryBatches' => $nearExpiryBatches,
            'canManage' => $canManage,
        ])->layout('layouts.app', ['title' => __('catalogue.alerts_title')]);
    }
}

