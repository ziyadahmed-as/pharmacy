<?php

namespace App\Livewire\Owner\Tiles;

use App\Actions\Sales\SetSalesTarget;
use App\Models\Sale;
use App\Models\SalesTarget;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SalesTargetTile extends Component
{
    public string $period = '';

    public string $newTargetAmount = '';

    public bool $isEditing = false;

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
    }

    public function placeholder(): View
    {
        return view('livewire.owner.tiles.placeholder');
    }

    public function saveTarget(SetSalesTarget $action): void
    {
        $user = Auth::user();
        $amountEtb = (float) $this->newTargetAmount;
        if ($amountEtb <= 0) {
            $this->addError('newTargetAmount', __('sales.targets.amount_required'));

            return;
        }

        $action->execute($user, [
            'staff_id' => null,
            'period' => $this->period,
            'amount' => (int) round($amountEtb * 100),
        ]);

        $this->isEditing = false;
        $this->newTargetAmount = '';
    }

    public function render(): View
    {
        if (empty($this->period)) {
            $this->period = now()->format('Y-m');
        }

        $target = SalesTarget::whereNull('staff_id')
            ->where('period', $this->period)
            ->first();

        [$year, $month] = explode('-', $this->period);
        $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $achievedSantim = (int) Sale::where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->sum('total');

        $targetSantim = $target ? $target->amount : 0;
        $percentage = $targetSantim > 0 ? round(($achievedSantim / $targetSantim) * 100, 1) : 0.0;
        $remainingSantim = max(0, $targetSantim - $achievedSantim);

        $daysInMonth = (int) $end->format('d');
        $daysPassed = min($daysInMonth, (int) now()->format('d'));
        $daysRemaining = max(0, $daysInMonth - $daysPassed);

        return view('livewire.owner.tiles.sales-target-tile', [
            'target' => $target,
            'targetSantim' => $targetSantim,
            'achievedSantim' => $achievedSantim,
            'remainingSantim' => $remainingSantim,
            'percentage' => $percentage,
            'daysRemaining' => $daysRemaining,
            'periodLabel' => $start->format('F Y'),
        ]);
    }
}
