<?php

namespace App\Livewire\Dispense;

use App\Actions\Prescriptions\DispenseControlledDrug;
use App\Actions\Prescriptions\DispensePrescriptionLine;
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
 * Dispense a verified prescription line.
 *
 * Shows all lines with remaining quantities; proposes FEFO batch;
 * handles quantity override and repeat-warning acknowledge dialogs.
 *
 * @uc UC-21
 *
 * @fr FR-4.1, FR-4.8, FR-4.9, FR-4.10
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
        $this->prescription = $prescription->load(['lines.product', 'customer']);
        // Pre-select first dispensable line
        $first = $this->prescription->lines->first(fn ($l) => ! $l->isFullyDispensed());
        if ($first) {
            $this->selectedLineId = $first->id;
            $this->syncControlledState();
            $this->refreshBatchProposal();
        }
    }

    public function updatedSelectedLineId(): void
    {
        $this->quantity = 1;
        $this->batchId = null;
        $this->syncControlledState();
        $this->refreshBatchProposal();
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
                requestedQuantity: 1,
            );
            $this->batchId = $allocations[0]['batch']->id ?? null;
        } catch (\Throwable) {
            $this->batchId = null;
        }
    }

    private function selectedLine(): ?PrescriptionLine
    {
        return $this->prescription->lines->firstWhere('id', $this->selectedLineId);
    }

    public function dispense(DispensePrescriptionLine $action): void
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
            if ($this->isControlled) {
                $sale = app(DispenseControlledDrug::class)->execute(
                    user: $user,
                    prescription: $this->prescription,
                    lineId: $this->selectedLineId,
                    quantity: $this->quantity,
                    payments: $payments,
                    idempotencyKey: Str::uuid()->toString(),
                    batchId: $this->batchId ?: null,
                    overrideReason: $this->overrideReason ?: null,
                    repeatWarningAcknowledged: $this->repeatWarningAcknowledged,
                    confirmedPatientName: $this->confirmedPatientName ?: null,
                    confirmedPrescriberName: $this->confirmedPrescriberName ?: null,
                );
            } else {
                $sale = $action->execute(
                    user: $user,
                    prescription: $this->prescription,
                    lineId: $this->selectedLineId,
                    quantity: $this->quantity,
                    payments: $payments,
                    idempotencyKey: Str::uuid()->toString(),
                    batchId: $this->batchId ?: null,
                    overrideReason: $this->overrideReason ?: null,
                    repeatWarningAcknowledged: $this->repeatWarningAcknowledged,
                );
            }

            $this->successMessage = __('prescriptions.dispensing_success', [
                'receipt_no' => $sale->receipt_no,
            ]);

            // Refresh prescription to reflect new status
            $this->prescription->refresh()->load(['lines.product', 'customer']);
            $this->showOverrideModal = false;
            $this->showRepeatWarning = false;
            $this->overrideReason = '';
            $this->repeatWarningAcknowledged = false;

        } catch (QuantityExceedsPrescriptionException $e) {
            $this->showOverrideModal = true;
            $this->errorMessage = $e->getMessage();
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            // Detect repeat warning (a bit fragile but sufficient for Livewire flow)
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
