# UC-35 says the temporary password is shown once to the SA, never stored or logged in plain text, but the account is approved by someone else, later.

**Status:** accepted (Stage 1)

## Context
On approval the handler generates the password, stores only its hash in users and an ENCRYPTED copy in credential_reveals for the requesting SA (72 hours). The SA clicks 'Show once'; the row is deleted on reveal or expiry. The value never appears in audit, notifications or approval payloads (tested). UC-36 reset shows the password in the same request.

## Decision
If the 72 hours pass, the SA uses Reset password.

## Consequence / revisit when

