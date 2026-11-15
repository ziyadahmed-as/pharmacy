<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Record of an approved sale void.
 *
 * @property int $id
 * @property int $sale_id
 * @property string $reason
 * @property int $requested_by
 * @property int $approved_by
 * @property int|null $approval_request_id
 * @property Carbon $voided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SaleVoid extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'reason',
        'requested_by',
        'approved_by',
        'approval_request_id',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'voided_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
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
