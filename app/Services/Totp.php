<?php

namespace App\Services;

/**
 * RFC 6238 time-based one-time passwords (SHA-1, 6 digits, 30 s), compatible
 * with Google Authenticator, Microsoft Authenticator and similar apps.
 * Optional second factor for SA, OW and PM (UC-33 step 6).
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function code(string $secret, ?int $timestamp = null, int $period = 30, int $digits = 6): string
    {
        $counter = intdiv($timestamp ?? time(), $period);
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /** Accepts the previous, current and next 30-second window to tolerate clock drift. */
    public static function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        if (strlen($code) !== 6 || ! ctype_digit($code) || $secret === '') {
            return false;
        }
        $now = $timestamp ?? time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $now + ($i * 30)), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function provisioningUri(string $secret, string $account, string $issuer = 'PMS'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    private static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function base32Decode(string $input): string
    {
        $input = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $input));
        $bits = '';
        foreach (str_split($input) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
