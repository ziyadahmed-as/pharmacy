<?php

namespace App\Livewire\Backup;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\RestoreRequest;
use App\Models\Shift;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Post-Restore Checklist')]
class RestoreChecklist extends Component
{
    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->isSystemAdmin(), 403);
        abort_unless($user->hasRole(Role::PharmacyManager) || $user->hasRole(Role::Owner), 403);
    }

    public function render(): View
    {
        $productCount = Product::count();
        $batchCount = Batch::count();
        $totalStockValue = Batch::with('batchStocks')->get()->sum(function ($batch) {
            return $batch->sellableStockQuantity() * ($batch->unit_cost ?? 0);
        });

        $pendingPrescriptions = Prescription::where('status', 'pending_verification')->count();
        $openShifts = Shift::where('status', 'open')->count();
        $auditRowsCount = AuditLog::count();

        $latestRestore = RestoreRequest::with('approvedBy')->latest()->first();

        return view('livewire.backup.restore-checklist', [
            'productCount' => $productCount,
            'batchCount' => $batchCount,
            'totalStockValue' => $totalStockValue,
            'pendingPrescriptions' => $pendingPrescriptions,
            'openShifts' => $openShifts,
            'auditRowsCount' => $auditRowsCount,
            'latestRestore' => $latestRestore,
        ]);
    }
}
