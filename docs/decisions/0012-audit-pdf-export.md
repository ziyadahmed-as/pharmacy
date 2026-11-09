# No PDF library can be installed offline.

**Status:** accepted (Stage 1)

## Context
A small built-in writer (App\Services\SimplePdf) produces a Latin-text table PDF. Amharic text is replaced by ? in PDF; CSV (UTF-8 with BOM) carries Amharic correctly.

## Decision
Swap in dompdf or similar when available.

## Consequence / revisit when

