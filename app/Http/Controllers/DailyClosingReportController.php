<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\DailyClosing;
use App\Services\DailySummaryCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DailyClosingReportController extends Controller
{
    public function show(Request $request, DailyClosing $dailyClosing): View
    {
        $user = Auth::user();
        abort_unless($user?->hasRole(Role::PharmacyManager) || $user?->hasRole(Role::Owner), 403);

        $dailyClosing->load(['closer', 'verifier']);
        $shifts = DailySummaryCalculator::getShifts($dailyClosing->date);
        $metrics = DailySummaryCalculator::getTransactionMetrics($dailyClosing->date);

        return view('closing.report', [
            'closing' => $dailyClosing,
            'shifts' => $shifts,
            'metrics' => $metrics,
        ]);
    }
}
