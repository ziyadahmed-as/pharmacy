<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staff salary profile (base salary, allowances, payment details).
 *
 * @property int $id
 * @property int $staff_id
 * @property int $base_salary
 * @property int $allowances
 * @property int $hourly_rate
 * @property string $payment_method
 * @property string|null $bank_name
 * @property string|null $bank_account_number
 * @property string|null $notes
 *
 * @uc UC-42 @fr FR-10.7 @br BR-13
 */
class SalaryProfile extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}

