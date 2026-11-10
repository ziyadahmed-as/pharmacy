<?php

namespace App\Livewire\Catalogue;

use App\Actions\Catalogue\SetSellingPrice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Services\PriceResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PricePanel extends Component
{
    #[Locked]
    public int $productId;

    public ?int $selectedUnitId = null;

    public string $price = '';

    public string $effectiveFrom = '';

    public string $reason = '';

    public bool $showChangeModal = false;

    public function mount(Product $product): void
    {
        $this->productId = $product->id;
        $this->effectiveFrom = now()->format('Y-m-d\TH:i');
    }

    public function openChangeModal(): void
    {
        $this->resetValidation();
        $this->price = '';
        $this->effectiveFrom = now()->format('Y-m-d\TH:i');
        $this->reason = '';
        $this->showChangeModal = true;
    }

    public function savePrice(SetSellingPrice $action): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('price.set')) {
            abort(403);
        }

        $this->validate([
            'price' => ['required', 'numeric', 'min:0.01'],
            'effectiveFrom' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $product = Product::findOrFail($this->productId);
        $unit = $this->selectedUnitId ? ProductUnit::find($this->selectedUnitId) : null;
        $minorPrice = (int) round(((float) $this->price) * 100);

        try {
            $action->execute(
                $product,
                $minorPrice,
                Carbon::parse($this->effectiveFrom),
                $this->reason,
                $unit,
                $user
            );

            $this->showChangeModal = false;
            $this->dispatch('price-updated');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function render()
    {
        $product = Product::with('units')->findOrFail($this->productId);
        $unit = $this->selectedUnitId ? ProductUnit::find($this->selectedUnitId) : null;

        $currentPrice = PriceResolver::at($product, $unit);

        $history = ProductPrice::where('product_id', $product->id)
            ->when($this->selectedUnitId, fn ($q) => $q->where('unit_id', $this->selectedUnitId), fn ($q) => $q->whereNull('unit_id'))
            ->with(['user', 'unit'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return view('livewire.catalogue.price-panel', [
            'product' => $product,
            'currentPrice' => $currentPrice,
            'history' => $history,
            'canSetPrice' => Auth::user()?->hasPermission('price.set') ?? false,
        ]);
    }
}
