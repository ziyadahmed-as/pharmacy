# AGENTS.md section 11 asks for Larastan. It is not in vendor/ and cannot be installed here.

**Status:** accepted (Stage 1)

## Context
Pint is run and clean. composer.json gets an 'analyse' script that expects larastan, but larastan is not added to require-dev (no lock update possible offline).

## Decision
Run composer require --dev larastan/larastan and add phpstan.neon at level 5 on a networked machine before Stage 2.

## Consequence / revisit when

