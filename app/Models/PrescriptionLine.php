<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single drug line on a registered prescription.
 *
 * @property int $id
 * @property int $prescription_id
 * @property int $product_id
 * @property string $dose
 * @property int $quantity_prescribed
 * @property int $quantity_dispensed
 * @property-read Prescription $prescription
 * @property-read Product $product
 *
 * @uc UC-19
 *
 * @fr FR-4.2
 */
class PrescriptionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'product_id',
        'dose',
        'quantity_prescribed',
        'quantity_dispensed',
    ];

    protected function casts(): array
    {
        return [
            'quantity_prescribed' => 'integer',
            'quantity_dispensed' => 'integer',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity_prescribed - $this->quantity_dispensed);
    }

    public function isFullyDispensed(): bool
    {
        return $this->quantity_dispensed >= $this->quantity_prescribed;
    }
}
