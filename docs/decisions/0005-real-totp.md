# The earlier UC-33 code accepted the fixed code 123456 as a valid second factor.

**Status:** accepted (Stage 1)

## Context
Replaced by RFC 6238 TOTP (App\Services\Totp, tested against RFC vectors), throttled to 5 tries a minute. Two-step sign-in is optional, offered from 'My security' to SA, Owner and PM only.

## Decision
A QR code is not rendered (no QR library offline); the key and otpauth URI are shown instead.

## Consequence / revisit when

