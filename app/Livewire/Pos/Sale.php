<?php

namespace App\Livewire\Pos;

use App\Actions\Sales\CompleteSale;
use App\Actions\Sales\DiscardHeldSale;
use App\Actions\Sales\HoldSale;
use App\Actions\Sales\ResumeSale;
use App\Exceptions\FefoOverrideException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PaymentMismatchException;
use App\Exceptions\SellabilityException;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\HeldSale;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Services\CurrentShift;
use App\Services\FefoBatchResolver;
use App\Services\NearestExpiryQuery;
use App\Services\PriceResolver;
use App\Services\SellabilityGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Point of Sale')]
class Sale extends Component
{
    use WithPagination;

    // Pagination for the browse panel uses a named paginator so it doesn't
    // collide with any other paginated component on the page.
    protected string $paginationTheme = 'tailwind';

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
     * Held sales state.
     */
    public bool $holdModalOpen = false;

    public string $holdReferenceInput = '';

    public bool $heldSalesModalOpen = false;

    /**
     * Customer selection & Credit panel state (UC-26).
     */
    public ?int $selectedCustomerId = null;

    public string $customerSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $customerSearchResults = [];

    // -------------------------------------------------------------------------
    // Product-browse panel state (UC-13 — nearest-expiry-first grid)
    // -------------------------------------------------------------------------

    /**
     * Whether the product-browse panel is open (toggled by the seller).
     * Defaults to open so sellers see the grid immediately on shift start.
     */
    public bool $browseOpen = true;

    /** Free-text filter for the browse panel (name / generic / barcode). */
    public string $browseSearch = '';

    /** Items per page in the browse panel. */
    public int $browsePerPage = 20;

    /** @var array<int, string> */
    public array $resumeWarnings = [];

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
     * Credit is added only for credit customers (UC-26 FR-3.4).
     *
     * @return list<string>
     */
    public function getActivePaymentMethods(): array
    {
        $methods = (array) setting('sales.active_payment_methods', ['cash', 'mobile_money', 'card', 'bank_transfer']);

        if ($this->selectedCustomer?->creditAccount) {
            $methods[] = 'credit';
        }

        return array_values(array_unique($methods));
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
            'credit' => __('credits.credit_payment_method'),
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public function updatedCustomerSearch(): void
    {
        $q = trim($this->customerSearch);
        if (strlen($q) < 2) {
            $this->customerSearchResults = [];

            return;
        }

        $this->customerSearchResults = Customer::search($q)
            ->with('creditAccount')
            ->limit(5)
            ->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'has_credit' => (bool) $c->creditAccount,
                'credit_limit' => $c->creditAccount?->credit_limit ?? 0,
                'balance' => $c->creditAccount?->balance ?? 0,
                'available_credit' => $c->creditAccount?->availableCredit() ?? 0,
            ])
            ->toArray();
    }

    public function selectCustomer(int $id): void
    {
        $this->selectedCustomerId = $id;
        $this->customerSearch = '';
        $this->customerSearchResults = [];
    }

    public function clearCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->customerSearch = '';
        $this->customerSearchResults = [];

        if ($this->selectedPaymentMethod === 'credit') {
            $this->selectedPaymentMethod = 'cash';
        }
    }

    // -------------------------------------------------------------------------
    // Browse panel helpers
    // -------------------------------------------------------------------------

    public function toggleBrowse(): void
    {
        $this->browseOpen = ! $this->browseOpen;
    }

    public function updatedBrowseSearch(): void
    {
        $this->resetPage('browsePage');
    }

    /**
     * Re-render after a sale completes so quantities in the browse panel
     * reflect the deducted stock without a full page reload.
     *
     * Livewire calls this automatically when the 'browse-refresh' event fires
     * (dispatched by submitSale after a successful CompleteSale).
     */
    #[On('browse-refresh')]
    public function onBrowseRefresh(): void
    {
        // No body needed: the attribute causes Livewire to re-run render().
    }

    public function getSelectedCustomerProperty(): ?Customer
    {
        return $this->selectedCustomerId ? Customer::with('creditAccount')->find($this->selectedCustomerId) : null;
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
                'id'              => $product->id,
                'brand'           => $product->brand,
                'generic_name'    => $product->generic_name,
                'dosage_form'     => $product->dosage_form,
                'strength'        => $product->strength,
                'classification'  => $product->classification,
                'total_stock'     => $totalStock,
                'price_etb'       => $basePrice ? number_format($basePrice->price / 100, 2) : '0.00',
                'earliest_expiry' => $earliest?->expiry_date?->format('Y-m-d') ?? '-',
                'barcode'         => $product->barcode,
                'thumbnail_url'   => \App\Services\ProductImageService::thumbnailUrl($product->thumbnail_path ?? null),
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
            'temp_id'          => (string) Str::uuid(),
            'product_id'       => $product->id,
            'brand'            => $product->brand,
            'generic_name'     => $product->generic_name,
            'classification'   => $product->classification,
            'unit_id'          => null,
            'unit_name'        => $product->unit ?: 'piece',
            'available_units'  => $unitsData,
            'quantity'         => 1,
            'unit_price'       => (int) $priceRecord->price,
            'subtotal'         => (int) $priceRecord->price,
            'batch_id'         => $earliestBatch->id,
            'batch_no'         => $earliestBatch->batch_no,
            'expiry_date'      => $earliestBatch->expiry_date?->format('Y-m-d') ?? '-',
            'is_fefo_override' => false,
            'override_reason'  => null,
            'available_batches' => $availableBatchesData,
            'thumbnail_url'    => \App\Services\ProductImageService::thumbnailUrl($product->thumbnail_path ?? null),
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
     * Action: Hold Sale (UC-14).
     */
    public function openHoldModal(): void
    {
        if (empty($this->cart)) {
            $this->errorMessage = __('sales.empty_cart_error');

            return;
        }

        $this->holdReferenceInput = '';
        $this->holdModalOpen = true;
    }

    public function confirmHold(HoldSale $action): void
    {
        $this->errorMessage = null;

        if (empty($this->cart)) {
            $this->errorMessage = __('sales.empty_cart_error');
            $this->holdModalOpen = false;

            return;
        }

        try {
            $heldSale = $action->execute(
                user: Auth::user(),
                shift: CurrentShift::requireForUser(Auth::user()),
                cart: $this->cart,
                reference: $this->holdReferenceInput
            );

            $this->cart = [];
            $this->payments = [];
            $this->warningMessage = null;
            $this->resumeWarnings = [];
            $this->holdModalOpen = false;
            $this->holdReferenceInput = '';

            session()->flash('status', __('sales.cart_held_success', ['ref' => $heldSale->reference ?: "#{$heldSale->id}"]));
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openHeldSalesModal(): void
    {
        $this->heldSalesModalOpen = true;
    }

    public function resumeSale(int $heldSaleId, ResumeSale $action): void
    {
        $this->errorMessage = null;
        $this->resumeWarnings = [];

        try {
            $heldSale = HeldSale::findOrFail($heldSaleId);
            $result = $action->execute(
                user: Auth::user(),
                heldSale: $heldSale,
                shift: CurrentShift::requireForUser(Auth::user())
            );

            $this->cart = $result['cart'];
            $this->resumeWarnings = $result['warnings'];
            $this->payments = [];
            $this->recalculatePayments();
            $this->heldSalesModalOpen = false;

            if ($result['has_issues']) {
                $this->errorMessage = __('sales.resumed_with_issues_warning');
            } else {
                session()->flash('status', __('sales.sale_resumed_successfully'));
            }
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function discardSale(int $heldSaleId, DiscardHeldSale $action, ?string $reason = null): void
    {
        $this->errorMessage = null;

        try {
            $heldSale = HeldSale::findOrFail($heldSaleId);
            $action->execute(Auth::user(), $heldSale, $reason);

            session()->flash('status', __('sales.held_sale_discarded_success'));
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
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

        // Check if any item in cart is invalid or sold out (A1)
        $invalidItem = collect($this->cart)->firstWhere('invalid', true);
        if ($invalidItem) {
            $this->errorMessage = __('sales.cannot_complete_sale_with_invalid_items');

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
                idempotencyKey: $this->idempotencyKey,
                prescriptionId: null,
                patientName: null,
                prescriberName: null,
                customerId: $this->selectedCustomerId
            );

            $this->lastCompletedSaleId = $sale->id;
            $this->lastCompletedReceiptNo = $sale->receipt_no;

            // Reset cart and generate fresh idempotency key
            $this->cart = [];
            $this->payments = [];
            $this->warningMessage = null;
            $this->resumeWarnings = [];
            $this->selectedCustomerId = null;
            $this->idempotencyKey = (string) Str::uuid();

            // Notify the browse panel to re-render so quantities are current.
            $this->dispatch('browse-refresh');

            session()->flash('status', __('sales.sale_completed_successfully', ['receipt' => $sale->receipt_no]));

            $this->redirect(route('sales.receipt', ['sale' => $sale->id]), navigate: true);
        } catch (PaymentMismatchException|InsufficientStockException|FefoOverrideException|SellabilityException|\InvalidArgumentException|\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        $user         = Auth::user();
        $currentShift = CurrentShift::forUser($user);
        $heldSales    = $user ? HeldSale::held()->forUser($user)->latest('id')->get() : collect();

        // Browse panel: nearest-expiry-first paginated product list (UC-13).
        // NearestExpiryQuery never exposes unit_cost or any cost field.
        $browseQuery = new NearestExpiryQuery(
            $this->browseSearch !== '' ? ['search' => $this->browseSearch] : []
        );
        $browseProducts = $this->browseOpen
            ? $browseQuery->builder($user)
                ->with(['latestPrice', 'units'])
                ->paginate($this->browsePerPage, ['*'], 'browsePage')
            : collect();

        // Expiry-window thresholds from Settings for colour coding.
        $rawWindows    = setting('inventory.near_expiry_alert_days', [90, 60, 30]);
        $expiryWindows = is_array($rawWindows) ? $rawWindows : [90, 60, 30];
        rsort($expiryWindows);

        return view('livewire.pos.sale', [
            'currentShift'   => $currentShift,
            'activeMethods'  => $this->getActivePaymentMethods(),
            'cartTotalSantim'=> $this->getCartTotalSantim(),
            'totalPaidSantim'=> $this->getTotalPaidSantim(),
            'balanceSantim'  => $this->getBalanceSantim(),
            'heldSales'      => $heldSales,
            'heldSalesCount' => $heldSales->count(),
            'selectedCustomer'=> $this->selectedCustomer,
            'browseProducts' => $browseProducts,
            'expiryWindows'  => $expiryWindows,
        ]);
    }
}
