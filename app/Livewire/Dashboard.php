<?php

namespace App\Livewire;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\BatchStock;
use App\Models\DispensingEvent;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockAlert;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\ActingRole;
use App\Services\CurrentShift;
use App\Services\SettingsRepository;
use App\Support\SettingsCatalogue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Role-aware Dashboard cooperating with user activity, cash shifts, sales, clinical queues, inventory, and system status.
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

        $pendingApprovals = ($user->hasPermission('approvals.process') && Schema::hasTable('approval_requests'))
            ? ApprovalRequest::query()->decidableBy($user)->count()
            : 0;

        $stats = [];
        $activeShift = null;
        $activeShiftStats = null;
        $recentSales = collect();
        $recentAudits = collect();

        if ($user->canAccessBusinessData()) {
            // 1. Core Inventory Stats
            $stats['products_count'] = Schema::hasTable('products') ? Product::where('is_active', true)->count() : 0;
            $stats['active_alerts_count'] = Schema::hasTable('stock_alerts') ? StockAlert::where('status', 'active')->count() : 0;
            $stats['critical_alerts_count'] = Schema::hasTable('stock_alerts') ? StockAlert::where('status', 'active')
                ->whereIn('type', ['critical_stock', 'expired'])->count() : 0;
            $stats['quarantine_quantity'] = Schema::hasTable('batch_stocks') ? (int) BatchStock::where('status', 'quarantine')->sum('quantity') : 0;
            $stats['pending_receipts_count'] = Schema::hasTable('stock_receipts') ? StockReceipt::where('status', 'pending_confirmation')->count() : 0;

            // 2. Active Cash Shift for current user
            if ($user->hasPermission('shifts.open') && Schema::hasTable('shifts')) {
                $activeShift = CurrentShift::forUser($user);
                if ($activeShift && Schema::hasTable('sales')) {
                    $activeShiftStats = [
                        'sales_count' => $activeShift->sales()->where('status', 'completed')->count(),
                        'sales_total' => (int) $activeShift->sales()->where('status', 'completed')->sum('total'),
                    ];
                }
            }

            // 3. Clinical & Prescription Activities
            if ($user->hasPermission('prescriptions.view') && Schema::hasTable('prescriptions')) {
                $stats['pending_verification_count'] = Prescription::where('status', Prescription::STATUS_PENDING_VERIFICATION)->count();
                $stats['ready_dispense_count'] = Prescription::whereIn('status', [
                    Prescription::STATUS_VERIFIED,
                    Prescription::STATUS_PARTIALLY_DISPENSED,
                ])->count();
                $stats['today_dispensed_count'] = Schema::hasTable('dispensing_events')
                    ? DispensingEvent::whereDate('dispensed_at', today())->count()
                    : 0;
            }

            // 4. Management & Store Totals (Owner & PM only - staff never see store margins/costs)
            if ($user->hasAnyRole(['owner', 'pharmacy_manager'])) {
                if (Schema::hasTable('sales')) {
                    $stats['today_sales_revenue'] = (int) Sale::whereDate('completed_at', today())
                        ->where('status', 'completed')
                        ->sum('total');
                    $stats['today_sales_count'] = Sale::whereDate('completed_at', today())
                        ->where('status', 'completed')
                        ->count();

                    // Recent sales store-wide
                    $recentSales = Sale::with(['user', 'customer'])
                        ->latest('completed_at')
                        ->take(5)
                        ->get();
                } else {
                    $stats['today_sales_revenue'] = 0;
                    $stats['today_sales_count'] = 0;
                }

                $stats['open_shifts_count'] = Schema::hasTable('shifts') ? Shift::where('status', 'open')->count() : 0;
            } elseif ($user->hasPermission('shifts.open') && Schema::hasTable('sales')) {
                // For Pharmacy Staff: recent transactions by this user
                $recentSales = Sale::with(['customer'])
                    ->where('user_id', $user->id)
                    ->latest('completed_at')
                    ->take(5)
                    ->get();
            }
        } else {
            // System Administrator (Technical & Security metrics only — zero business data)
            $stats['total_users'] = Schema::hasTable('users') ? User::count() : 0;
            $stats['active_users'] = Schema::hasTable('users') ? User::where('account_status', User::STATUS_ACTIVE)->count() : 0;

            if (Schema::hasTable('audit_logs')) {
                $stats['today_audits'] = AuditLog::whereDate('created_at', today())->count();
                $stats['failed_logins_today'] = AuditLog::where('action', 'auth.login.failed')
                    ->whereDate('created_at', today())
                    ->count();

                $recentAudits = AuditLog::with('user')
                    ->whereIn('action', [
                        'auth.login.success',
                        'auth.login.failed',
                        'auth.logout',
                        'user.created',
                        'user.approved',
                        'user.deactivated',
                        'settings.value.updated',
                        'audit.exported',
                    ])
                    ->latest()
                    ->take(6)
                    ->get();
            } else {
                $stats['today_audits'] = 0;
                $stats['failed_logins_today'] = 0;
            }
        }

        return view('livewire.dashboard', [
            'user' => $user,
            'actingRole' => ActingRole::for($user),
            'missingSettings' => $missing,
            'pendingApprovals' => $pendingApprovals,
            'stats' => $stats,
            'activeShift' => $activeShift,
            'activeShiftStats' => $activeShiftStats,
            'recentSales' => $recentSales,
            'recentAudits' => $recentAudits,
            'handlesControlledDrugs' => (bool) setting('pharmacy.handles_controlled_drugs', true),
        ])->layout('layouts.app', ['title' => __('nav.dashboard')]);
    }
}
