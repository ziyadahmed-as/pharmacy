<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Clinical drug information about a medicine (DDD 5.3).
 *
 * @property int $id
 * @property int $product_id
 * @property string|null $indications
 * @property string|null $dosage_guidance
 * @property string|null $side_effects
 * @property string|null $contraindications
 * @property string|null $interactions
 * @property int|null $updated_by
 * @property-read Product $product
 * @property-read User|null $updater
 *
 * @uc UC-01
 */
class DrugInformation extends Model
{
    use HasFactory;

    protected $table = 'drug_information';

    protected $guarded = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getFor(Product|int $product): ?self
    {
        $id = $product instanceof Product ? $product->id : $product;

        return self::where('product_id', $id)->first();
    }

    /**
     * @param  array{
     *     indications?: string|null,
     *     dosage_guidance?: string|null,
     *     side_effects?: string|null,
     *     contraindications?: string|null,
     *     interactions?: string|null
     * }  $data
     */
    public static function saveFor(Product|int $product, array $data, ?User $user = null): self
    {
        $id = $product instanceof Product ? $product->id : $product;

        $record = self::firstOrNew(['product_id' => $id]);
        $record->fill([
            'indications' => $data['indications'] ?? $record->indications,
            'dosage_guidance' => $data['dosage_guidance'] ?? $record->dosage_guidance,
            'side_effects' => $data['side_effects'] ?? $record->side_effects,
            'contraindications' => $data['contraindications'] ?? $record->contraindications,
            'interactions' => $data['interactions'] ?? $record->interactions,
            'updated_by' => $user?->id ?? auth()->id(),
        ]);
        $record->save();

        return $record;
    }
}
