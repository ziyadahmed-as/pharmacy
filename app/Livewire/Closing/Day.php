<?php

namespace App\Livewire\Closing;

use App\Actions\Closing\LockDay;
use App\Enums\Role;
use App\Models\DailyClosing;
use App\Models\Shift;
use App\Services\DailySummaryCalculator;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Daily Closing & Day Lock')]
class Day extends Component
{
    public string $selectedDate = '';

    /** @var array<string, string|float|int> Counted amounts in ETB */
    public array $counted = [];

    /** @var array<string, string> */
    public array $varianceReasons = [];

    public string $notes = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(?string $date = null): void
    {
        $user = Auth::user();
        abort_unless($user?->hasRole(Role::PharmacyManager) || $user?->hasRole(Role::Owner), 403);

        $this->selectedDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();
        $this->loadDateData();
    }

    public function updatedSelectedDate(): void
    {
        $this->loadDateData();
    }

    public function loadDateData(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $systemTotals = DailySummaryCalculator::calculateSystemTotals($this->selectedDate);

        foreach (array_keys($systemTotals) as $method) {
            if (! isset($this->counted[$method])) {
                $this->counted[$method] = '';
            }
            if (! isset($this->varianceReasons[$method])) {
                $this->varianceReasons[$method] = '';
            }
        }
    }

    public function requestLockDay(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        // Check if there are open shifts on this date
        $openCount = Shift::whereDate('opened_at', $this->selectedDate)
            ->where('status', 'open')
            ->count();

        if ($openCount > 0) {
            $this->errorMessage = __('closing.open_shifts_exist_error', ['count' => $openCount]);

            return;
        }

        $systemTotals = DailySummaryCalculator::calculateSystemTotals($this->selectedDate);

        // Pre-validate variances & reasons
        foreach ($systemTotals as $method => $sysSantim) {
            $val = trim((string) ($this->counted[$method] ?? ''));
            $cntSantim = $val !== '' ? (int) round(((float) $val) * 100) : 0;
            $variance = $cntSantim - $sysSantim;

            if ($variance !== 0 && empty(trim($this->varianceReasons[$method] ?? ''))) {
                $this->addError("varianceReasons.{$method}", __('closing.variance_reason_required_for_method', [
                    'method' => ucfirst(str_replace('_', ' ', $method)),
                ]));

                return;
            }
        }

        // Trigger password re-authentication modal
        $this->dispatch('confirm-password', purpose: 'closing.lock_day');
    }

    #[On('password-confirmed')]
    public function handlePasswordConfirmed(string $purpose, LockDay $action): void
    {
        if ($purpose !== 'closing.lock_day') {
            return;
        }

        $this->errorMessage = null;

        // Convert ETB inputs to santim
        $countedSantim = [];
        foreach ($this->counted as $method => $amount) {
            $val = trim((string) $amount);
            $countedSantim[$method] = $val !== '' ? (int) round(((float) $val) * 100) : 0;
        }

        try {
            $closing = $action->execute(
                user: Auth::user(),
                date: $this->selectedDate,
                countedTotalsByMethod: $countedSantim,
                varianceReasons: $this->varianceReasons,
                notes: $this->notes,
                reauthConfirmed: true
            );

            session()->flash('status', __('closing.day_locked_successfully', ['date' => $this->selectedDate]));
            $this->redirect(route('closing.report', $closing), navigate: true);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        $existingClosing = DailyClosing::where('date', $this->selectedDate)->first();
        $isLocked = $existingClosing?->isLocked() ?? false;

        $shifts = DailySummaryCalculator::getShifts($this->selectedDate);
        $openShiftsCount = $shifts->where('status', 'open')->count();
        $heldSales = DailySummaryCalculator::getUnresolvedHeldSales($this->selectedDate);
        $metrics = DailySummaryCalculator::getTransactionMetrics($this->selectedDate);
        $systemTotals = DailySummaryCalculator::calculateSystemTotals($this->selectedDate);

        // Compute live variances for display
        $liveVariances = [];
        $liveVariancesEtb = [];
        $totalSystemSantim = array_sum($systemTotals);
        $totalCountedSantim = 0;

        foreach ($systemTotals as $method => $sys) {
            $val = trim((string) ($this->counted[$method] ?? ''));
            $cnt = $val !== '' ? (int) round(((float) $val) * 100) : 0;
            $totalCountedSantim += $cnt;
            $diff = $cnt - $sys;
            $liveVariances[$method] = $diff;
            $liveVariancesEtb[$method] = number_format($diff / 100, 2);
        }

        $totalVarianceSantim = $totalCountedSantim - $totalSystemSantim;

        return view('livewire.closing.day', [
            'existingClosing' => $existingClosing,
            'isLocked' => $isLocked,
            'shifts' => $shifts,
            'openShiftsCount' => $openShiftsCount,
            'heldSales' => $heldSales,
            'metrics' => $metrics,
            'systemTotals' => $systemTotals,
            'liveVariances' => $liveVariances,
            'liveVariancesEtb' => $liveVariancesEtb,
            'totalSystemEtb' => number_format($totalSystemSantim / 100, 2),
            'totalCountedEtb' => number_format($totalCountedSantim / 100, 2),
            'totalVarianceEtb' => number_format($totalVarianceSantim / 100, 2),
            'totalVarianceSantim' => $totalVarianceSantim,
        ]);
    }
}
