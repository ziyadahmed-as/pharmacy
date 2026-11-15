<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monthly sales target for the pharmacy as a whole (staff_id is null) or an individual staff member.
 *
 * @property int $id
 * @property int|null $staff_id
 * @property string $period // YYYY-MM
 * @property int $amount // Santim
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Staff|null $staff
 * @property-read User|null $createdBy
 */
class SalesTarget extends Model
{
    use Auditable, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isWholePharmacy(): bool
    {
        return $this->staff_id === null;
    }

    public function formattedAmount(): string
    {
        return number_format($this->amount / 100, 2).' ETB';
    }

    /**
     * Calculate achieved sales in santim for this target's period.
     */
    public function achievedSales(): int
    {
        [$year, $month] = explode('-', $this->period);
        $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end]);

        if ($this->staff_id !== null) {
            $staff = $this->staff;
            if ($staff && $staff->user_id) {
                $query->where('user_id', $staff->user_id);
            } else {
                return 0;
            }
        }

        return (int) $query->sum('total');
    }

    /**
     * Returns the achievement percentage (e.g. 75.5).
     */
    public function progressPercentage(): float
    {
        if ($this->amount <= 0) {
            return 0.0;
        }

        $achieved = $this->achievedSales();

        return round(($achieved / $this->amount) * 100, 1);
    }

    public function auditModule(): string
    {
        return 'sales';
    }
}
