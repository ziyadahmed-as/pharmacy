<?php

namespace App\Actions\Sales;

use App\Exceptions\NoOpenShiftException;
use App\Models\HeldSale;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CurrentShift;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Hold a sale without reserving physical stock.
 *
 * @uc UC-14
 *
 * @fr FR-3.6
 *
 * @br BR-01
 *
 * @ac AC-23
 */
class HoldSale
{
    /**
     * Store the active POS cart as a held sale.
     *
     * @param  User  $user  Cashier holding the cart
     * @param  Shift|null  $shift  Current cash shift
     * @param  array<int, array<string, mixed>>  $cart  Cart items
     * @param  string|null  $reference  Optional customer identifier or note
     *
     * @throws AuthorizationException
     * @throws NoOpenShiftException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        ?Shift $shift,
        array $cart,
        ?string $reference = null
    ): HeldSale {
        if ($user->isSystemAdmin()) {
            throw new AuthorizationException(__('sales.unauthorized_to_sell'));
        }

        $activeShift = $shift ?? CurrentShift::requireForUser($user);
        if (! $activeShift->isOpen()) {
            throw new NoOpenShiftException(__('shifts.no_open_shift_error'));
        }

        if (empty($cart)) {
            throw new InvalidArgumentException(__('sales.empty_cart_error'));
        }

        $totalSantim = (int) collect($cart)->sum('subtotal');

        $heldSale = HeldSale::create([
            'user_id' => $user->id,
            'shift_id' => $activeShift->id,
            'reference' => trim((string) $reference) ?: null,
            'cart' => $cart,
            'total' => $totalSantim,
            'status' => HeldSale::STATUS_HELD,
            'held_at' => now(),
        ]);

        AuditLogger::record(
            action: 'sale.held',
            subject: $heldSale,
            old: [],
            new: [
                'held_sale_id' => $heldSale->id,
                'user_id' => $user->id,
                'shift_id' => $activeShift->id,
                'items_count' => count($cart),
                'total' => $totalSantim,
                'reference' => $heldSale->reference,
            ],
            user: $user,
            description: "Sale held by {$user->name} ({$heldSale->itemsCount()} items, {$heldSale->totalEtb()} ETB)"
        );

        return $heldSale;
    }
}
