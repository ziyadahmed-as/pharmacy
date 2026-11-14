<?php

namespace App\Actions\Inventory;

use App\Models\Batch;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a new stocktake count sheet (UC-07, STEP 2).
 *
 * @uc UC-07
 *
 * @fr FR-2.9, FR-2.10
 *
 * @br BR-07
 *
 * @ac AC-09
 */
class CreateStocktake
{
    /**
     * @param  array{
     *     type: string,
     *     scope_type?: string|null,
     *     scope_value?: string|null
     * }  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $user, array $data): Stocktake
    {
        // Only PM and Owner may initiate a stocktake
        if ($user->isSystemAdmin() || ! $user->hasPermission('stock.stocktake')) {
            throw new AuthorizationException(__('stocktake.unauthorized'));
        }

        $type = $data['type'] ?? Stocktake::TYPE_FULL;
        if (! in_array($type, [Stocktake::TYPE_FULL, Stocktake::TYPE_PARTIAL], true)) {
            throw ValidationException::withMessages([
                'type' => __('stocktake.invalid_type'),
            ]);
        }

        $scopeType = $data['scope_type'] ?? null;
        $scopeValue = $data['scope_value'] ?? null;

        if ($type === Stocktake::TYPE_PARTIAL && empty($scopeValue)) {
            throw ValidationException::withMessages([
                'scope_value' => __('stocktake.scope_required_for_partial'),
            ]);
        }

        // Build the query for batches in scope
        $batchQuery = Batch::query()
            ->with(['product', 'batchStocks'])
            ->whereHas('batchStocks', fn (Builder $q) => $q->where('status', 'sellable')->where('quantity', '>', 0));

        if ($type === Stocktake::TYPE_PARTIAL && $scopeType === 'category' && $scopeValue) {
            $batchQuery->whereHas('product', fn (Builder $q) => $q->where('category_id', $scopeValue));
        }

        $batches = $batchQuery->orderBy('id')->get();

        if ($batches->isEmpty()) {
            throw ValidationException::withMessages([
                'type' => __('stocktake.no_stock_in_scope'),
            ]);
        }

        // Precondition: block a second active stocktake covering the same batches (STEP 2)
        $batchIds = $batches->pluck('id');
        $conflictingLine = StocktakeLine::query()
            ->whereIn('batch_id', $batchIds)
            ->whereHas('stocktake', fn (Builder $q) => $q->whereIn('status', [
                Stocktake::STATUS_DRAFT,
                Stocktake::STATUS_COUNTING,
                Stocktake::STATUS_REVIEW,
            ]))
            ->first();

        if ($conflictingLine) {
            throw ValidationException::withMessages([
                'type' => __('stocktake.active_stocktake_conflict'),
            ]);
        }

        return DB::transaction(function () use ($user, $type, $scopeType, $scopeValue, $batches) {
            $stocktake = Stocktake::create([
                'type' => $type,
                'scope_type' => $scopeType,
                'scope_value' => $scopeValue,
                'status' => Stocktake::STATUS_COUNTING,
                'created_by' => $user->id,
            ]);

            // Generate count sheet lines with system qty snapshot
            $lines = $batches->map(function (Batch $batch) use ($stocktake) {
                $systemQty = $batch->batchStocks
                    ->where('status', 'sellable')
                    ->sum('quantity');

                return [
                    'stocktake_id' => $stocktake->id,
                    'batch_id' => $batch->id,
                    'system_qty' => (int) $systemQty,
                    'counted_qty' => null,
                    'variance' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            StocktakeLine::insert($lines);

            AuditLogger::record(
                action: 'stock.stocktake.created',
                subject: $stocktake,
                old: [],
                new: ['type' => $type, 'scope_type' => $scopeType, 'line_count' => count($lines)],
                metadata: ['batch_ids' => $batches->pluck('id')->toArray()],
                user: $user,
                description: "Stocktake #{$stocktake->id} ({$type}) created with ".count($lines).' lines',
            );

            return $stocktake;
        });
    }
}
