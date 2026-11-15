<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $recall_no
 * @property int $product_id
 * @property string|null $source_reference
 * @property string $reason
 * @property Carbon $recall_date
 * @property string $severity
 * @property string $status
 * @property int $created_by
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Recall extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const SEVERITY_CLASS_I = 'class_i';

    public const SEVERITY_CLASS_II = 'class_ii';

    public const SEVERITY_CLASS_III = 'class_iii';

    protected $fillable = [
        'recall_no',
        'product_id',
        'source_reference',
        'reason',
        'recall_date',
        'severity',
        'status',
        'created_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'recall_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(RecallBatch::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
