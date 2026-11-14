<?php

namespace App\Actions\Inventory;

use App\Models\AdjustmentRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

/**
 * Action to record that a physical recount was conducted for an adjustment request (BR-07).
 *
 * @uc UC-06
 *
 * @br BR-07
 */
class RecordRecount
{
    /**
     * @throws AuthorizationException
     * @throws RuntimeException
     */
    public function execute(User $user, AdjustmentRequest $adjustmentRequest, ?string $notes = null): AdjustmentRequest
    {
        if ($user->isSystemAdmin() || ! $user->hasPermission('stock.adjust.request')) {
            throw new AuthorizationException(__('catalogue.unauthorized_stock_adjustment'));
        }

        if (! $adjustmentRequest->isPending()) {
            throw new RuntimeException(__('catalogue.cannot_recount_non_pending_adjustment'));
        }

        $adjustmentRequest->update([
            'recount_done' => true,
            'recounted_by' => $user->id,
            'recounted_at' => now(),
            'recount_notes' => $notes,
        ]);

        AuditLogger::record(
            action: 'stock.adjustment.recounted',
            subject: $adjustmentRequest,
            old: ['recount_done' => false],
            new: ['recount_done' => true],
            metadata: [
                'recount_notes' => $notes,
                'batch_id' => $adjustmentRequest->batch_id,
            ],
            user: $user,
            description: "Physical recount recorded for adjustment on batch {$adjustmentRequest->batch->batch_no}"
        );

        return $adjustmentRequest->fresh();
    }
}

