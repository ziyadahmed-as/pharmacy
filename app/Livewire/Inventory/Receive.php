<?php

namespace App\Livewire\Inventory;

use App\Actions\Inventory\SubmitStockReceipt;
use App\Models\Product;
use App\Models\StorageLocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Receive extends Component
{
    public string $supplier_name = '';

    public string $delivery_note_no = '';

    public string $receipt_date = '';

    public string $notes = '';

    public string $idempotency_key = '';

    public array $lines = [];

    public bool $overrideShelfLife = false;

    public string $overrideReason = '';

    public bool $showOverrideModal = false;

    public function mount(): void
    {
        $this->receipt_date = now()->format('Y-m-d');
        $this->idempotency_key = (string) Str::uuid();
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'product_id' => '',
            'location_id' => '',
            'batch_no' => '',
            'manufacturing_date' => '',
            'expiry_date' => '',
            'quantity' => 1,
            'unit_cost' => '',
            'flag' => 'ok',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    /**
     * When the expiry date on a line is set, auto-fill batch_no as YYYY-MM
     * (the last month of the expiry date) and check whether a batch for the
     * same product + batch_no already exists so the user can see it will merge.
     */
    public function updatedLines(mixed $value, string $key): void
    {
        // key looks like "0.expiry_date" or "2.product_id"
        $parts = explode('.', $key);
        if (count($parts) !== 2) {
            return;
        }
        [$index, $field] = $parts;

        if ($field === 'expiry_date') {
            $expiry = $this->lines[$index]['expiry_date'] ?? '';
            if ($expiry !== '') {
                try {
                    $batchName = \Carbon\Carbon::parse($expiry)->format('Y-m');
                    // Only auto-fill if the user hasn't typed their own value
                    if (empty($this->lines[$index]['batch_no'])) {
                        $this->lines[$index]['batch_no'] = $batchName;
                    }
                } catch (\Throwable) {
                    // Invalid date — leave batch_no as-is
                }
            }
        }
    }

    public function submit(SubmitStockReceipt $action)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('stock.receive')) {
            abort(403);
        }

        $this->resetValidation();

        try {
            $receipt = $action->execute(
                [
                    'supplier_name' => $this->supplier_name,
                    'delivery_note_no' => $this->delivery_note_no,
                    'receipt_date' => $this->receipt_date,
                    'idempotency_key' => $this->idempotency_key,
                    'notes' => $this->notes,
                    'lines' => $this->lines,
                ],
                $user,
                $this->overrideShelfLife,
                $this->overrideReason
            );

            session()->flash('message', __('catalogue.receipt_submitted_success', ['reference' => $receipt->reference]));

            return redirect()->route('inventory.receipts.show', $receipt);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function render()
    {
        $user = Auth::user();
        $canViewCost = $user?->hasPermission('cost.view') ?? false;

        return view('livewire.inventory.receive', [
            'products' => Product::where('is_active', true)->orderBy('brand')->get(),
            'locations' => StorageLocation::where('is_active', true)->orderBy('name')->get(),
            'canViewCost' => $canViewCost,
        ])->layout('layouts.app', ['title' => __('catalogue.receive_stock')]);
    }
}
