<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLicence extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'expires_at' => 'date'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function auditModule(): string
    {
        return 'staff';
    }
}
