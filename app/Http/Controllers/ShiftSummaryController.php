<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ShiftSummaryController extends Controller
{
    public function show(Request $request, Shift $shift): View
    {
        $user = Auth::user();
        abort_unless($user?->hasPermission('shifts.open') || $user?->hasPermission('shifts.view'), 403);

        // Staff can only view their own shift summary
        if ($shift->user_id !== $user->id && ! $user->hasRole(Role::PharmacyManager) && ! $user->hasRole(Role::Owner)) {
            abort(403, __('shifts.unauthorized_to_access_other_shift'));
        }

        $shift->load(['till', 'user', 'cashMovements.user', 'sales' => fn ($q) => $q->where('status', 'completed')]);

        return view('shifts.summary', [
            'shift' => $shift,
        ]);
    }
}

