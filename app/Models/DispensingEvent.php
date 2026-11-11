<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single dispensing event: one batch, one quantity, one sale, one user.
 *
 * @property int $id
 * @property int $prescription_line_id
 * @property int $batch_id
 * @property int $quantity
 * @property int $user_id
 * @property int|null $sale_id
 * @property CarbonInterface $dispensed_at
 * @property-read PrescriptionLine $prescriptionLine
 * @property-read Batch $batch
 * @property-read User $user
 * @property-read Sale|null $sale
 *
 * @uc UC-21
 *
 * @fr FR-4.1, FR-4.8, FR-4.9, FR-4.10
 */
class DispensingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_line_id',
        'batch_id',
        'quantity',
        'user_id',
        'sale_id',
        'dispensed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'dispensed_at' => 'datetime',
        ];
    }

    public function prescriptionLine(): BelongsTo
    {
        return $this->belongsTo(PrescriptionLine::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
