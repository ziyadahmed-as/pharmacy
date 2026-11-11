<?php

namespace App\Actions\Sales;

use App\Models\HeldSale;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Discard a held sale that will not be resumed.
 *
 * @uc UC-14
 *
 * @fr FR-3.6
 *
 * @ac AC-23
 */
class DiscardHeldSale
{
    /**
     * Mark a held sale as discarded.
     *
     * @param  User  $user  Cashier or manager discarding
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        HeldSale $heldSale,
        ?string $reason = null
    ): HeldSale {
        if ($user->isSystemAdmin()) {
            throw new AuthorizationException(__('sales.unauthorized_to_sell'));
        }

        // AC-23: Staff can only discard their own held sales
        if ($heldSale->user_id !== $user->id && ! $user->hasAnyRole(['pharmacy_manager', 'owner'])) {
            throw new AuthorizationException(__('sales.unauthorized_held_sale_access'));
        }

        if (! $heldSale->isHeld()) {
            throw new InvalidArgumentException(__('sales.held_sale_already_processed'));
        }

        $heldSale->status = HeldSale::STATUS_DISCARDED;
        $heldSale->discarded_at = now();
        $heldSale->discard_reason = trim((string) $reason) ?: null;
        $heldSale->save();

        AuditLogger::record(
            action: 'sale.held.discarded',
            subject: $heldSale,
            old: ['status' => HeldSale::STATUS_HELD],
            new: [
                'status' => HeldSale::STATUS_DISCARDED,
                'discarded_at' => $heldSale->discarded_at,
                'reason' => $heldSale->discard_reason,
            ],
            user: $user,
            description: "Held sale #{$heldSale->id} discarded by {$user->name}".($heldSale->discard_reason ? " ({$heldSale->discard_reason})" : '')
        );

        return $heldSale;
    }
}
