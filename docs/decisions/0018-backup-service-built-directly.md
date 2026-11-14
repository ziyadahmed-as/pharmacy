# 0018: Backup and Restore Service Built Directly Without External Composer Dependency

**Status:** accepted (Stage 6)

## Context
UC-37 prompt references `spatie/laravel-backup`. However, as recorded in Decision 0001 and 0002, the sandbox build environment has no outbound Packagist access, and `vendor/` does not include `spatie/laravel-backup` or `spatie/db-dumper`.

Furthermore, external command wrappers (`mysqldump`, `pg_dump`, `zip`) require external system binaries that may not be available across cross-platform environments (Windows / Linux) or SQLite test databases.

## Decision
Implement a pure, robust native Laravel backup and restore engine in `App\Services\BackupService`:
1. Database serialization: Dumps all system tables directly through Eloquent and PDO into structured JSON streams.
2. File packaging: Collects uploaded assets from private storage.
3. Encryption: Cryptographically secures the archive using AES-256-CBC via PHP OpenSSL with a server environment key (`BACKUP_ENCRYPTION_KEY` or `APP_KEY`). The key is strictly isolated from the UI, database, and the System Admin role (FR-8.10, AC-35).
4. Retention: Implements custom cleanup honouring 30 daily backups (`backup.retention_daily`) and 12 month-end archives (`backup.retention_month_end`).
5. Restore workflow: Integrates with the UC-34 approval framework (`backup.restore`), enforces dual-custody authorization (PM/Owner must approve), takes an automatic safety backup prior to restore, verifies checksum/encryption integrity, and ensures audit log rows created after the backup point survive the restore process.
