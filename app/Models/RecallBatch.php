<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $recall_id
 * @property int $batch_id
 * @property int $quantity_found
 * @property int $quantity_quarantined
 * @property string|null $action
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RecallBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'recall_id',
        'batch_id',
        'quantity_found',
        'quantity_quarantined',
        'action',
    ];

    protected function casts(): array
    {
        return [
            'quantity_found' => 'integer',
            'quantity_quarantined' => 'integer',
        ];
    }

    public function recall(): BelongsTo
    {
        return $this->belongsTo(Recall::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
