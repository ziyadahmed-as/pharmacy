<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query object for the append-only stock movements ledger (UC-11 Step 1).
 * Read-only; no modification methods exist (E1).
 *
 * @uc UC-11 @fr FR-2.10, 7.2 @ac AC-16
 */
class LedgerQuery
{
    /**
     * @param array{
     *     product_id?: ?int,
     *     batch_id?: ?int,
     *     type?: ?string,
     *     from?: ?string,
     *     to?: ?string,
     *     search?: ?string,
     * } $filters
     */
    public function __construct(private array $filters = []) {}

    public function builder(?User $viewer = null): Builder
    {
        $f = $this->filters;

        $query = StockMovement::query()
            ->with([
                'batch.product',
                'user',
            ])
            ->orderByDesc('id');

        if (! empty($f['product_id'])) {
            $query->whereHas('batch', fn ($bQ) => $bQ->where('product_id', $f['product_id']));
        }

        if (! empty($f['batch_id'])) {
            $query->where('batch_id', $f['batch_id']);
        }

        if (! empty($f['type'])) {
            $query->where('type', $f['type']);
        }

        if (! empty($f['from'])) {
            $query->where('created_at', '>=', Carbon::parse($f['from'], config('pms.timezone'))->startOfDay()->utc());
        }

        if (! empty($f['to'])) {
            $query->where('created_at', '<=', Carbon::parse($f['to'], config('pms.timezone'))->endOfDay()->utc());
        }

        if (! empty($f['search'])) {
            $term = trim($f['search']);
            $query->where(function (Builder $q) use ($term) {
                $q->whereHas('batch', function (Builder $bQ) use ($term) {
                    $bQ->where('batch_no', 'like', "%{$term}%")
                        ->orWhereHas('product', function (Builder $pQ) use ($term) {
                            $pQ->where('brand', 'like', "%{$term}%")
                                ->orWhere('generic_name', 'like', "%{$term}%");
                        });
                })->orWhere('description', 'like', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * Resolve a user-facing label and route URL for the movement source document.
     *
     * @return array{label: string, url: ?string}
     */
    public static function sourceInfo(StockMovement $movement): array
    {
        $type = $movement->source_type;
        $id = $movement->source_id;

        if (str_ends_with($type, 'StockReceipt')) {
            $receipt = $movement->source;
            $ref = $receipt ? $receipt->reference : "#{$id}";

            return [
                'label' => __('catalogue.receipt_ref', ['ref' => $ref]),
                'url' => route('inventory.receipts.show', $id),
            ];
        }

        $basename = class_basename($type);

        return [
            'label' => "{$basename} #{$id}",
            'url' => null,
        ];
    }
}

