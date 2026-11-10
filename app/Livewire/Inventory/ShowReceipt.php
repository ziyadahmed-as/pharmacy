<?php

namespace App\Livewire\Inventory;

use App\Actions\Inventory\ConfirmStockReceipt;
use App\Models\StockReceipt;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ShowReceipt extends Component
{
    public StockReceipt $receipt;

    public function mount(StockReceipt $receipt): void
    {
        $this->receipt = $receipt->load(['lines.product', 'creator', 'confirmer']);
    }

    public function confirm(ConfirmStockReceipt $action): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('stock.receipt.confirm')) {
            abort(403);
        }

        $action->execute($this->receipt, $user, false);
        session()->flash('message', __('catalogue.receipt_confirmed_success'));
        $this->receipt->refresh();
    }

    public function reject(ConfirmStockReceipt $action): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('stock.receipt.confirm')) {
            abort(403);
        }

        $action->execute($this->receipt, $user, true);
        session()->flash('message', __('catalogue.receipt_rejected_success'));
        $this->receipt->refresh();
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.inventory.show-receipt', [
            'canConfirm' => $user?->hasPermission('stock.receipt.confirm') ?? false,
            'canViewCost' => $user?->hasPermission('cost.view') ?? false,
        ])->layout('layouts.app', ['title' => $this->receipt->reference]);
    }
}
