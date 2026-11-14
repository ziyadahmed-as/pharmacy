<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staff salary advance request and recovery record.
 *
 * @property int $id
 * @property int $staff_id
 * @property int $amount
 * @property CarbonInterface $request_date
 * @property string|null $reason
 * @property string $status
 * @property string|null $repayment_month
 * @property int|null $approved_by
 * @property int|null $payroll_run_id
 *
 * @uc UC-42 @fr FR-10.7
 */
class SalaryAdvance extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RECOVERED = 'recovered';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}

