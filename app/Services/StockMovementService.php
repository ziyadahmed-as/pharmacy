<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Backward-compatibility shim.
 *
 * All existing callers that used StockMovementService::post() now delegate
 * to StockService::applyMovement(), which is the single source of truth for
 * every stock quantity change (BR-04, BR-08).
 *
 * New code should call StockService::applyMovement() directly.
 *
 * @deprecated Use StockService::applyMovement() directly.
 */
class StockMovementService
{
    /**
     * @deprecated Use StockService::applyMovement()
     */
    public static function post(
        Batch $batch,
        int $signedQuantity,
        string $type,
        Model $source,
        User $user,
        string $status = 'sellable',
        int|string|null $locationId = null,
        ?string $description = null,
        ?User $secondPerson = null,
        ?string $patientName = null,
        ?string $prescriberName = null
    ): StockMovement {
        return StockService::applyMovement(
            batch: $batch,
            signedQuantity: $signedQuantity,
            type: $type,
            source: $source,
            user: $user,
            stockStatus: $status,
            locationId: $locationId,
            description: $description,
            secondPerson: $secondPerson,
            patientName: $patientName,
            prescriberName: $prescriberName,
        );
    }
}
