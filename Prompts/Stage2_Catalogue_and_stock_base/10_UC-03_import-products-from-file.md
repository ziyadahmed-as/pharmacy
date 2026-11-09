# UC-03  Import products from file

| Field | Value |
|---|---|
| Stage | 2. Catalogue and stock base |
| Build order | 10 |
| Priority | Must (Phase 1) |
| Depends on | UC-01 |
| Suggested branch | `uc-03-import-products-from-file` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-03 Import products from file. Depends on: UC-01. BRD: FR-1.8; D-09; NFR-10. AC-27.

GOAL: load opening product data safely, with nothing saved before the user confirms.

STEP 1 Choose a maintained package for CSV and XLSX reading (for example Laravel Excel or OpenSpout); verify Laravel 12 compatibility and record the choice in `docs/decisions/`.
STEP 2 Data: `product_imports` (file path, status, mapping json, totals) and `product_import_rows` (row number, raw data json, errors json, status).
STEP 3 Action `PreviewImport`: upload, map columns (name, generic, strength, form, pack, unit, manufacturer, category, barcode, classification, levels, loose allowed, optional price), validate every row (duplicates in file and in database, missing mandatory fields, invalid classification, invalid price). Process large files in a queued job.
STEP 4 Validation report screen: counts, row-level errors, filter "errors only", download error CSV.
STEP 5 Action `ConfirmImport`: A1 exclude error rows and import the rest; A2 cancel saves nothing. Saved rows use the same actions as UC-01 and UC-02 (bulk price). Summary of saved and rejected rows. Audit the import.
STEP 6 E1: empty or unreadable file refused.

TESTS: file with duplicates and missing fields shows a report and saves nothing until confirmed (AC-27); A1 imports valid rows only; A2 cancel saves nothing; empty file refused; 20,000-row file imports without timeout; audit entry; only PM may import.

DONE WHEN: all green; sample CSV template in `docs/`.
````
