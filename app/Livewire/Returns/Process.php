<?php

namespace App\Livewire\Returns;

use App\Actions\Sales\ProcessReturn;
use App\Models\Sale;
use App\Models\SaleReturnLine;
use App\Services\CurrentShift;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Customer Returns & Refunds')]
class Process extends Component
{
    public string $receiptNo = '';

    public ?int $saleId = null;

    /** @var array<int, array{selected: bool, quantity: int, assessment: string, reason: string}> */
    public array $returnLines = [];

    public string $generalReason = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(?string $receipt = null): void
    {
        abort_unless(Auth::user()?->hasPermission('shifts.open'), 403);

        if ($receipt) {
            $this->receiptNo = $receipt;
            $this->searchSale();
        }
    }

    public function searchSale(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->saleId = null;
        $this->returnLines = [];

        if (empty(trim($this->receiptNo))) {
            $this->errorMessage = __('sales.receipt_number_required');

            return;
        }

        $sale = Sale::with(['lines.product', 'lines.batch', 'payments'])
            ->where('receipt_no', trim($this->receiptNo))
            ->first();

        if (! $sale) {
            $this->errorMessage = __('sales.sale_not_found');

            return;
        }

        if ($sale->isVoided()) {
            $this->errorMessage = __('sales.cannot_refund_voided_sale');

            return;
        }

        $this->saleId = $sale->id;

        foreach ($sale->lines as $line) {
            $rem = $line->remainingReturnableQuantity();
            $this->returnLines[$line->id] = [
                'selected' => false,
                'quantity' => $rem > 0 ? 1 : 0,
                'assessment' => SaleReturnLine::ASSESSMENT_RESALEABLE,
                'reason' => '',
            ];
        }
    }

    public function submitReturn(ProcessReturn $action): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $selectedItems = [];

        foreach ($this->returnLines as $lineId => $item) {
            if (! empty($item['selected'])) {
                $qty = (int) ($item['quantity'] ?? 0);
                if ($qty > 0) {
                    $selectedItems[] = [
                        'sale_line_id' => (int) $lineId,
                        'quantity' => $qty,
                        'assessment' => $item['assessment'] ?? SaleReturnLine::ASSESSMENT_RESALEABLE,
                        'reason' => $item['reason'] ?: $this->generalReason,
                    ];
                }
            }
        }

        if (empty($selectedItems)) {
            $this->errorMessage = __('sales.select_at_least_one_item_to_return');

            return;
        }

        try {
            $result = $action->execute(
                user: Auth::user(),
                receiptNo: $this->receiptNo,
                items: $selectedItems,
                generalReason: $this->generalReason
            );

            if ($result['requires_approval']) {
                $this->successMessage = __('sales.refund_pending_approval_notice', [
                    'refund' => $result['sale_return']->refund_no,
                ]);
                $this->saleId = null;
                $this->receiptNo = '';
            } else {
                session()->flash('status', __('sales.refund_completed_successfully', [
                    'refund' => $result['sale_return']->refund_no,
                ]));
                $this->redirect(route('returns.receipt', $result['sale_return']), navigate: true);
            }
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        $sale = $this->saleId ? Sale::with(['lines.product', 'lines.batch', 'payments'])->find($this->saleId) : null;

        // Compute preview of refund total
        $previewRefundSantim = 0;
        if ($sale) {
            $saleLines = $sale->lines->keyBy('id');
            foreach ($this->returnLines as $lineId => $item) {
                if (! empty($item['selected']) && isset($saleLines[$lineId])) {
                    $qty = (int) ($item['quantity'] ?? 0);
                    $previewRefundSantim += $saleLines[$lineId]->unit_price * $qty;
                }
            }
        }

        $limitSetting = setting('sales.refund_limit');
        $willRequireApproval = ($limitSetting === null || $limitSetting === '' || $limitSetting === 'Not set')
            ? true
            : ($previewRefundSantim > (int) $limitSetting);

        return view('livewire.returns.process', [
            'sale' => $sale,
            'previewRefundEtb' => number_format($previewRefundSantim / 100, 2),
            'willRequireApproval' => $willRequireApproval,
            'currentShift' => CurrentShift::forUser(Auth::user()),
        ]);
    }
}
