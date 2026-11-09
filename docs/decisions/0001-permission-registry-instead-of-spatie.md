# Prompts/AGENTS.md section 4 asks for spatie/laravel-permission. The build environment has no Packagist access, so no package could be installed, and the committed vendor/ does not contain it.

**Status:** accepted (Stage 1)

## Context
Permissions live in App\Support\Permissions (the BRD 4.3 matrix, dotted names, an active flag per Stage). One Gate is defined per active permission; User::hasPermission() unions the roles a person holds. Roles stay in the existing roles table.

## Decision
Same contract (can('x.y'), role to permission map) so spatie can replace it with a data migration. Revisit when composer can reach Packagist; later stages flip permissions to active by editing one file.

## Consequence / revisit when

