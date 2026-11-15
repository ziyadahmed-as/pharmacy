<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log entry for each thermal receipt print or reprint.
 *
 * @property int $id
 * @property int $sale_id
 * @property int $user_id
 * @property Carbon $printed_at
 * @property bool $is_reprint
 * @property string|null $reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReceiptPrintLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'user_id',
        'printed_at',
        'is_reprint',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
            'is_reprint' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
