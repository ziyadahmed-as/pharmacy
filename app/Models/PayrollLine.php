<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Individual staff line inside a monthly payroll run.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $staff_id
 * @property int $base_salary
 * @property int $allowances
 * @property float $overtime_hours
 * @property int $overtime_amount
 * @property int $gross
 * @property int $deductions
 * @property int $advances
 * @property int $net
 * @property string $payment_method
 * @property string|null $notes
 *
 * @uc UC-42 @fr FR-10.7
 */
class PayrollLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
