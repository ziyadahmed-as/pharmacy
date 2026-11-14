<?php

namespace App\Actions\Inventory;

use App\Enums\Role;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockMovementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PM (or Owner when PM started it) approves or rejects a stocktake (UC-07, STEP 4).
 *
 * On approval  : posts one adjustment movement per non-zero variance through
 *                StockMovementService::post() (type 'adjustment') – reuses UC-06 path.
 * On rejection : leaves stock completely unchanged.
 *
 * @uc UC-07
 *
 * @fr FR-2.9, FR-2.10
 *
 * @ac AC-09
 */
class ApproveStocktake
{
    /**
     * Approve a stocktake in 'review' status.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function approve(User $approver, Stocktake $stocktake): Stocktake
    {
        // E1: PM started it → Owner must approve; otherwise PM or Owner can approve.
        $this->guardApprover($approver, $stocktake);

        if ($stocktake->status !== Stocktake::STATUS_REVIEW) {
            throw ValidationException::withMessages([
                'status' => __('stocktake.not_in_review'),
            ]);
        }

        // Load lines with batch and product relationships
        $stocktake->load(['lines.batch.product', 'lines.secondPerson']);

        // A1: Reject lines where variance requires a recount that hasn't been done
        $linesNeedingRecount = $stocktake->lines->filter(function (StocktakeLine $line) {
            return $line->hasVariance()
                && $line->requiresRecount()
                && $line->recount_qty === null;
        });

        if ($linesNeedingRecount->isNotEmpty()) {
            throw ValidationException::withMessages([
                'recount' => __('stocktake.recount_required_before_approval', [
                    'count' => $linesNeedingRecount->count(),
                ]),
            ]);
        }

        return DB::transaction(function () use ($approver, $stocktake) {
            foreach ($stocktake->lines as $line) {
                $effectiveVariance = $line->effectiveVariance();

                if ($effectiveVariance === 0) {
                    continue;
                }

                // Post one adjustment movement per variance line
                StockMovementService::post(
                    batch: $line->batch,
                    signedQuantity: $effectiveVariance,
                    type: 'adjustment',
                    source: $stocktake,
                    user: $approver,
                    status: 'sellable',
                    description: sprintf(
                        'Stocktake #%d adjustment: batch %s variance %+d',
                        $stocktake->id,
                        $line->batch->batch_no,
                        $effectiveVariance
                    ),
                    secondPerson: $line->secondPerson,
                );
            }

            $stocktake->forceFill([
                'status' => Stocktake::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ])->save();

            AuditLogger::record(
                action: 'stock.stocktake.approved',
                subject: $stocktake,
                old: ['status' => Stocktake::STATUS_REVIEW],
                new: ['status' => Stocktake::STATUS_APPROVED],
                metadata: ['variance_lines' => $stocktake->lines->filter->hasVariance()->count()],
                user: $approver,
                description: "Stocktake #{$stocktake->id} approved by {$approver->name}",
            );

            return $stocktake;
        });
    }

    /**
     * Reject a stocktake – leaves stock completely unchanged (A2 / E1).
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function reject(User $approver, Stocktake $stocktake, string $reason): Stocktake
    {
        $this->guardApprover($approver, $stocktake);

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => __('stocktake.rejection_reason_required'),
            ]);
        }

        if ($stocktake->status !== Stocktake::STATUS_REVIEW) {
            throw ValidationException::withMessages([
                'status' => __('stocktake.not_in_review'),
            ]);
        }

        return DB::transaction(function () use ($approver, $stocktake, $reason) {
            $stocktake->forceFill([
                'status' => Stocktake::STATUS_REJECTED,
                'approved_by' => $approver->id,
                'rejection_reason' => $reason,
            ])->save();

            AuditLogger::record(
                action: 'stock.stocktake.rejected',
                subject: $stocktake,
                old: ['status' => Stocktake::STATUS_REVIEW],
                new: ['status' => Stocktake::STATUS_REJECTED],
                metadata: ['rejection_reason' => $reason],
                user: $approver,
                description: "Stocktake #{$stocktake->id} rejected: {$reason}",
            );

            return $stocktake;
        });
    }

    /**
     * Move a stocktake from counting → review (all lines counted).
     *
     * @throws ValidationException
     */
    public function submitForReview(User $user, Stocktake $stocktake): Stocktake
    {
        if ($user->isSystemAdmin() || ! $user->hasPermission('stock.stocktake')) {
            throw new AuthorizationException(__('stocktake.unauthorized'));
        }

        if ($stocktake->status !== Stocktake::STATUS_COUNTING) {
            throw ValidationException::withMessages([
                'status' => __('stocktake.not_in_counting'),
            ]);
        }

        if (! $stocktake->allLinesCounted()) {
            throw ValidationException::withMessages([
                'lines' => __('stocktake.not_all_lines_counted'),
            ]);
        }

        $stocktake->forceFill(['status' => Stocktake::STATUS_REVIEW])->save();

        return $stocktake;
    }

    // ── Guards ───────────────────────────────────────────────────────────────

    private function guardApprover(User $approver, Stocktake $stocktake): void
    {
        if ($approver->isSystemAdmin() || ! $approver->hasPermission('stock.stocktake')) {
            throw new AuthorizationException(__('stocktake.unauthorized'));
        }

        // If the PM created it, only the Owner may approve (like UC-06 A2)
        $creator = $stocktake->createdBy;
        if ($creator && $creator->hasRole(Role::PharmacyManager)) {
            if (! $approver->hasRole(Role::Owner)) {
                throw new AuthorizationException(__('stocktake.owner_must_approve'));
            }
        }

        // The initiator cannot approve their own stocktake
        if ($stocktake->created_by === $approver->id) {
            throw new AuthorizationException(__('stocktake.initiator_cannot_approve'));
        }
    }
}
