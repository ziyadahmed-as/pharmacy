<?php

namespace App\Services;

/**
 * Server-side proof that the user re-entered their password just now (BR-14,
 * AGENTS.md rule 5). The ConfirmWithPassword modal calls grant(); the action that
 * needs it calls consume(). One use per grant, valid for two minutes, so a forged
 * browser event can never stand in for the password.
 */
final class ReAuthentication
{
    private const TTL = 120;

    public static function grant(string $purpose): void
    {
        session()->put('reauth.'.$purpose, time());
    }

    /** Returns true once, then forgets the grant. */
    public static function consume(string $purpose): bool
    {
        $at = session()->pull('reauth.'.$purpose);

        return is_int($at) && (time() - $at) <= self::TTL;
    }
}
