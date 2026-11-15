<?php

namespace App\Livewire\Finance\Payroll;

use App\Actions\Staff\Payroll\PreparePayroll;
use App\Actions\Staff\Payroll\RecordPayrollPayment;
use App\Actions\Staff\Payroll\SubmitPayrollApproval;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Main Payroll Management and Payslips view.
 *
 * Owner & PM: Prepare, view runs, submit approval, pay.
 * Staff: View and download own payslips.
 * SA: None (403).
 *
 * @uc UC-42 @fr FR-10.7 @br BR-13
 */
class Index extends Component
{
    #[Url]
    public string $selectedMonth = '';

    public ?int $viewRunId = null;

    public bool $showPrepareModal = false;

    public string $prepareMonth = '';

    public string $prepareNotes = '';

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || $user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            abort(403);
        }

        if (! $user->hasPermission('payroll.manage') &&
            ! $user->hasPermission('payroll.view') &&
            ! $user->hasPermission('payroll.view.own')) {
            abort(403);
        }

        $this->prepareMonth = now()->format('Y-m');
    }

    public function openPrepareModal(): void
    {
        if (! Auth::user()->hasPermission('payroll.manage')) {
            abort(403);
        }

        $this->showPrepareModal = true;
    }

    public function closePrepareModal(): void
    {
        $this->showPrepareModal = false;
        $this->resetValidation();
    }

    public function prepare(PreparePayroll $action): void
    {
        $user = Auth::user();
        if (! $user->hasPermission('payroll.manage')) {
            abort(403);
        }

        $this->validate([
            'prepareMonth' => 'required|regex:/^\d{4}-(0[1-9]|1[0-2])$/',
        ]);

        $run = $action->execute($user, $this->prepareMonth, [], $this->prepareNotes ?: null);

        session()->flash('success', __('payroll.draft_created_success'));
        $this->showPrepareModal = false;
        $this->viewRunId = $run->id;
    }

    public function submitApproval(int $id, SubmitPayrollApproval $action): void
    {
        $user = Auth::user();
        if (! $user->hasPermission('payroll.manage')) {
            abort(403);
        }

        $run = PayrollRun::findOrFail($id);
        $action->execute($user, $run);

        session()->flash('success', __('payroll.approval_submitted_success'));
    }

    public function recordPayment(int $id, RecordPayrollPayment $action): void
    {
        $user = Auth::user();
        if (! $user->hasPermission('payroll.manage')) {
            abort(403);
        }

        $run = PayrollRun::findOrFail($id);
        $action->execute($user, $run);

        session()->flash('success', __('payroll.payment_recorded_success'));
    }

    public function viewRun(int $id): void
    {
        $this->viewRunId = $id;
    }

    public function backToList(): void
    {
        $this->viewRunId = null;
    }

    public function render()
    {
        $user = Auth::user();
        $canManage = $user->hasPermission('payroll.manage') || $user->hasPermission('payroll.view');
        $staff = $user->staff;

        // If viewing specific run
        $currentRun = null;
        if ($this->viewRunId && $canManage) {
            $currentRun = PayrollRun::with(['lines.staff', 'preparer', 'approver', 'expense'])
                ->find($this->viewRunId);
        }

        $runs = $canManage
            ? PayrollRun::query()->latest('month')->get()
            : collect();

        // Staff own payslips
        $ownLines = $staff
            ? PayrollLine::query()
                ->with(['payrollRun'])
                ->where('staff_id', $staff->id)
                ->whereHas('payrollRun', fn ($q) => $q->whereIn('status', [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_PAID]))
                ->latest()
                ->get()
            : collect();

        return view('livewire.finance.payroll.index', [
            'canManage' => $canManage,
            'runs' => $runs,
            'currentRun' => $currentRun,
            'ownLines' => $ownLines,
            'staff' => $staff,
        ])->layout('layouts.app', ['title' => __('payroll.title')]);
    }
}
