# UC-31 E2: the SA sees technical entries only.

**Status:** accepted (Stage 1)

## Context
The SA sees action prefixes auth., user., approval., settings., audit. Staff entries (personal data) and any business prefix are hidden; business prefixes are also masked in values. Owner and PM see everything.

## Decision
Each later stage adds its prefix to AuditLogQuery::BUSINESS_PREFIXES.

## Consequence / revisit when

