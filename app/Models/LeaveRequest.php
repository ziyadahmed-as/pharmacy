<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staff Leave Request (UC-41).
 *
 * @property int $id
 * @property int $staff_id
 * @property int $leave_type_id
 * @property CarbonInterface $start_date
 * @property CarbonInterface $end_date
 * @property int $days
 * @property string|null $reason
 * @property string $status
 * @property bool $is_over_balance
 * @property int|null $approval_request_id
 * @property int|null $approved_by
 * @property CarbonInterface|null $decided_at
 * @property string|null $rejection_reason
 *
 * @uc UC-41 @fr FR-10.6
 */
class LeaveRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'is_over_balance' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }
}
