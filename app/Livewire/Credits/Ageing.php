<?php

namespace App\Livewire\Credits;

use App\Actions\Credits\ReceivablesAgeing as ReceivablesAgeingAction;
use App\Enums\Role;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Receivables ageing report (PM/Owner only).
 *
 * @uc UC-26
 */
class Ageing extends Component
{
    public function mount(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole([Role::PharmacyManager, Role::Owner]),
            403
        );
    }

    public function render()
    {
        $data = app(ReceivablesAgeingAction::class)->execute(Auth::user());

        $totals = [
            'current' => $data->where('bucket', 'current')->sum('balance'),
            '31_60' => $data->where('bucket', '31_60')->sum('balance'),
            '61_90' => $data->where('bucket', '61_90')->sum('balance'),
            '90_plus' => $data->where('bucket', '90_plus')->sum('balance'),
            'total' => $data->sum('balance'),
        ];

        return view('livewire.credits.ageing', [
            'accounts' => $data,
            'totals' => $totals,
        ])->layout('layouts.app');
    }
}
