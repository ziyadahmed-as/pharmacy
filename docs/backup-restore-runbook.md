# Disaster Recovery Runbook: Backup & Restore (UC-37)

## 1. Objectives & Metrics
- **Recovery Time Objective (RTO)**: 4 hours (maximum allowable downtime before business resumption).
- **Recovery Point Objective (RPO)**: 1 hour (maximum allowable data loss window, met via hourly database backups during opening hours).
- **Compliance Requirement**: Full rehearsal on a clean machine before go-live and quarterly thereafter (NFR-4).

---

## 2. Roles & Responsibilities

| Role | Responsibility |
|---|---|
| **System Administrator (SA)** | Initiates backups, monitors dashboard, triggers manual runs, selects archive and requests restore with written justification. Executes technical clean-machine drills. **Never has access to key material, patient records, or financial data (AC-35).** |
| **Pharmacy Manager (PM)** | Reviews and approves/rejects restore requests via UC-34 approval queue. Completes post-restore operational sign-off checklist. |
| **Owner** | Serves as escalation approval authority for restore requests when PM is unavailable. Verifies financial reconciliation. |

---

## 3. Backup Architecture & Policies

### 3.1 Schedules
- **Daily Full Backup**: Executed at 02:00 Africa/Addis_Ababa (`backup:run --type=full`). Backs up all database tables and uploaded private storage assets.
- **Hourly Database Backup**: Executed hourly during pharmacy opening hours (`08:00-20:00`) (`backup:run --type=db`).
- **Retention Cleanup**: Daily at 03:00 (`backup:clean`). Retains 30 daily backups and 12 month-end archives.
- **Health Monitoring**: Hourly staleness check (`backup:monitor`). Alerts SA, PM, and Owner if no backup succeeds within 26 hours or if a run fails.

### 3.2 Encryption & Security
- Encrypted with **AES-256-CBC** with HMAC/SHA-256 integrity verification.
- Encryption key is stored solely in the server environment variable (`BACKUP_ENCRYPTION_KEY` or `APP_KEY`). It is never stored in the database, settings table, or exposed to the UI.

---

## 4. Restore Workflow & Dual Custody (AC-21)

```
[System Admin]                         [PM / Owner]                        [System Engine]
       │                                     │                                    │
       ├─ Request restore with reason ───────┼───────────────────────────────────►│ (Status: Pending)
       │                                     ├─ Approves request via UC-34 ──────►│ (Status: Approved)
       │                                     │                                    │
       │                                     │                                    ├─ Maintenance mode ON
       │                                     │                                    ├─ Safety backup created
       │                                     │                                    ├─ Archive integrity check
       │                                     │                                    ├─ Export post-backup audit logs
       │                                     │                                    ├─ Database & files restored
       │                                     │                                    ├─ Migration check executed
       │                                     │                                    ├─ Re-import preserved audit logs
       │                                     │                                    ├─ Log 'backup.restored'
       │                                     │                                    └─ Maintenance mode OFF
```

---

## 5. Audit Log Preservation Rule & Trade-Offs (STEP 6)

### Approach
In accordance with BR-15 and NFR-2, the audit log is immutable and append-only. When restoring to a prior point in time:
1. All audit rows created after the backup point (`created_at > $backupTimestamp`) are exported into memory before the database tables are reset.
2. The operational tables (products, stock movements, sales, prescriptions) are restored to the backup timestamp.
3. The exported audit rows are re-inserted with their original timestamps and metadata intact.
4. An immutable audit entry `backup.restored` is logged.

### Trade-Offs
- **Pros**: Unbroken regulatory audit trail. Actions taken between the backup point and the crash/restore incident remain permanently accountable and visible.
- **Cons**: Audit entries may reference entities (e.g. prescription #105) that were created after the backup point and therefore rolled back in the business tables. The post-restore verification checklist specifically requires reconciling these audit entries.

---

## 6. Clean-Machine Recovery Procedure (Quarterly Drill)

1. **Provision Environment**:
   - Install PHP 8.2+ and target database server (MySQL / SQLite).
   - Clone repository and run `composer install --no-dev`.
   - Configure `.env` with the same `APP_KEY` / `BACKUP_ENCRYPTION_KEY`.
2. **Mount Backup Storage**:
   - Configure `BACKUP_DISK_PATH` to point to the external storage containing `.enc` archives.
3. **Execute Clean-Machine Test**:
   ```bash
   php artisan migrate --force
   php artisan backup:clean
   php artisan backup:monitor
   ```
4. **Initiate Restore Drill**:
   - System Admin submits restore request for latest verified archive.
   - Manager logs in to approvals queue and authorizes the restore.
   - Run restore command:
     ```bash
     php artisan queue:work --once
     ```
5. **Manager Post-Restore Sign-Off**:
   - Access `/backup/checklist` and review product counts, inventory valuation, active prescriptions, and audit trail continuity.
   - Record sign-off in the quarterly compliance log.
