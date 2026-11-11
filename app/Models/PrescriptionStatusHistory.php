<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable status transition log for a prescription.
 *
 * @property int $id
 * @property int $prescription_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int $user_id
 * @property string|null $role_used
 * @property string|null $notes
 * @property CarbonInterface $created_at
 * @property-read Prescription $prescription
 * @property-read User $user
 *
 * @uc UC-19
 * @fr FR-4.8
 */
class PrescriptionStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'prescription_id',
        'from_status',
        'to_status',
        'user_id',
        'role_used',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

