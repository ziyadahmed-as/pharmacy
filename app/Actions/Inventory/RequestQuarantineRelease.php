<?php

namespace App\Actions\Inventory;

use App\Models\ApprovalRequest;
use App\Models\QuarantineRecord;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Creates an approval request to release stock from quarantine (UC-08 Step 4).
 *
 * @uc UC-08 @fr FR-2.15 @br BR-01, BR-10 @ac AC-07
 */
class RequestQuarantineRelease
{
    public function __construct(private ApprovalService $approvals) {}

    /**
     * @throws ValidationException
     */
    public function execute(QuarantineRecord $record, string $reason, ?User $actor = null): ApprovalRequest
    {
        $user = $actor ?? Auth::user();

        if (! $user || ! $user->canAccessBusinessData()) {
            throw ValidationException::withMessages([
                'user' => __('catalogue.unauthorized_quarantine_release'),
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => __('catalogue.release_reason_required'),
            ]);
        }

        if (! $record->isOpen()) {
            throw ValidationException::withMessages([
                'record' => __('catalogue.quarantine_record_not_open'),
            ]);
        }

        // Recalled batches can never be released (UC-08 Step 4)
        if ($record->isRecalled()) {
            throw ValidationException::withMessages([
                'record' => __('catalogue.recalled_cannot_be_released'),
            ]);
        }

        // Expired batches can never return to sellable (BR-01)
        if ($record->batch->expiry_date && $record->batch->expiry_date->isPast()) {
            throw ValidationException::withMessages([
                'record' => __('catalogue.expired_cannot_be_released'),
            ]);
        }

        $productName = $record->batch->product?->brand ?? 'Product';
        $summary = "Release {$record->quantity} units of {$productName} (Batch {$record->batch->batch_no}) from quarantine";

        return $this->approvals->request(
            type: 'stock.quarantine.release',
            initiator: $user,
            payload: [
                'quarantine_record_id' => $record->id,
                'batch_id' => $record->batch_id,
                'batch_no' => $record->batch->batch_no,
                'product_name' => $productName,
                'quantity' => $record->quantity,
                'release_reason' => trim($reason),
            ],
            summary: $summary,
            subject: $record,
            comment: trim($reason)
        );
    }
}
