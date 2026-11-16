<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRunsLog extends Model
{
    public $timestamps = false;

    protected $table = 'report_runs_log';

    protected $fillable = [
        'user_id',
        'report_key',
        'filters',
        'row_count',
        'duration_ms',
        'run_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'run_at' => 'datetime',
        'duration_ms' => 'float',
        'row_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
