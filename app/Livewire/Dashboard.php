<?php

namespace App\Livewire;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\ActingRole;
use App\Services\SettingsRepository;
use App\Support\SettingsCatalogue;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Role-aware Dashboard with quick stats, inventory indicators, alerts, and pending approvals.
 */
class Dashboard extends Component
{
    public function render(SettingsRepository $settings)
    {
        $user = Auth::user()->load('roles');

        $missing = [];
        if ($user->hasAnyRole(['owner', 'pharmacy_manager'])) {
            foreach (SettingsCatalogue::all() as $key => $def) {
                if ($def['required'] && $settings->isNotSet($key)) {
                    $missing[] = $key;
                }
            }
        }

        $pendingApprovals = $user->hasPermission('approvals.process')
            ? ApprovalRequest::query()->decidableBy($user)->count()
            : 0;

        $stats = [];
        if ($user->canAccessBusinessData()) {
            $stats['products_count'] = Product::where('is_active', true)->count();
            $stats['active_alerts_count'] = StockAlert::where('status', 'active')->count();
            $stats['critical_alerts_count'] = StockAlert::where('status', 'active')
                ->whereIn('type', ['critical_stock', 'expired'])->count();
            $stats['quarantine_quantity'] = (int) BatchStock::where('status', 'quarantine')->sum('quantity');
            $stats['pending_receipts_count'] = StockReceipt::where('status', 'pending_confirmation')->count();
        } else {
            // System Administrator
            $stats['total_users'] = User::count();
            $stats['active_users'] = User::where('account_status', User::STATUS_ACTIVE)->count();
            $stats['today_audits'] = AuditLog::whereDate('created_at', today())->count();
        }

        return view('livewire.dashboard', [
            'user' => $user,
            'actingRole' => ActingRole::for($user),
            'missingSettings' => $missing,
            'pendingApprovals' => $pendingApprovals,
            'stats' => $stats,
        ])->layout('layouts.app', ['title' => __('nav.dashboard')]);
    }
}
