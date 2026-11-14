<?php

namespace App\Actions\Inventory;

use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff enter their physical count for a stocktake line (UC-07, STEP 3).
 *
 * @uc UC-07
 *
 * @fr FR-2.9
 */
class RecordStocktakeCount
{
    /**
     * @param  array{
     *     line_id: int,
     *     counted_qty: int,
     *     second_person_id?: int|null
     * }  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $user, array $data): StocktakeLine
    {
        if ($user->isSystemAdmin() || ! $user->hasPermission('stock.stocktake')) {
            throw new AuthorizationException(__('stocktake.unauthorized'));
        }

        /** @var StocktakeLine $line */
        $line = StocktakeLine::with(['stocktake', 'batch.product'])->findOrFail($data['line_id']);

        if (! in_array($line->stocktake->status, [Stocktake::STATUS_COUNTING], true)) {
            throw ValidationException::withMessages([
                'line_id' => __('stocktake.stocktake_not_in_counting_phase'),
            ]);
        }

        $countedQty = (int) $data['counted_qty'];
        if ($countedQty < 0) {
            throw ValidationException::withMessages([
                'counted_qty' => __('stocktake.counted_qty_non_negative'),
            ]);
        }

        $variance = $countedQty - $line->system_qty;

        // A3: Controlled drug counts need a second named person (STEP 3)
        $isControlled = $line->batch->product->classification === 'controlled';
        $handlesControlled = (bool) setting('pharmacy.handles_controlled_drugs', true);
        $secondPersonId = $data['second_person_id'] ?? null;

        if ($isControlled && $handlesControlled) {
            if (! $secondPersonId) {
                throw ValidationException::withMessages([
                    'second_person_id' => __('controlled_drugs.second_person_required'),
                ]);
            }
            if ((int) $secondPersonId === $user->id) {
                throw ValidationException::withMessages([
                    'second_person_id' => __('controlled_drugs.second_person_cannot_be_same'),
                ]);
            }
            $secondPerson = User::find($secondPersonId);
            if (! $secondPerson || $secondPerson->isSystemAdmin()) {
                throw ValidationException::withMessages([
                    'second_person_id' => __('controlled_drugs.second_person_invalid'),
                ]);
            }
        }

        return DB::transaction(function () use ($line, $user, $countedQty, $variance, $secondPersonId) {
            $line->forceFill([
                'counted_qty' => $countedQty,
                'variance' => $variance,
                'counted_by' => $user->id,
                'second_person_id' => $secondPersonId,
                'counted_at' => now(),
            ])->save();

            return $line;
        });
    }

    /**
     * Record a recount for a specific line (A1: variance above threshold forces recount).
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function recordRecount(User $user, StocktakeLine $line, int $recountQty): StocktakeLine
    {
        if ($user->isSystemAdmin() || ! $user->hasPermission('stock.stocktake')) {
            throw new AuthorizationException(__('stocktake.unauthorized'));
        }

        if ($line->stocktake->status !== Stocktake::STATUS_REVIEW) {
            throw ValidationException::withMessages([
                'line_id' => __('stocktake.recount_only_in_review'),
            ]);
        }

        if ($recountQty < 0) {
            throw ValidationException::withMessages([
                'recount_qty' => __('stocktake.counted_qty_non_negative'),
            ]);
        }

        $line->forceFill(['recount_qty' => $recountQty])->save();

        return $line;
    }
}
