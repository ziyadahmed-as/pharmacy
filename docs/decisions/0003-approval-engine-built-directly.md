# UC-35 prompt first uses a temporary approval gateway, UC-34 later replaces it.

**Status:** accepted (Stage 1)

## Context
Because Stage 1 is delivered as one unit, the real UC-34 engine (ApprovalService, handler registry, router, expiry) was built first and UC-35 and UC-38 use it directly. No temporary gateway exists.

## Decision
None. Later use cases register an ApprovalHandler in AppServiceProvider.

## Consequence / revisit when

