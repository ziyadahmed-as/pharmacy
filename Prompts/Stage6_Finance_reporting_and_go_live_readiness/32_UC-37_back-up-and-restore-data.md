# UC-37  Back up and restore data

| Field | Value |
|---|---|
| Stage | 6. Finance, reporting and go-live readiness |
| Build order | 32 |
| Priority | Must (Phase 1) |
| Depends on | UC-33, UC-34 |
| Suggested branch | `uc-37-back-up-and-restore-data` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-37 Back up and restore data. Depends on: UC-33, UC-34. BRD: FR-9.6, 8.10; NFR-4, NFR-2. AC-21, AC-35.

STEP 1 Use spatie/laravel-backup (verify Laravel 12 support). Daily full backup (database and uploaded files) and hourly database backups during `backup.opening_hours`, to a separate disk (external drive path or S3-compatible), configurable.
STEP 2 Retention: keep `backup.retention_daily` (30) daily and `backup.retention_month_end` (12) month-end copies; write a custom cleanup honouring both.
STEP 3 Encryption: encrypt archives. The key lives only in the server environment and is never shown or readable by the SA role (E1, FR-8.10). Technical logs mask patient, sales, cost and financial values.
STEP 4 Monitoring: SA dashboard with last success, last failure, size, age; "Run backup now". A1: a failed backup, or no success in 26 hours, alerts SA, PM and Owner and writes an audit entry.
STEP 5 Restore: SA selects a backup and gives a reason; approval type `backup.restore` goes to PM or Owner (UC-34, E2 restore without approval is refused, also by direct action call). On approval a queued job: maintenance mode, safety backup of the current state, integrity check (checksum and test-decrypt), restore database and files, migration check, leave maintenance mode, log every step; roll back to the safety backup on any failure.
STEP 6 Audit-log rule: the append-only audit log must survive a restore. Export audit rows newer than the backup point before restoring and re-import afterwards, then write `backup.restored`. Document the approach and trade-offs.
STEP 7 The Manager checks key figures after restore (checklist screen). Write `docs/backup-restore-runbook.md`: clean-machine test restore (required before go-live and quarterly), RTO 4 h, RPO 1 h, who does what.

TESTS (fake disks): schedule entries and times; retention keeps 30 daily and 12 month-end; restore needs approval and the initiator cannot approve (AC-21); direct restore call without an approved request denied; SA cannot see key material or backup contents (AC-35); failure and 26-hour staleness alert; audit rows written after the backup survive a simulated restore.

DONE WHEN: all green; a real restore rehearsed on a clean database and recorded in the runbook.
````
