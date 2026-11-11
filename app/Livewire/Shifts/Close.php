<?php

namespace App\Livewire\Shifts;

use App\Actions\Shifts\CloseShift;
use App\Actions\Shifts\RecordCashMovement;
use App\Enums\Role;
use App\Models\HeldSale;
use App\Models\Shift;
use App\Services\CurrentShift;
use App\Services\ShiftExpectedCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Close Cash Shift')]
class Close extends Component
{
    #[Locked]
    public ?int $shiftId = null;

    /** @var array<string, string|float|int> Counted amounts in ETB */
    public array $counted = [];

    public string $varianceReason = '';

    public ?string $errorMessage = null;

    public int $heldSalesCount = 0;

    // Cash movement modal
    public bool $showCashMovementModal = false;

    public string $movementType = 'cash_in';

    public string $movementAmount = '';

    public string $movementReason = '';

    public ?string $movementError = null;

    public ?string $movementSuccess = null;

    public function mount(?int $shiftId = null): void
    {
        $user = Auth::user();
        abort_unless($user?->hasPermission('shifts.open'), 403);

        if ($shiftId) {
            $shift = Shift::with(['till', 'user'])->findOrFail($shiftId);
            // Staff may only close their own shift
            if ($shift->user_id !== $user->id && ! $user->hasRole(Role::PharmacyManager) && ! $user->hasRole(Role::Owner)) {
                abort(403, __('shifts.unauthorized_to_access_other_shift'));
            }
        } else {
            $shift = CurrentShift::forUser($user);
        }

        if ($shift) {
            $this->shiftId = $shift->id;
            $this->loadShiftState($shift);
        }
    }

    private function loadShiftState(Shift $shift): void
    {
        $expected = ShiftExpectedCalculator::calculate($shift);

        foreach (array_keys($expected) as $method) {
            if (! isset($this->counted[$method])) {
                $this->counted[$method] = '';
            }
        }

        $this->heldSalesCount = HeldSale::query()
            ->where('shift_id', $shift->id)
            ->where('status', 'held')
            ->count();
    }

    public function openCashMovementModal(): void
    {
        $this->movementType = 'cash_in';
        $this->movementAmount = '';
        $this->movementReason = '';
        $this->movementError = null;
        $this->movementSuccess = null;
        $this->showCashMovementModal = true;
    }

    public function closeCashMovementModal(): void
    {
        $this->showCashMovementModal = false;
        $this->movementError = null;
    }

    public function submitCashMovement(RecordCashMovement $action): void
    {
        $this->movementError = null;
        $this->movementSuccess = null;

        $this->validate([
            'movementType' => 'required|in:cash_in,cash_out',
            'movementAmount' => 'required|numeric|gt:0',
            'movementReason' => 'required|string|min:3|max:255',
        ], [
            'movementAmount.gt' => __('shifts.amount_must_be_positive'),
            'movementReason.required' => __('shifts.reason_required'),
        ]);

        try {
            $shift = Shift::findOrFail($this->shiftId);
            $amountSantim = (int) round(((float) $this->movementAmount) * 100);

            $action->execute(
                user: Auth::user(),
                shift: $shift,
                type: $this->movementType,
                amountSantim: $amountSantim,
                reason: $this->movementReason,
            );

            $this->showCashMovementModal = false;
            $this->movementSuccess = __('shifts.cash_movement_recorded');
            $this->loadShiftState($shift);
        } catch (\Throwable $e) {
            $this->movementError = $e->getMessage();
        }
    }

    public function closeShift(CloseShift $action): void
    {
        $this->errorMessage = null;

        if (! $this->shiftId) {
            $this->errorMessage = __('shifts.no_active_shift_to_close');

            return;
        }

        $shift = Shift::findOrFail($this->shiftId);
        $expected = ShiftExpectedCalculator::calculate($shift);

        // Convert counted ETB inputs to santim
        $countedSantim = [];
        foreach ($this->counted as $method => $amount) {
            $val = trim((string) $amount);
            $countedSantim[$method] = $val !== '' ? (int) round(((float) $val) * 100) : 0;
        }

        // Ensure all expected methods exist in counted
        foreach (array_keys($expected) as $method) {
            if (! isset($countedSantim[$method])) {
                $countedSantim[$method] = 0;
            }
        }

        $variance = array_sum($countedSantim) - array_sum($expected);

        if ($variance !== 0 && empty(trim($this->varianceReason))) {
            $this->addError('varianceReason', __('shifts.variance_reason_required'));

            return;
        }

        try {
            $action->execute(
                user: Auth::user(),
                shift: $shift,
                countedCashPerMethod: $countedSantim,
                varianceReason: $this->varianceReason,
            );

            session()->flash('status', __('shifts.shift_closed_successfully'));
            $this->redirect(route('shifts.summary', $shift), navigate: true);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        $shift = $this->shiftId ? Shift::with(['till', 'user', 'cashMovements.user'])->find($this->shiftId) : null;
        $expected = $shift ? ShiftExpectedCalculator::calculate($shift) : [];

        // Compute live counted total and variance
        $countedTotalSantim = 0;
        foreach ($this->counted as $amount) {
            $val = trim((string) $amount);
            if ($val !== '') {
                $countedTotalSantim += (int) round(((float) $val) * 100);
            }
        }

        $expectedTotalSantim = array_sum($expected);
        $liveVarianceSantim = $countedTotalSantim - $expectedTotalSantim;

        return view('livewire.shifts.close', [
            'shift' => $shift,
            'expected' => $expected,
            'countedTotalEtb' => number_format($countedTotalSantim / 100, 2),
            'expectedTotalEtb' => number_format($expectedTotalSantim / 100, 2),
            'liveVarianceEtb' => number_format($liveVarianceSantim / 100, 2),
            'liveVarianceSantim' => $liveVarianceSantim,
        ]);
    }
}
