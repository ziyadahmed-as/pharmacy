<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_return_id
 * @property int $sale_line_id
 * @property int $batch_id
 * @property int $quantity
 * @property int $unit_price
 * @property int $subtotal
 * @property string $assessment
 * @property string|null $reason
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read SaleReturn $saleReturn
 * @property-read SaleLine $saleLine
 * @property-read Batch $batch
 */
class SaleReturnLine extends Model
{
    use HasFactory;

    public const ASSESSMENT_RESALEABLE = 'resaleable';

    public const ASSESSMENT_NOT_RESALEABLE = 'not_resaleable';

    protected $fillable = [
        'sale_return_id',
        'sale_line_id',
        'batch_id',
        'quantity',
        'unit_price',
        'subtotal',
        'assessment',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function isResaleable(): bool
    {
        return $this->assessment === self::ASSESSMENT_RESALEABLE;
    }

    public function unitPriceEtb(): string
    {
        return number_format($this->unit_price / 100, 2);
    }

    public function subtotalEtb(): string
    {
        return number_format($this->subtotal / 100, 2);
    }
}

