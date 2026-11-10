<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_import_id',
        'row_number',
        'raw_data',
        'errors',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'errors' => 'array',
            'row_number' => 'integer',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }
}

