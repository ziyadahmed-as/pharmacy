# Stage 1 already had partial UC-33 and UC-36 code built on Services, a hard-coded seeder with passwords and a config file for security limits.

**Status:** accepted (Stage 1)

## Context
Refactored to Actions, AuditLogger, settings-driven limits and a BootstrapSeeder reading .env (generates and prints a password once when none is set). Old AuditService, AuthenticationService, AccountRecoveryService, UserManagementService, the admin recovery pages and the identity_verifications model were removed; the identity_verifications table remains because merged migrations are not edited, and is unused. UC-36 identity confirmation is now a checkbox plus a note stored in the audit entry.

## Decision
A later cleanup migration can drop identity_verifications.

## Consequence / revisit when

