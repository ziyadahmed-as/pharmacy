<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_path',
        'file_name',
        'status',
        'mapping',
        'totals',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'totals' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ProductImportRow::class);
    }
}

