<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Controlled drug register entry (append-only).
 *
 * @property int $id
 * @property int $product_id
 * @property int $batch_id
 * @property string $entry_type
 * @property int $quantity
 * @property int $running_balance
 * @property string|null $patient_name
 * @property string|null $prescriber_name
 * @property int $user_id
 * @property int|null $second_person_id
 * @property int|null $sale_id
 * @property int|null $stock_movement_id
 * @property string|null $notes
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Product $product
 * @property-read Batch $batch
 * @property-read User $user
 * @property-read User|null $secondPerson
 * @property-read Sale|null $sale
 * @property-read StockMovement|null $stockMovement
 *
 * @uc UC-22
 *
 * @fr FR-4.4, FR-4.3, FR-4.9
 *
 * @br BR-03, BR-11, BR-12
 *
 * @ac AC-13, AC-34
 */
class ControlledDrugRegister extends Model
{
    use HasFactory;

    protected $table = 'controlled_drug_register';

    protected $fillable = [
        'product_id',
        'batch_id',
        'entry_type',
        'quantity',
        'running_balance',
        'patient_name',
        'prescriber_name',
        'user_id',
        'second_person_id',
        'sale_id',
        'stock_movement_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'running_balance' => 'integer',
        ];
    }

    public static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Controlled drug register is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('Controlled drug register is append-only and cannot be deleted.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function secondPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_person_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
