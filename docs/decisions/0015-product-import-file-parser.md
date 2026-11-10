# Decision 0015: File Parser for UC-03 Product Import

## Context
UC-03 imports opening product catalogue data from files (CSV and XLSX).
The environment is an offline / air-gapped terminal without Packagist access, where external packages cannot be pulled on demand.

## Decision
We implement a zero-dependency, high-performance streaming file reader:
- For CSV files: PHP native `SplFileObject` / `fgetcsv` streaming line-by-line with negligible memory usage, supporting 20,000+ rows smoothly without timeouts.
- For XLSX files: Native lightweight XML streaming parser reading `xl/worksheets/sheet1.xml` and `xl/sharedStrings.xml` via PHP's built-in `ZipArchive` and `XMLReader`.

## Consequences
- Fully compatible with Laravel 12 and PHP 8.2+.
- No third-party dependency maintenance risks or vendor lock-in.
- Handles arbitrary file encodings, delimiters, and streaming for tens of thousands of rows under memory constraints.
