<?php

namespace App\Livewire\Inventory\Adjustments;

use App\Actions\Inventory\RequestStockAdjustment;
use App\Enums\Role;
use App\Models\AdjustmentRequest;
use App\Models\Batch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Request Stock Adjustment')]
class Create extends Component
{
    public string $product_search = '';

    public ?int $product_id = null;

    public ?int $batch_id = null;

    public string $reason_type = AdjustmentRequest::REASON_DAMAGE;

    public ?int $quantity = null;

    public string $written_reason = '';

    public ?int $second_person_id = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('stock.adjust.request'), 403);
    }

    public function updatedProductSearch(): void
    {
        $this->product_id = null;
        $this->batch_id = null;
    }

    public function selectProduct(int $productId): void
    {
        $this->product_id = $productId;
        $product = Product::find($productId);
        if ($product) {
            $this->product_search = trim($product->brand.' '.($product->strength ?? '').' ('.$product->generic_name.')');
        }
        $this->batch_id = null;
    }

    public function updatedProductId(): void
    {
        $this->batch_id = null;
    }

    public function getMatchingProductsProperty(): Collection
    {
        if (strlen(trim($this->product_search)) < 2 || $this->product_id) {
            return collect();
        }

        return Product::query()
            ->where(function ($q) {
                $q->where('brand', 'like', '%'.$this->product_search.'%')
                    ->orWhere('generic_name', 'like', '%'.$this->product_search.'%')
                    ->orWhere('barcode', 'like', '%'.$this->product_search.'%');
            })
            ->where('is_active', true)
            ->limit(8)
            ->get();
    }

    public function getAvailableBatchesProperty(): Collection
    {
        if (! $this->product_id) {
            return collect();
        }

        return Batch::query()
            ->where('product_id', $this->product_id)
            ->with(['batchStocks'])
            ->orderBy('expiry_date')
            ->get();
    }

    public function getSelectedBatchProperty(): ?Batch
    {
        if (! $this->batch_id) {
            return null;
        }

        return Batch::with(['product', 'batchStocks'])->find($this->batch_id);
    }

    public function getWitnessUsersProperty(): Collection
    {
        $user = Auth::user();

        return User::query()
            ->where('is_active', true)
            ->where('id', '!=', $user?->id)
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', Role::SystemAdmin->value);
            })
            ->orderBy('name')
            ->get();
    }

    public function getSignedQuantityProperty(): int
    {
        $qty = abs((int) $this->quantity);
        if ($this->reason_type === AdjustmentRequest::REASON_FOUND_STOCK) {
            return $qty;
        }

        return -$qty;
    }

    public function getProjectedStockProperty(): ?int
    {
        $batch = $this->selectedBatch;
        if (! $batch || ! $this->quantity) {
            return null;
        }

        $current = $batch->sellableStockQuantity();

        return $current + $this->signedQuantity;
    }

    public function getIsControlledProperty(): bool
    {
        $batch = $this->selectedBatch;

        return $batch && $batch->product->classification === 'controlled';
    }

    public function getRequiresRecountEstimatedProperty(): bool
    {
        $batch = $this->selectedBatch;
        if (! $batch || ! $this->quantity) {
            return false;
        }

        $thresholdEtb = setting('inventory.adjustment_value_threshold');
        if ($thresholdEtb === null || $thresholdEtb === '') {
            return true;
        }

        $thresholdSantim = (int) round(((float) $thresholdEtb) * 100);
        $estimatedValueSantim = abs($this->signedQuantity) * $batch->unit_cost;

        return $estimatedValueSantim > $thresholdSantim;
    }

    public function submit(RequestStockAdjustment $action)
    {
        $this->errorMessage = null;

        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $this->validate([
            'batch_id' => ['required', 'exists:batches,id'],
            'reason_type' => ['required', 'in:'.implode(',', [
                AdjustmentRequest::REASON_DAMAGE,
                AdjustmentRequest::REASON_LOSS,
                AdjustmentRequest::REASON_EXPIRY_WRITE_OFF,
                AdjustmentRequest::REASON_FOUND_STOCK,
            ])],
            'quantity' => ['required', 'integer', 'min:1'],
            'written_reason' => ['required', 'string', 'min:3', 'max:1000'],
            'second_person_id' => [
                $this->isControlled ? 'required' : 'nullable',
                'exists:users,id',
            ],
        ]);

        try {
            $action->execute($user, [
                'batch_id' => (int) $this->batch_id,
                'reason_type' => $this->reason_type,
                'quantity' => (int) $this->quantity,
                'written_reason' => $this->written_reason,
                'second_person_id' => $this->second_person_id ? (int) $this->second_person_id : null,
            ]);

            session()->flash('success', __('catalogue.adjustment_requested_successfully'));

            return $this->redirect(route('inventory.adjustments.index'), navigate: true);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.inventory.adjustments.create');
    }
}
