<?php

namespace App\Livewire\Dispense;

use App\Actions\Prescriptions\DispensePartially;
use App\Exceptions\NoOpenShiftException;
use App\Exceptions\PrescriptionNotDispensableException;
use App\Exceptions\QuantityExceedsPrescriptionException;
use App\Models\Prescription;
use App\Models\PrescriptionLine;
use App\Services\FefoBatchResolver;
use App\Services\PriceResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Dispense a verified prescription line in full, in parts, or as a refill.
 *
 * Shows all lines with remaining quantities and refill counts; proposes FEFO batch;
 * handles partial quantities, next refill dates, overrides, and repeat-warning dialogs.
 *
 * @uc UC-21 @uc UC-24
 *
 * @fr FR-4.1, FR-4.5, FR-4.8, FR-4.9, FR-4.10; D-08
 */
class Show extends Component
{
    public Prescription $prescription;

    // Dispensing form
    public int $selectedLineId = 0;

    public int $quantity = 1;

    public ?int $batchId = null;

    public string $overrideReason = '';

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    // Partial and Refill State (UC-24)
    public bool $isRefill = false;

    public ?string $nextRefillDate = null;

    // Controlled drug confirmation
    public bool $isControlled = false;

    public string $confirmedPatientName = '';

    public string $confirmedPrescriberName = '';

    // State flags
    public bool $showOverrideModal = false;

    public bool $showRepeatWarning = false;

    public bool $repeatWarningAcknowledged = false;

    public string $errorMessage = '';

    public string $successMessage = '';

    protected function rules(): array
    {
        return [
            'selectedLineId' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
            'paymentMethod' => ['required', 'string'],
        ];
    }

    public function mount(Prescription $prescription): void
    {
        $this->prescription = $prescription->load(['lines.product', 'customer', 'prescriber']);
        // Pre-select first dispensable line
        $first = $this->prescription->lines->first(fn ($l) => ! $l->isFullyDispensed());
        if ($first) {
            $this->selectLine($first->id);
        }
    }

    public function selectLine(int $lineId, bool $refill = false): void
    {
        $this->selectedLineId = $lineId;
        $this->isRefill = $refill;
        $line = $this->selectedLine();

        if ($refill) {
            $this->quantity = $line ? $line->quantity_prescribed : 1;
        } else {
            $this->quantity = $line && $line->remainingQuantity() > 0 ? $line->remainingQuantity() : 1;
        }

        $this->batchId = null;
        $this->nextRefillDate = null;
        $this->syncControlledState();
        $this->refreshBatchProposal();
    }

    public function toggleRefill(): void
    {
        $line = $this->selectedLine();
        if (! $line) {
            return;
        }

        $this->isRefill = ! $this->isRefill;
        if ($this->isRefill) {
            $this->quantity = $line->quantity_prescribed;
        } else {
            $this->quantity = $line->remainingQuantity() > 0 ? $line->remainingQuantity() : 1;
        }
        $this->refreshBatchProposal();
    }

    public function updatedSelectedLineId(): void
    {
        $this->selectLine($this->selectedLineId);
    }

    private function syncControlledState(): void
    {
        $line = $this->selectedLine();
        $this->isControlled = $line?->product->classification === 'controlled';
        $this->confirmedPatientName = $this->prescription->customer->name;
        $this->confirmedPrescriberName = $this->prescription->prescriber_name;
    }

    private function refreshBatchProposal(): void
    {
        $line = $this->selectedLine();
        if (! $line) {
            return;
        }

        try {
            $allocations = FefoBatchResolver::resolve(
                product: $line->product,
                requestedQuantity: max(1, $this->quantity),
            );
            $this->batchId = $allocations[0]['batch']->id ?? null;
        } catch (\Throwable) {
            $this->batchId = null;
        }
    }

    public function selectedLine(): ?PrescriptionLine
    {
        return $this->prescription->lines->firstWhere('id', $this->selectedLineId);
    }

    public function dispense(DispensePartially $partiallyAction): void
    {
        $this->validate();
        $this->errorMessage = '';
        $this->successMessage = '';

        $user = auth()->user();
        $totalSantim = $this->computeTotal();

        if ($totalSantim <= 0) {
            $this->errorMessage = __('sales.no_price_configured', [
                'product' => $this->selectedLine()?->product->brand ?? '?',
            ]);

            return;
        }

        $payments = [
            [
                'method' => $this->paymentMethod,
                'amount' => $totalSantim,
                'reference' => $this->paymentReference ?: null,
            ],
        ];

        try {
            $sale = $partiallyAction->execute(
                user: $user,
                prescription: $this->prescription,
                lineId: $this->selectedLineId,
                quantity: $this->quantity,
                payments: $payments,
                idempotencyKey: Str::uuid()->toString(),
                batchId: $this->batchId ?: null,
                overrideReason: $this->overrideReason ?: null,
                repeatWarningAcknowledged: $this->repeatWarningAcknowledged,
                nextRefillDate: $this->nextRefillDate ?: null,
                isRefill: $this->isRefill,
            );

            $this->successMessage = $this->isRefill
                ? __('prescriptions.refill_success', ['receipt_no' => $sale->receipt_no])
                : __('prescriptions.dispensing_success', ['receipt_no' => $sale->receipt_no]);

            // Refresh prescription to reflect new status
            $this->prescription->refresh()->load(['lines.product', 'customer', 'prescriber']);
            $this->showOverrideModal = false;
            $this->showRepeatWarning = false;
            $this->overrideReason = '';
            $this->repeatWarningAcknowledged = false;
            $this->isRefill = false;
            $this->nextRefillDate = null;

        } catch (QuantityExceedsPrescriptionException $e) {
            $this->showOverrideModal = true;
            $this->errorMessage = $e->getMessage();
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, __('prescriptions.repeat_purchase_warning', [
                'days' => setting('prescriptions.repeat_purchase_warning_days', 0),
                'product' => $this->selectedLine()?->product->brand ?? '',
            ]))) {
                $this->showRepeatWarning = true;
            }
            $this->errorMessage = $msg;
        } catch (PrescriptionNotDispensableException|AuthorizationException|NoOpenShiftException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function acknowledgeRepeatWarning(): void
    {
        $this->repeatWarningAcknowledged = true;
        $this->showRepeatWarning = false;
    }

    private function computeTotal(): int
    {
        $line = $this->selectedLine();
        if (! $line) {
            return 0;
        }

        $price = PriceResolver::at($line->product, null);

        return $price ? (int) $price->price * $this->quantity : 0;
    }

    public function render(): View
    {
        return view('livewire.dispense.show')
            ->title(__('prescriptions.dispense'));
    }
}
