<?php

namespace App\Livewire\Inventory;

use App\Actions\Inventory\SubmitStockReceipt;
use App\Models\Product;
use App\Models\StorageLocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Stock Receipt entry form (UC-04).
 *
 * Separated from medicine registration — handles all batch, supplier,
 * purchase cost, and quantity data for one or many lines per receipt.
 *
 * Enhancement (Task 4):
 *  - Accepts ?product_id= query-string to pre-select a product on the first line
 *  - Computes total_cost per line from quantity × unit_cost
 *  - Supports a per-line note field (stored in the line's flag/notes area)
 *  - Notes field on the receipt header
 */
class Receive extends Component
{
    // ── Receipt header ────────────────────────────────────────────────────────

    public string $supplier_name    = '';
    public string $delivery_note_no = '';
    public string $purchase_invoice_no = '';   // NEW: purchase invoice separate from delivery note
    public string $receipt_date     = '';
    public string $notes            = '';
    public string $idempotency_key  = '';

    // ── Override ──────────────────────────────────────────────────────────────

    public bool   $overrideShelfLife = false;
    public string $overrideReason    = '';
    public bool   $showOverrideModal = false;

    // ── Lines ─────────────────────────────────────────────────────────────────

    /**
     * Each line:
     *   product_id, location_id, batch_no, manufacturing_date, expiry_date,
     *   quantity, unit_cost (decimal ETB string), flag, line_notes
     *
     * @var array<int, array<string, mixed>>
     */
    public array $lines = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(?int $product_id = null): void
    {
        $this->receipt_date    = now()->format('Y-m-d');
        $this->idempotency_key = (string) Str::uuid();

        $this->addLine($product_id);
    }

    // ── Line management ───────────────────────────────────────────────────────

    public function addLine(?int $product_id = null): void
    {
        $this->lines[] = [
            'product_id'         => $product_id ? (string) $product_id : '',
            'location_id'        => '',
            'batch_no'           => '',
            'manufacturing_date' => '',
            'expiry_date'        => '',
            'quantity'           => 1,
            'unit_cost'          => '',   // decimal ETB — converted to santim on submit
            'flag'               => 'ok',
            'line_notes'         => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    // ── Watchers ──────────────────────────────────────────────────────────────

    /**
     * Auto-fill batch_no from expiry date (YYYY-MM) when the user sets an
     * expiry date and has not already typed a batch number.
     * Also clears computed total if quantity or cost change.
     */
    public function updatedLines(mixed $value, string $key): void
    {
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
                    if (empty($this->lines[$index]['batch_no'])) {
                        $this->lines[$index]['batch_no'] = $batchName;
                    }
                } catch (\Throwable) {
                    // Invalid date — leave as-is
                }
            }
        }
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    /**
     * Total cost per line = quantity × unit_cost (in ETB, displayed to 2 dp).
     * Returns null when either value is missing.
     *
     * @return array<int, float|null>
     */
    public function getLineTotals(): array
    {
        $totals = [];
        foreach ($this->lines as $line) {
            $qty  = (int) ($line['quantity'] ?? 0);
            $cost = trim($line['unit_cost'] ?? '');
            if ($qty > 0 && $cost !== '' && (float) $cost > 0) {
                $totals[] = round($qty * (float) $cost, 2);
            } else {
                $totals[] = null;
            }
        }
        return $totals;
    }

    /**
     * Grand total purchase cost across all lines (ETB).
     */
    public function getGrandTotal(): ?float
    {
        $totals = $this->getLineTotals();
        $sum    = 0.0;
        $any    = false;
        foreach ($totals as $t) {
            if ($t !== null) {
                $sum += $t;
                $any = true;
            }
        }
        return $any ? round($sum, 2) : null;
    }

    // ── Submit ────────────────────────────────────────────────────────────────

    public function submit(SubmitStockReceipt $action): mixed
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('stock.receive')) {
            abort(403);
        }

        $this->resetValidation();

        // Build lines for the action — convert unit_cost from decimal ETB to santim
        $actionLines = [];
        foreach ($this->lines as $line) {
            $unitCostSantim = null;
            if (isset($line['unit_cost']) && trim((string) $line['unit_cost']) !== '') {
                $unitCostSantim = (int) round((float) $line['unit_cost'] * 100);
            }

            $actionLines[] = array_merge($line, [
                'unit_cost'  => $unitCostSantim,
                'product_id' => $line['product_id'] !== '' ? (int) $line['product_id'] : null,
                'location_id' => isset($line['location_id']) && $line['location_id'] !== ''
                    ? (int) $line['location_id']
                    : null,
            ]);
        }

        // Merge purchase invoice into the notes if provided
        $headerNotes = $this->notes;
        if (! empty(trim($this->purchase_invoice_no))) {
            $headerNotes = trim("Invoice: {$this->purchase_invoice_no}\n{$headerNotes}");
        }

        try {
            $receipt = $action->execute(
                [
                    'supplier_name'    => $this->supplier_name,
                    'delivery_note_no' => $this->delivery_note_no,
                    'receipt_date'     => $this->receipt_date,
                    'idempotency_key'  => $this->idempotency_key,
                    'notes'            => $headerNotes,
                    'lines'            => $actionLines,
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

        return null;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        $user       = Auth::user();
        $canViewCost = $user?->hasPermission('cost.view') ?? false;

        return view('livewire.inventory.receive', [
            'products'    => Product::where('is_active', true)
                ->orderBy('generic_name')
                ->orderBy('brand')
                ->get(),
            'locations'   => \Illuminate\Support\Facades\Schema::hasTable('storage_locations')
                ? StorageLocation::where('is_active', true)->orderBy('name')->get()
                : collect(),
            'canViewCost' => $canViewCost,
            'lineTotals'  => $this->getLineTotals(),
            'grandTotal'  => $this->getGrandTotal(),
        ])->layout('layouts.app', ['title' => __('catalogue.receive_stock')]);
    }
}
