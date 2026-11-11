<?php

namespace App\Livewire\Pos;

use App\Actions\Sales\CompleteSale;
use App\Exceptions\FefoOverrideException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PaymentMismatchException;
use App\Exceptions\SellabilityException;
use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Services\CurrentShift;
use App\Services\FefoBatchResolver;
use App\Services\PriceResolver;
use App\Services\SellabilityGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Point of Sale')]
class Sale extends Component
{
    /**
     * Search input for product barcode or name.
     */
    public string $search = '';

    /**
     * Search results dropdown.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $searchResults = [];

    /**
     * Cart lines.
     *
     * @var array<int, array{
     *     temp_id: string,
     *     product_id: int,
     *     brand: string,
     *     generic_name: string,
     *     classification: string,
     *     unit_id: int|null,
     *     unit_name: string,
     *     available_units: array<int, array{id: int, name: string, factor: int}>,
     *     quantity: int,
     *     unit_price: int,
     *     subtotal: int,
     *     batch_id: int|null,
     *     batch_no: string,
     *     expiry_date: string,
     *     is_fefo_override: bool,
     *     override_reason: string|null,
     *     available_batches: array<int, array{id: int, batch_no: string, expiry: string, stock: int}>
     * }>
     */
    public array $cart = [];

    /**
     * Payment lines: array of ['method' => string, 'amount' => float|string, 'reference' => string]
     *
     * @var array<int, array{method: string, amount: float|int|string, reference: string}>
     */
    public array $payments = [];

    /**
     * Selected payment method to add.
     */
    public string $selectedPaymentMethod = 'cash';

    public float|int|string $paymentAmountInput = 0;

    public string $paymentReferenceInput = '';

    /**
     * Idempotency key for this sale attempt.
     */
    public string $idempotencyKey = '';

    /**
     * Selected batch override modal state.
     */
    public bool $overrideModalOpen = false;

    public ?string $overrideCartTempId = null;

    public ?int $overrideSelectedBatchId = null;

    public string $overrideReason = '';

    /**
     * Error / Warning messages.
     */
    public ?string $errorMessage = null;

    public ?string $warningMessage = null;

    public ?int $lastCompletedSaleId = null;

    public ?string $lastCompletedReceiptNo = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->isSystemAdmin(), 403);

        $shift = CurrentShift::forUser($user);
        if (! $shift || ! $shift->isOpen()) {
            session()->flash('status', __('shifts.no_open_shift_error'));
            $this->redirect(route('shifts.open'), navigate: true);

            return;
        }

        $this->idempotencyKey = (string) Str::uuid();
        $this->selectedPaymentMethod = $this->getActivePaymentMethods()[0] ?? 'cash';
    }

    /**
     * List active payment methods from settings.
     *
     * @return list<string>
     */
    public function getActivePaymentMethods(): array
    {
        return (array) setting('sales.active_payment_methods', ['cash', 'mobile_money', 'card', 'bank_transfer']);
    }

    public function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash' => __('sales.payment_method_cash'),
            'mobile_money' => __('sales.payment_method_mobile_money'),
            'telebirr' => __('sales.payment_method_telebirr'),
            'cbe_birr' => __('sales.payment_method_cbe_birr'),
            'card' => __('sales.payment_method_card'),
            'bank_transfer' => __('sales.payment_method_bank_transfer'),
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    /**
     * Updated search query handler.
     */
    public function updatedSearch(): void
    {
        $query = trim($this->search);
        if (strlen($query) < 2) {
            $this->searchResults = [];

            return;
        }

        $products = Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('brand', 'like', "%{$query}%")
                    ->orWhere('generic_name', 'like', "%{$query}%")
                    ->orWhere('barcode', $query);
            })
            ->with(['units', 'batches.batchStocks' => fn ($q) => $q->where('status', 'sellable')])
            ->limit(8)
            ->get();

        $this->searchResults = $products->map(function (Product $product) {
            $eligibleBatches = FefoBatchResolver::getEligibleBatches($product);
            $totalStock = $eligibleBatches->sum(fn ($b) => $b->batchStocks->sum('quantity'));
            $earliest = $eligibleBatches->first();
            $basePrice = PriceResolver::at($product, null);

            return [
                'id' => $product->id,
                'brand' => $product->brand,
                'generic_name' => $product->generic_name,
                'dosage_form' => $product->dosage_form,
                'strength' => $product->strength,
                'classification' => $product->classification,
                'total_stock' => $totalStock,
                'price_etb' => $basePrice ? number_format($basePrice->price / 100, 2) : '0.00',
                'earliest_expiry' => $earliest?->expiry_date?->format('Y-m-d') ?? '-',
                'barcode' => $product->barcode,
            ];
        })->all();
    }

    /**
     * Scan or quick-add on barcode Enter press.
     */
    public function submitScan(): void
    {
        $code = trim($this->search);
        if ($code === '') {
            return;
        }

        $product = Product::where('is_active', true)
            ->where('barcode', $code)
            ->first();

        if ($product) {
            $this->addProduct($product->id);
            $this->search = '';
            $this->searchResults = [];
        } else {
            $this->updatedSearch();
            if (count($this->searchResults) === 1) {
                $this->addProduct($this->searchResults[0]['id']);
                $this->search = '';
                $this->searchResults = [];
            }
        }
    }

    /**
     * Add product to cart.
     */
    public function addProduct(int $productId): void
    {
        $this->errorMessage = null;

        $product = Product::with(['units'])->findOrFail($productId);

        // A3: Check classification
        if (in_array($product->classification, ['rx', 'controlled'], true)) {
            $this->warningMessage = __('sales.cart_contains_prescription_item', ['brand' => $product->brand]);
        }

        $eligibleBatches = FefoBatchResolver::getEligibleBatches($product);
        if ($eligibleBatches->isEmpty()) {
            $this->errorMessage = __('sales.no_sellable_stock_for_product', ['brand' => $product->brand]);

            return;
        }

        $earliestBatch = $eligibleBatches->first();

        // Check sellability
        try {
            SellabilityGuard::check($product, $earliestBatch, 1, Auth::user(), 'sell');
        } catch (SellabilityException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $priceRecord = PriceResolver::at($product, null);
        if (! $priceRecord) {
            $this->errorMessage = __('sales.no_price_configured', ['product' => $product->brand]);

            return;
        }

        $availableBatchesData = $eligibleBatches->map(function ($b) {
            return [
                'id' => $b->id,
                'batch_no' => $b->batch_no,
                'expiry' => $b->expiry_date?->format('Y-m-d') ?? '-',
                'stock' => (int) $b->batchStocks->sum('quantity'),
            ];
        })->all();

        $unitsData = [];
        if ($product->loose_allowed && $product->units->isNotEmpty()) {
            foreach ($product->units as $u) {
                $unitsData[] = [
                    'id' => $u->id,
                    'name' => $u->name,
                    'factor' => $u->factor,
                ];
            }
        }

        // Check if item already exists in cart with same unit & default batch
        foreach ($this->cart as $index => $item) {
            if ($item['product_id'] === $product->id && $item['unit_id'] === null && ! $item['is_fefo_override']) {
                $this->cart[$index]['quantity']++;
                $this->cart[$index]['subtotal'] = $this->cart[$index]['quantity'] * $this->cart[$index]['unit_price'];
                $this->recalculatePayments();
                $this->search = '';
                $this->searchResults = [];

                return;
            }
        }

        $this->cart[] = [
            'temp_id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'brand' => $product->brand,
            'generic_name' => $product->generic_name,
            'classification' => $product->classification,
            'unit_id' => null,
            'unit_name' => $product->unit ?: 'piece',
            'available_units' => $unitsData,
            'quantity' => 1,
            'unit_price' => (int) $priceRecord->price,
            'subtotal' => (int) $priceRecord->price,
            'batch_id' => $earliestBatch->id,
            'batch_no' => $earliestBatch->batch_no,
            'expiry_date' => $earliestBatch->expiry_date?->format('Y-m-d') ?? '-',
            'is_fefo_override' => false,
            'override_reason' => null,
            'available_batches' => $availableBatchesData,
        ];

        $this->search = '';
        $this->searchResults = [];
        $this->recalculatePayments();
    }

    /**
     * Change quantity for a cart line.
     */
    public function updateQuantity(string $tempId, int $delta): void
    {
        foreach ($this->cart as $index => $line) {
            if ($line['temp_id'] === $tempId) {
                $newQty = $line['quantity'] + $delta;
                if ($newQty <= 0) {
                    $this->removeLine($tempId);

                    return;
                }
                $this->cart[$index]['quantity'] = $newQty;
                $this->cart[$index]['subtotal'] = $newQty * $line['unit_price'];
                break;
            }
        }

        $this->recalculatePayments();
    }

    /**
     * Change unit (pack vs base/loose).
     */
    public function changeUnit(string $tempId, ?int $unitId): void
    {
        foreach ($this->cart as $index => $line) {
            if ($line['temp_id'] === $tempId) {
                $product = Product::findOrFail($line['product_id']);
                $unit = $unitId ? ProductUnit::find($unitId) : null;
                $price = PriceResolver::at($product, $unit);

                if (! $price) {
                    $this->errorMessage = __('sales.no_price_for_unit');

                    return;
                }

                $this->cart[$index]['unit_id'] = $unit?->id;
                $this->cart[$index]['unit_name'] = $unit ? $unit->name : ($product->unit ?: 'piece');
                $this->cart[$index]['unit_price'] = (int) $price->price;
                $this->cart[$index]['subtotal'] = $this->cart[$index]['quantity'] * (int) $price->price;
                break;
            }
        }

        $this->recalculatePayments();
    }

    /**
     * Remove line from cart.
     */
    public function removeLine(string $tempId): void
    {
        $this->cart = array_values(array_filter($this->cart, fn ($l) => $l['temp_id'] !== $tempId));
        $this->checkClassificationWarnings();
        $this->recalculatePayments();
    }

    /**
     * Open manual batch override modal (A2).
     */
    public function openBatchOverride(string $tempId): void
    {
        $this->overrideCartTempId = $tempId;
        $line = collect($this->cart)->firstWhere('temp_id', $tempId);
        if ($line) {
            $this->overrideSelectedBatchId = $line['batch_id'];
            $this->overrideReason = $line['override_reason'] ?? '';
            $this->overrideModalOpen = true;
        }
    }

    /**
     * Save manual batch override (A2).
     */
    public function saveBatchOverride(): void
    {
        $this->errorMessage = null;

        if (trim($this->overrideReason) === '') {
            $this->addError('overrideReason', __('sales.fefo_override_reason_required'));

            return;
        }

        foreach ($this->cart as $index => $line) {
            if ($line['temp_id'] === $this->overrideCartTempId) {
                $batch = Batch::findOrFail($this->overrideSelectedBatchId);
                $product = Product::findOrFail($line['product_id']);

                try {
                    SellabilityGuard::check($product, $batch, 1, Auth::user(), 'sell');
                } catch (SellabilityException $e) {
                    $this->errorMessage = $e->getMessage();

                    return;
                }

                $earliest = collect($line['available_batches'])->first();
                $isOverride = ($earliest && $earliest['id'] !== $batch->id);

                $this->cart[$index]['batch_id'] = $batch->id;
                $this->cart[$index]['batch_no'] = $batch->batch_no;
                $this->cart[$index]['expiry_date'] = $batch->expiry_date?->format('Y-m-d') ?? '-';
                $this->cart[$index]['is_fefo_override'] = $isOverride;
                $this->cart[$index]['override_reason'] = $isOverride ? trim($this->overrideReason) : null;
                break;
            }
        }

        $this->overrideModalOpen = false;
        $this->overrideCartTempId = null;
        $this->overrideReason = '';
    }

    /**
     * Add payment tender line (A1 split payments).
     */
    public function addPayment(): void
    {
        $this->errorMessage = null;

        $amountSantim = (int) round(((float) $this->paymentAmountInput) * 100);
        if ($amountSantim <= 0) {
            $this->errorMessage = __('sales.payment_amount_invalid');

            return;
        }

        $this->payments[] = [
            'method' => $this->selectedPaymentMethod,
            'amount' => $amountSantim,
            'reference' => trim($this->paymentReferenceInput),
        ];

        $this->paymentAmountInput = 0;
        $this->paymentReferenceInput = '';
    }

    /**
     * Remove payment line.
     */
    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    /**
     * Quick exact cash shortcut.
     */
    public function setExactCash(): void
    {
        $totalSantim = $this->getCartTotalSantim();
        $this->payments = [
            [
                'method' => 'cash',
                'amount' => $totalSantim,
                'reference' => '',
            ],
        ];
    }

    /**
     * Quick denomination cash button.
     */
    public function addCashDenomination(int $etb): void
    {
        $santim = $etb * 100;
        $this->payments[] = [
            'method' => 'cash',
            'amount' => $santim,
            'reference' => '',
        ];
    }

    /**
     * Automatically suggest remaining amount into input.
     */
    public function recalculatePayments(): void
    {
        $total = $this->getCartTotalSantim();
        $paid = $this->getTotalPaidSantim();
        $diff = max(0, $total - $paid);
        $this->paymentAmountInput = number_format($diff / 100, 2, '.', '');
    }

    /**
     * Check if Rx items are in cart.
     */
    private function checkClassificationWarnings(): void
    {
        $hasRx = collect($this->cart)->contains(fn ($l) => in_array($l['classification'], ['rx', 'controlled'], true));
        if (! $hasRx) {
            $this->warningMessage = null;
        }
    }

    public function getCartTotalSantim(): int
    {
        return (int) collect($this->cart)->sum('subtotal');
    }

    public function getTotalPaidSantim(): int
    {
        return (int) collect($this->payments)->sum('amount');
    }

    public function getBalanceSantim(): int
    {
        return $this->getCartTotalSantim() - $this->getTotalPaidSantim();
    }

    /**
     * Action A4: Hold Sale (Stub for UC-14).
     */
    public function holdSale(): void
    {
        if (empty($this->cart)) {
            $this->errorMessage = __('sales.empty_cart_error');

            return;
        }

        // Stub until UC-14 is built
        $this->cart = [];
        $this->payments = [];
        $this->warningMessage = null;
        $this->errorMessage = null;
        session()->flash('status', __('sales.cart_held_success'));
    }

    /**
     * Action: Complete Sale.
     */
    public function submitSale(CompleteSale $action): void
    {
        $this->errorMessage = null;

        if (empty($this->cart)) {
            $this->errorMessage = __('sales.empty_cart_error');

            return;
        }

        // Check if Rx or controlled
        $rxItem = collect($this->cart)->firstWhere(fn ($l) => in_array($l['classification'], ['rx', 'controlled'], true));
        if ($rxItem) {
            $this->errorMessage = __('sales.prescription_required_error', ['product' => $rxItem['brand']]);

            return;
        }

        if ($this->getBalanceSantim() !== 0) {
            $this->errorMessage = __('sales.payment_mismatch_error', [
                'total' => number_format($this->getCartTotalSantim() / 100, 2),
                'paid' => number_format($this->getTotalPaidSantim() / 100, 2),
            ]);

            return;
        }

        $items = array_map(function ($line) {
            return [
                'product_id' => $line['product_id'],
                'unit_id' => $line['unit_id'],
                'quantity' => $line['quantity'],
                'batch_id' => $line['is_fefo_override'] ? $line['batch_id'] : null,
                'override_reason' => $line['override_reason'],
            ];
        }, $this->cart);

        $payments = array_map(function ($p) {
            return [
                'method' => $p['method'],
                'amount' => (int) $p['amount'],
                'reference' => $p['reference'],
            ];
        }, $this->payments);

        try {
            $sale = $action->execute(
                user: Auth::user(),
                items: $items,
                payments: $payments,
                idempotencyKey: $this->idempotencyKey
            );

            $this->lastCompletedSaleId = $sale->id;
            $this->lastCompletedReceiptNo = $sale->receipt_no;

            // Reset cart and generate fresh idempotency key
            $this->cart = [];
            $this->payments = [];
            $this->warningMessage = null;
            $this->idempotencyKey = (string) Str::uuid();

            session()->flash('status', __('sales.sale_completed_successfully', ['receipt' => $sale->receipt_no]));

            $this->redirect(route('sales.receipt', ['sale' => $sale->id]), navigate: true);
        } catch (PaymentMismatchException|InsufficientStockException|FefoOverrideException|SellabilityException|\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        $currentShift = CurrentShift::forUser(Auth::user());

        return view('livewire.pos.sale', [
            'currentShift' => $currentShift,
            'activeMethods' => $this->getActivePaymentMethods(),
            'cartTotalSantim' => $this->getCartTotalSantim(),
            'totalPaidSantim' => $this->getTotalPaidSantim(),
            'balanceSantim' => $this->getBalanceSantim(),
        ]);
    }
}
