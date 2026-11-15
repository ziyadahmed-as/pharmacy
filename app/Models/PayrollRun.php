<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Monthly payroll run container.
 *
 * @property int $id
 * @property string $month (YYYY-MM)
 * @property string $status
 * @property int $total_gross
 * @property int $total_allowances
 * @property int $total_overtime
 * @property int $total_deductions
 * @property int $total_advances
 * @property int $total_net
 * @property int $prepared_by
 * @property int|null $approved_by
 * @property int|null $paid_by
 * @property CarbonInterface|null $paid_at
 * @property int|null $approval_request_id
 * @property int|null $expense_id
 * @property string|null $notes
 *
 * @uc UC-42 @fr FR-10.7
 */
class PayrollRun extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }
}
