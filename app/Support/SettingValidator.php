<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Per-type validation and normalisation of setting values (UC-38 step 5, E1). */
final class SettingValidator
{
    /** @throws ValidationException */
    public static function normalise(string $key, mixed $raw): mixed
    {
        $def = SettingsCatalogue::find($key) ?? throw ValidationException::withMessages(['key' => __('settings.error.unknown')]);
        $type = $def['type'];
        $fail = fn (string $m) => throw ValidationException::withMessages(['value' => __($m)]);
        $isEmpty = $raw === null || $raw === '' || $raw === [];

        if ($isEmpty && ! in_array($type, ['string_list', 'bool'], true)) {
            if ($def['required']) {
                $fail('settings.error.required');
            }

            return null; // clearing an optional value returns it to "Not set"
        }

        switch ($type) {
            case 'pos_int':
                if (! is_numeric($raw) || (string) (int) $raw !== (string) (int) $raw || (int) $raw < 1 || floor((float) $raw) != (float) $raw) {
                    $fail('settings.error.pos_int');
                }

                return (int) $raw;

            case 'bool':
                return filter_var($raw, FILTER_VALIDATE_BOOLEAN);

            case 'string':
                $s = trim((string) $raw);
                if ($s === '' && $def['required']) {
                    $fail('settings.error.required');
                }

                return mb_substr($s, 0, 255);

            case 'currency':
                $s = strtoupper(trim((string) $raw));
                if (! preg_match('/^[A-Z]{3}$/', $s)) {
                    $fail('settings.error.currency');
                }

                return $s;

            case 'desc_int_list':
                $list = is_array($raw) ? $raw : preg_split('/[\s,]+/', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY);
                foreach ($list as $n) {
                    if (! ctype_digit((string) $n) || (int) $n < 1) {
                        $fail('settings.error.int_list');
                    }
                }
                $list = array_map('intval', $list);
                $sorted = $list;
                rsort($sorted);
                if ($list !== $sorted || count(array_unique($list)) !== count($list)) {
                    $fail('settings.error.descending');
                }

                return $list;

            case 'money':
                if (! is_numeric($raw) || (float) $raw < 0) {
                    $fail('settings.error.money');
                }

                return round((float) $raw, 2);

            case 'payment_methods':
                $list = is_array($raw) ? $raw : preg_split('/[\s,]+/', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY);
                $allowed = ['cash', 'mobile_money', 'card', 'bank_transfer'];
                if ($list === [] || array_diff($list, $allowed) !== []) {
                    $fail('settings.error.payment_methods');
                }

                return array_values(array_unique($list));

            case 'string_list':
                $list = is_array($raw) ? $raw : preg_split('/\s*,\s*/', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY);

                return array_values(array_filter(array_map('trim', $list)));

            case 'locale':
                if (! in_array($raw, ['en', 'am'], true)) {
                    $fail('settings.error.locale');
                }

                return $raw;

            case 'receipt_language':
                if (! in_array($raw, ['default', 'en', 'am'], true)) {
                    $fail('settings.error.locale');
                }

                return $raw;

            case 'image':
                return (string) $raw;
        }

        return $raw;
    }
}
