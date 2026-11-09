# 'Last 5 passwords cannot be reused' is ambiguous about the current password.

**Status:** accepted (Stage 1)

## Context
The current password plus the 4 previous ones are blocked (5 in total), from setting security.password_history.

## Decision
Change the setting if the BA means 5 previous plus current.

## Consequence / revisit when

