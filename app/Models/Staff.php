<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Staff record (UC-39). Never deleted: employment ends with status `ended`.
 * id_number is encrypted at rest and masked in the audit trail.
 */
class Staff extends Model
{
    use Auditable, HasFactory;

    public const ACTIVE = 'active';

    public const ON_LEAVE = 'on_leave';

    public const ENDED = 'ended';

    protected $table = 'staff';

    protected $guarded = ['id'];

    protected $hidden = ['id_number'];

    protected function casts(): array
    {
        return [
            'id_number' => 'encrypted',
            'hire_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function licences(): HasMany
    {
        return $this->hasMany(StaffLicence::class)->orderByDesc('expires_at')->orderByDesc('id');
    }

    public function currentLicence(): ?StaffLicence
    {
        return $this->licences()->first();
    }

    /** Valid on a date: expires_at is inclusive (a licence expiring today is still valid today). */
    public function hasValidLicence(?CarbonInterface $on = null): bool
    {
        $day = ($on ?? now())->copy()->timezone(config('pms.timezone'))->toDateString();

        return $this->licences()->whereDate('expires_at', '>=', $day)->exists();
    }

    public function isActive(): bool
    {
        return $this->status !== self::ENDED;
    }

    public function auditModule(): string
    {
        return 'staff';
    }

    public function delete(): ?bool
    {
        throw new \RuntimeException('Staff records are never deleted; end employment instead.');
    }
}
