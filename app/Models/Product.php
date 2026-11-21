<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Product master record (DDD 5.3).
 *
 * --- Original columns (unchanged) ---
 * @property int         $id
 * @property string|null $product_code
 * @property string      $brand
 * @property string      $generic_name
 * @property string|null $strength
 * @property string|null $dosage_form
 * @property string|null $pack_size
 * @property string      $unit
 * @property string|null $manufacturer
 * @property int|null    $category_id
 * @property string|null $barcode
 * @property string|null $storage_condition
 * @property bool        $is_cold_chain
 * @property string      $classification
 * @property bool        $loose_allowed
 * @property int         $reorder_level
 * @property int         $max_level
 * @property int         $min_level
 * @property int|null    $min_shelf_life_days
 * @property string|null $preferred_supplier_name
 * @property bool        $is_active
 * @property string|null $image_path          Path on the 'private' disk; null until an image is uploaded.
 * @property string|null $thumbnail_path
 * @property int|null    $selling_price       Current selling price in minor units (santim). Null = not priced.
 *
 * --- New columns (migration 2026_11_21_000001) ---
 * @property string      $product_type        enum: medicine|medical_device|cosmetic|supplement|other
 * @property string|null $product_description
 * @property int|null    $manufacturer_id     FK → manufacturers
 * @property string|null $country_of_origin
 * @property int|null    $dosage_form_id      FK → dosage_forms
 * @property string|null $strength_unit       e.g. mg, mcg, IU
 * @property int|null    $route_of_administration_id  FK → routes_of_administration
 * @property int|null    $therapeutic_class_id        FK → therapeutic_classes
 * @property string|null $atc_code            WHO ATC code
 * @property string|null $registration_number
 * @property bool        $prescription_required
 * @property string|null $special_storage_instructions
 * @property bool        $inventory_tracking_enabled
 * @property bool        $batch_tracking_enabled
 * @property bool        $expiry_tracking_enabled
 *
 * --- Relationships ---
 * @property-read Category|null                                    $category
 * @property-read Manufacturer|null                                $manufacturerEntity
 * @property-read DosageForm|null                                  $dosageForm
 * @property-read RouteOfAdministration|null                       $routeOfAdministration
 * @property-read TherapeuticClass|null                            $therapeuticClass
 * @property-read DrugInformation|null                             $drugInformation
 * @property-read Collection<int, ProductActiveIngredient>         $activeIngredients
 * @property-read Collection<int, ProductUnit>                     $units
 * @property-read Collection<int, ProductPrice>                    $prices
 * @property-read Collection<int, Batch>                           $batches
 * @property-read Collection<int, BatchStock>                      $batchStocks
 * @property-read Collection<int, Product>                         $substitutes
 * @property-read ProductPrice|null                                $latestPrice
 *
 * @uc UC-01, UC-03
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        // --- Original columns ---
        'product_code',
        'brand',
        'generic_name',
        'strength',
        'dosage_form',
        'pack_size',
        'unit',
        'manufacturer',
        'category_id',
        'barcode',
        'storage_condition',
        'is_cold_chain',
        'classification',
        'loose_allowed',
        'reorder_level',
        'max_level',
        'min_level',
        'min_shelf_life_days',
        'preferred_supplier_name',
        'is_active',
        'image_path',
        'selling_price',
        'thumbnail_path',
        // --- New columns (migration 2026_11_21_000001) ---
        'product_type',
        'product_description',
        'manufacturer_id',
        'country_of_origin',
        'dosage_form_id',
        'strength_unit',
        'route_of_administration_id',
        'therapeutic_class_id',
        'atc_code',
        'registration_number',
        'prescription_required',
        'special_storage_instructions',
        'inventory_tracking_enabled',
        'batch_tracking_enabled',
        'expiry_tracking_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_cold_chain'               => 'boolean',
            'loose_allowed'               => 'boolean',
            'is_active'                   => 'boolean',
            'reorder_level'               => 'integer',
            'max_level'                   => 'integer',
            'min_level'                   => 'integer',
            'min_shelf_life_days'         => 'integer',
            'selling_price'               => 'integer',   // stored in minor units (santim)
            // New casts
            'prescription_required'       => 'boolean',
            'inventory_tracking_enabled'  => 'boolean',
            'batch_tracking_enabled'      => 'boolean',
            'expiry_tracking_enabled'     => 'boolean',
            'manufacturer_id'             => 'integer',
            'dosage_form_id'              => 'integer',
            'route_of_administration_id'  => 'integer',
            'therapeutic_class_id'        => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Stock helpers
    // -------------------------------------------------------------------------

    /**
     * Total available quantity = sum of sellable, non-expired batch quantities.
     *
     * Computed from child rows every time it is called; never stored separately
     * so it cannot drift out of sync with the ledger (FR-1.1).
     *
     * @fr FR-1.1
     */
    /** Cached per-request flag: does batches.status column exist? */
    private static ?bool $batchStatusColExists = null;

    public function totalAvailableQuantity(): int
    {
        // Guard: quantity_on_hand added in migration 2026_11_17_000005.
        if (self::$batchStatusColExists === null) {
            self::$batchStatusColExists = \Illuminate\Support\Facades\Schema::hasColumn('batches', 'quantity_on_hand');
        }

        if (! self::$batchStatusColExists) {
            // Pre-migration fallback: sum batch_stocks (should not happen in production)
            return (int) $this->batches()
                ->where('expiry_date', '>', now())
                ->withSum(['stocks as sq' => fn ($q) => $q->where('status', 'sellable')], 'quantity')
                ->get()->sum('sq');
        }

        // SSOT: sum quantity_on_hand across SELLABLE, non-expired batches only.
        return (int) $this->batches()
            ->where('expiry_date', '>', now())
            ->whereIn('batches.status', ['SELLABLE'])
            ->sum('quantity_on_hand');
    }

    // -------------------------------------------------------------------------
    // Cost / price visibility (BR-06)
    // -------------------------------------------------------------------------

    /**
     * Remove buying-price fields from the serialised array when the current
     * acting user holds only the PharmacyStaff role.
     *
     * Called automatically by toArray() / toJson() (Eloquent hook).
     * Server-side enforcement: even if a view mistakenly outputs $product,
     * unit_cost and selling_price will not be present for PP users.
     *
     * NOTE: selling_price here is the *denormalised* catalogue price set by OW/PM.
     * It differs from the per-sale line price which PS must see at the till.
     * The POS resolves price through PriceResolver, not this column.
     */
    protected function serializeForPs(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // If the user holds any role with cost.view the full model is returned.
        if ($user->hasPermission('cost.view')) {
            return false;
        }

        return true;  // PS: strip cost fields
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturerEntity(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    public function dosageForm(): BelongsTo
    {
        return $this->belongsTo(DosageForm::class, 'dosage_form_id');
    }

    public function routeOfAdministration(): BelongsTo
    {
        return $this->belongsTo(RouteOfAdministration::class, 'route_of_administration_id');
    }

    public function therapeuticClass(): BelongsTo
    {
        return $this->belongsTo(TherapeuticClass::class, 'therapeutic_class_id');
    }

    public function drugInformation(): HasOne
    {
        return $this->hasOne(DrugInformation::class);
    }

    public function activeIngredients(): HasMany
    {
        return $this->hasMany(ProductActiveIngredient::class)->orderBy('sort_order');
    }

    public function substitutes(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_substitutes',
            'product_id',
            'substitute_product_id'
        )->withPivot('note')->withTimestamps();
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function batchStocks(): HasManyThrough
    {
        return $this->hasManyThrough(BatchStock::class, Batch::class);
    }

    public function latestPrice(): HasOne
    {
        return $this->hasOne(ProductPrice::class)->latestOfMany('effective_from');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** Active products only. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Filter by product type. */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('product_type', $type);
    }

    /** Medicines only (product_type = 'medicine'). */
    public function scopeMedicines(Builder $query): Builder
    {
        return $query->where('product_type', 'medicine');
    }

    /** Products that require a prescription. */
    public function scopePrescriptionRequired(Builder $query): Builder
    {
        return $query->where('prescription_required', true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Human-readable strength string built from active ingredients.
     * Falls back to the legacy `strength` text column when no structured
     * ingredient rows exist.
     *
     * Examples:
     *   single:      "500 mg"
     *   combination: "875 mg + 125 mg"
     */
    public function strengthLabel(): string
    {
        $ingredients = $this->activeIngredients;

        if ($ingredients->isEmpty()) {
            return $this->strength ?? '';
        }

        return $ingredients
            ->map(fn ($i) => $i->strength_string)
            ->implode(' + ');
    }

    // -------------------------------------------------------------------------
    // Image accessor
    // -------------------------------------------------------------------------

    /**
     * Public thumbnail URL — returns the placeholder when no image is set.
     */
    public function thumbnailUrl(): string
    {
        return \App\Services\ProductImageService::thumbnailUrl($this->thumbnail_path ?? null);
    }

    // -------------------------------------------------------------------------
    // Serialisation — hide buying-price fields from PP (BR-06)
    // -------------------------------------------------------------------------

    /**
     * When converted to array/JSON, strip `selling_price` for users without
     * `cost.view`. The POS reads price through PriceResolver, not this column,
     * so PS can still sell; they just never see the catalogue cost/price column.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->serializeForPs()) {
            unset($array['selling_price']);
        }

        return $array;
    }
}
