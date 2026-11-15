<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Staff document record (contracts, ID cards, qualifications, certifications).
 *
 * @property int $id
 * @property int $staff_id
 * @property string $type
 * @property string $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 * @property string|null $mime_type
 * @property CarbonInterface|null $issued_at
 * @property CarbonInterface|null $expires_at
 * @property int $uploaded_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Staff $staff
 * @property-read User $uploadedBy
 */
class StaffDocument extends Model
{
    use Auditable, HasFactory;

    public const TYPE_CONTRACT = 'contract';

    public const TYPE_ID_CARD = 'id_card';

    public const TYPE_QUALIFICATION = 'qualification';

    public const TYPE_LICENCE = 'licence';

    public const TYPE_TRAINING = 'training';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_CONTRACT,
        self::TYPE_ID_CARD,
        self::TYPE_QUALIFICATION,
        self::TYPE_LICENCE,
        self::TYPE_TRAINING,
        self::TYPE_OTHER,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'file_size' => 'integer',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function isExpired(?CarbonInterface $on = null): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        $day = business_date($on);

        return $this->expires_at->isPast() && ! $this->expires_at->isSameDay($day);
    }

    public function isExpiringSoon(int $days = 30, ?CarbonInterface $on = null): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        $day = business_date($on);

        return $this->expires_at->isFuture() && $this->expires_at->diffInDays($day) <= $days;
    }

    public function formattedSize(): string
    {
        if (! $this->file_size) {
            return '—';
        }

        if ($this->file_size >= 1048576) {
            return number_format($this->file_size / 1048576, 1).' MB';
        }

        return number_format($this->file_size / 1024, 0).' KB';
    }

    public function existsOnDisk(): bool
    {
        return ! empty($this->file_path) && Storage::disk('local')->exists($this->file_path);
    }

    public function auditModule(): string
    {
        return 'staff';
    }
}
