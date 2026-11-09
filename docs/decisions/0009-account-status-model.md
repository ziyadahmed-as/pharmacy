# AGENTS.md sketches users.status.

**Status:** accepted (Stage 1)

## Context
Existing columns are kept: is_active plus account_status (active, pending_approval, rejected) plus locked_at; User::status is computed (active, locked, pending_approval, deactivated, rejected). A rejected account request renames its username so the name can be reused; rows are never deleted.

## Decision
None.

## Consequence / revisit when

