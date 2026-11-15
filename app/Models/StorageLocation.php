<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class StorageLocation extends Model
{
    use HasFactory;

    public const TYPE_SHELF = 'shelf';

    public const TYPE_FRIDGE = 'fridge';

    public const TYPE_CONTROLLED_CABINET = 'controlled-cabinet';

    public const TYPE_QUARANTINE = 'quarantine';

    public const TYPE_DISPENSARY = 'dispensary';

    public const TYPES = [
        self::TYPE_SHELF,
        self::TYPE_FRIDGE,
        self::TYPE_CONTROLLED_CABINET,
        self::TYPE_QUARANTINE,
        self::TYPE_DISPENSARY,
    ];

    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function booted(): void
    {
        static::deleting(function () {
            throw new RuntimeException('Storage locations cannot be deleted. Deactivate instead.');
        });
    }

    public function batchStocks(): HasMany
    {
        return $this->hasMany(BatchStock::class, 'location_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'location_id');
    }

    public static function getDefaultDispensary(): ?self
    {
        return static::where('code', 'DISP-MAIN')->first()
            ?? static::where('type', self::TYPE_DISPENSARY)->where('is_active', true)->first();
    }

    public static function getDefaultControlledCabinet(): ?self
    {
        return static::where('code', 'SAFE-MAIN')->first()
            ?? static::where('type', self::TYPE_CONTROLLED_CABINET)->where('is_active', true)->first();
    }

    public static function getDefaultQuarantine(): ?self
    {
        return static::where('code', 'QUAR-MAIN')->first()
            ?? static::where('type', self::TYPE_QUARANTINE)->where('is_active', true)->first();
    }
}
