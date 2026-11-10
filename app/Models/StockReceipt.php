<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'supplier_name',
        'delivery_note_no',
        'receipt_date',
        'status',
        'idempotency_key',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockReceiptLine::class);
    }
}
