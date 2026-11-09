<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Support\Str;

/**
 * Logs created / updated / deactivated with old and new values (S0 step 3).
 * The model may define `auditModule()` (default: table name) and
 * `auditExclude()` (attributes never logged).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLogger::record(
                $model->auditKey('created'),
                $model,
                [],
                $model->auditValues($model->getAttributes()),
            );
        });

        static::updated(function ($model) {
            $changes = $model->auditValues($model->getChanges());
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->auditValues($model->getOriginal()), $changes);

            $verb = 'updated';
            if (array_key_exists('is_active', $changes) && ! $changes['is_active']) {
                $verb = 'deactivated';
            }

            AuditLogger::record($model->auditKey($verb), $model, $old, $changes);
        });
    }

    public function auditModule(): string
    {
        return $this->getTable();
    }

    /** @return list<string> */
    public function auditExclude(): array
    {
        return ['updated_at', 'created_at', 'remember_token'];
    }

    protected function auditKey(string $verb): string
    {
        return $this->auditModule().'.'.Str::snake(class_basename($this)).'.'.$verb;
    }

    /** @param  array<string, mixed>  $values */
    protected function auditValues(array $values): array
    {
        return array_diff_key($values, array_flip($this->auditExclude()));
    }
}
