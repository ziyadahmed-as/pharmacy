<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\SettingsCatalogue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Typed, cached settings (UC-38). Read with `setting('group.key', $default)`.
 *
 * Resolution order: database value -> BRD default in SettingsCatalogue
 * (and config/pms.php seed defaults) -> the caller's default.
 * A key with no BRD default and no stored value is "Not set" and returns the caller's default.
 *
 * @uc UC-38 @fr FR-9.4,FR-9.5,FR-9.7 @ac AC-31
 */
class SettingsRepository
{
    public const CACHE_KEY = 'pms.settings.all';

    /** @var array<string, mixed>|null per-request memo */
    private ?array $memo = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->stored();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        $catalogue = SettingsCatalogue::find($key);
        if ($catalogue !== null && $catalogue['default'] !== null) {
            return $catalogue['default'];
        }

        $configDefault = config('pms.defaults.'.$key);
        if ($configDefault !== null) {
            return $configDefault;
        }

        return $default;
    }

    /** True when neither a stored value nor a BRD default exists ("Not set"). */
    public function isNotSet(string $key): bool
    {
        $stored = $this->stored();
        if (array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== '' && $stored[$key] !== []) {
            return false;
        }

        $catalogue = SettingsCatalogue::find($key);

        return $catalogue === null || $catalogue['default'] === null;
    }

    /** Write a value (only called by the approval handler or the seeder). */
    public function put(string $key, mixed $value, ?int $userId = null): Setting
    {
        $catalogue = SettingsCatalogue::find($key);
        abort_if($catalogue === null, 422, "Unknown setting {$key}");

        $row = Setting::updateOrCreate(
            ['key' => $key],
            [
                'group' => $catalogue['group'],
                'value' => $value,
                'type' => $catalogue['type'],
                'description' => $catalogue['description'],
                'is_sensitive' => $catalogue['sensitive'] ?? false,
                'updated_by' => $userId,
            ]
        );

        $this->flush();

        return $row;
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return $this->memo = [];
            }

            return $this->memo = Cache::remember(
                self::CACHE_KEY,
                3600,
                fn () => Setting::query()->pluck('value', 'key')->all()
            );
        } catch (\Throwable) {
            return $this->memo = [];
        }
    }
}
