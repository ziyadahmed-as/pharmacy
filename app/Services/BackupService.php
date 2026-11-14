<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\ApprovalException;
use App\Models\AuditLog;
use App\Models\BackupRun;
use App\Models\RestoreRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Service for Backing up and Restoring system data (UC-37).
 *
 * Enforces:
 * - Encryption with AES-256-CBC using server-side environment key (FR-8.10, AC-35).
 * - Retention: 30 daily and 12 month-end archives (BRD NFR-4).
 * - 26-hour staleness and failure monitoring (A1).
 * - Safe restore workflow with maintenance mode, safety backup, and audit survival (AC-21).
 *
 * @uc UC-37
 *
 * @fr FR-9.6, FR-8.10
 *
 * @nfr NFR-4, NFR-2
 *
 * @ac AC-21, AC-35
 */
class BackupService
{
    public const CIPHER = 'aes-256-cbc';

    /**
     * Tables included in database backup.
     *
     * @return list<string>
     */
    public function getBackupTables(): array
    {
        return [
            'users',
            'password_histories',
            'roles',
            'role_user',
            'settings',
            'products',
            'categories',
            'batches',
            'batch_stocks',
            'stock_receipts',
            'stock_receipt_lines',
            'stock_movements',
            'quarantine_records',
            'adjustment_requests',
            'stocktakes',
            'stocktake_lines',
            'disposals',
            'tills',
            'cash_shifts',
            'sales',
            'sale_items',
            'sale_returns',
            'sale_return_items',
            'customers',
            'prescriptions',
            'prescription_lines',
            'dispensing_events',
            'controlled_drug_register',
            'approval_requests',
            'audit_logs',
        ];
    }

    /**
     * Create an encrypted backup archive.
     *
     * @throws Throwable
     */
    public function createBackup(string $type = BackupRun::TYPE_FULL, ?string $customFilename = null): BackupRun
    {
        $diskName = (string) config('backup.disk', 'backup');
        $filename = $customFilename ?? sprintf('backup-%s-%s.enc', $type, now()->format('Y-m-d-His'));

        $run = BackupRun::create([
            'filename' => $filename,
            'disk' => $diskName,
            'type' => $type,
            'status' => BackupRun::STATUS_IN_PROGRESS,
            'is_encrypted' => true,
            'started_at' => now(),
        ]);

        try {
            // 1. Dump database data
            $databaseDump = [];
            foreach ($this->getBackupTables() as $table) {
                if (Schema::hasTable($table)) {
                    $databaseDump[$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
                }
            }

            // 2. Dump private uploaded files if full backup
            $filesDump = [];
            if ($type === BackupRun::TYPE_FULL) {
                $privatePath = storage_path('app/private');
                if (File::isDirectory($privatePath)) {
                    $allFiles = File::allFiles($privatePath);
                    foreach ($allFiles as $file) {
                        $relativePath = str_replace('\\', '/', $file->getRelativePathname());
                        $filesDump[$relativePath] = base64_encode($file->getContents());
                    }
                }
            }

            // 3. Serialize unencrypted payload
            $payload = json_encode([
                'version' => 1,
                'type' => $type,
                'created_at' => now()->toIso8601String(),
                'timestamp' => now()->timestamp,
                'database' => $databaseDump,
                'files' => $filesDump,
            ], JSON_THROW_ON_ERROR);

            $checksum = hash('sha256', $payload);

            // 4. Encrypt using AES-256-CBC
            $key = $this->getEncryptionKey();
            $ivLength = openssl_cipher_iv_length(self::CIPHER);
            $iv = random_bytes($ivLength);

            $encryptedData = openssl_encrypt($payload, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
            if ($encryptedData === false) {
                throw new RuntimeException('Encryption failed.');
            }

            // Envelope format
            $envelope = json_encode([
                'header' => 'PMS_ENC_V1',
                'iv' => base64_encode($iv),
                'checksum' => $checksum,
                'data' => base64_encode($encryptedData),
            ], JSON_THROW_ON_ERROR);

            // 5. Store on configured backup disk
            $disk = Storage::disk($diskName);
            $disk->put($filename, $envelope);

            $sizeBytes = strlen($envelope);

            $run->update([
                'size_bytes' => $sizeBytes,
                'checksum' => $checksum,
                'status' => BackupRun::STATUS_SUCCESS,
                'completed_at' => now(),
            ]);

            return $run;
        } catch (Throwable $e) {
            $run->update([
                'status' => BackupRun::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            // Alert SA, PM and Owner on failure (A1)
            $this->triggerFailureAlert($e->getMessage(), $run);

            throw $e;
        }
    }

    /**
     * Decrypt and verify integrity of a backup archive (AC-35).
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function verifyIntegrity(string|BackupRun $backup): array
    {
        $filename = $backup instanceof BackupRun ? $backup->filename : $backup;
        $diskName = $backup instanceof BackupRun ? $backup->disk : (string) config('backup.disk', 'backup');

        $disk = Storage::disk($diskName);
        if (! $disk->exists($filename)) {
            throw new RuntimeException("Backup file [{$filename}] does not exist on disk [{$diskName}].");
        }

        $rawEnvelope = $disk->get($filename);
        $envelope = json_decode($rawEnvelope, true);

        if (! is_array($envelope) || ($envelope['header'] ?? '') !== 'PMS_ENC_V1') {
            throw new RuntimeException('Invalid backup archive format.');
        }

        $iv = base64_decode($envelope['iv'] ?? '', true);
        $ciphertext = base64_decode($envelope['data'] ?? '', true);
        $expectedChecksum = $envelope['checksum'] ?? '';

        if (! $iv || ! $ciphertext) {
            throw new RuntimeException('Corrupted archive envelope.');
        }

        $key = $this->getEncryptionKey();
        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed. Invalid server key or altered archive.');
        }

        $actualChecksum = hash('sha256', $decrypted);
        if (! hash_equals($expectedChecksum, $actualChecksum)) {
            throw new RuntimeException('Checksum mismatch. The backup archive has been tampered with or corrupted.');
        }

        $data = json_decode($decrypted, true);
        if (! is_array($data)) {
            throw new RuntimeException('Corrupted backup payload.');
        }

        return $data;
    }

    /**
     * Execute full data restoration (STEP 5 & 6).
     *
     * @throws ApprovalException
     * @throws Throwable
     */
    public function restore(RestoreRequest $restoreRequest, User $actor): void
    {
        if (! $restoreRequest->isApproved()) {
            throw new ApprovalException(__('backup.restore_requires_approval'));
        }

        $restoreRequest->update(['status' => RestoreRequest::STATUS_RESTORING]);
        $restoreRequest->appendLog('Beginning restoration workflow.');

        // Step 1: Put application in maintenance mode
        Artisan::call('down', ['--secret' => 'restore-in-progress']);
        $restoreRequest->appendLog('System placed in maintenance mode.');

        $safetyBackupRun = null;

        try {
            // Step 2: Safety backup of current state
            $safetyBackupRun = $this->createBackup(
                BackupRun::TYPE_FULL,
                sprintf('safety-backup-before-restore-%s.enc', now()->format('Y-m-d-His'))
            );
            $restoreRequest->update(['safety_backup_filename' => $safetyBackupRun->filename]);
            $restoreRequest->appendLog("Safety backup created: {$safetyBackupRun->filename}");

            // Step 3: Verify archive integrity and decrypt
            $archive = $this->verifyIntegrity($restoreRequest->backup_filename);
            $restoreRequest->appendLog('Archive integrity and checksum verified successfully.');

            // Step 4: Export audit rows written AFTER the backup point (STEP 6)
            $backupCreatedAt = Carbon::parse($archive['created_at']);
            $postBackupAuditRows = AuditLog::query()
                ->where('created_at', '>', $backupCreatedAt)
                ->get()
                ->map(fn ($r) => (array) $r->getAttributes())
                ->all();

            $restoreRequest->appendLog(sprintf('Preserved %d audit log rows newer than backup point.', count($postBackupAuditRows)));

            // Step 5: Restore database tables from archive
            $this->restoreDatabaseTables($archive['database'] ?? []);
            $restoreRequest->appendLog('Database tables restored from archive.');

            // Step 6: Restore uploaded files if full archive
            if (! empty($archive['files']) && is_array($archive['files'])) {
                $privatePath = storage_path('app/private');
                foreach ($archive['files'] as $relativePath => $base64Content) {
                    $target = $privatePath.DIRECTORY_SEPARATOR.$relativePath;
                    File::ensureDirectoryExists(dirname($target));
                    File::put($target, base64_decode($base64Content));
                }
                $restoreRequest->appendLog('Uploaded private storage files restored.');
            }

            // Step 7: Run migrations check
            Artisan::call('migrate', ['--force' => true]);
            $restoreRequest->appendLog('Schema migration check completed.');

            // Step 8: Re-import preserved audit rows
            foreach ($postBackupAuditRows as $auditRow) {
                // Ensure no duplication by checking id or timestamp
                if (! DB::table('audit_logs')->where('id', $auditRow['id'])->exists()) {
                    DB::table('audit_logs')->insert($auditRow);
                }
            }
            $restoreRequest->appendLog('Preserved audit log rows re-imported successfully.');

            // Step 9: Write backup.restored audit entry
            AuditLogger::record(
                action: 'backup.restored',
                subject: $restoreRequest,
                old: [],
                new: [
                    'backup_filename' => $restoreRequest->backup_filename,
                    'restored_at' => now()->toIso8601String(),
                ],
                metadata: [
                    'safety_backup' => $safetyBackupRun->filename,
                    'preserved_audit_count' => count($postBackupAuditRows),
                ],
                user: $actor,
                description: "System data restored from backup {$restoreRequest->backup_filename}"
            );

            // Complete restore request
            $restoreRequest->update([
                'status' => RestoreRequest::STATUS_COMPLETED,
                'restored_at' => now(),
            ]);
            $restoreRequest->appendLog('Restoration completed successfully.');
        } catch (Throwable $e) {
            $restoreRequest->update([
                'status' => RestoreRequest::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
            $restoreRequest->appendLog('Restoration failed: '.$e->getMessage().'. Initiating safety rollback.');

            // Roll back to safety backup if created
            if ($safetyBackupRun) {
                try {
                    $safetyArchive = $this->verifyIntegrity($safetyBackupRun);
                    $this->restoreDatabaseTables($safetyArchive['database'] ?? []);
                    $restoreRequest->appendLog('Rolled back successfully to safety backup state.');
                } catch (Throwable $rollbackError) {
                    Log::critical('Rollback to safety backup failed: '.$rollbackError->getMessage());
                    $restoreRequest->appendLog('CRITICAL: Rollback failed: '.$rollbackError->getMessage());
                }
            }

            throw $e;
        } finally {
            // Leave maintenance mode
            Artisan::call('up');
            $restoreRequest->appendLog('System left maintenance mode.');
        }
    }

    /**
     * Restore database tables from payload.
     * Deletes in child-to-parent order and inserts in parent-to-child order
     * to prevent foreign key constraint violations while preserving append-only
     * tables (audit_logs, controlled_drug_register) and user accounts.
     *
     * @param  array<string, list<array<string, mixed>>>  $databaseData
     */
    protected function restoreDatabaseTables(array $databaseData): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            try {
                DB::statement('PRAGMA foreign_keys = OFF;');
            } catch (Throwable) {
            }
        } elseif ($driver === 'mysql') {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            } catch (Throwable) {
            }
        }

        // Child-to-parent deletion order to respect foreign key integrity
        $deletionOrder = [
            'dispensing_events',
            'prescription_lines',
            'prescriptions',
            'sale_return_items',
            'sale_returns',
            'sale_items',
            'sales',
            'shift_cash_movements',
            'shifts',
            'disposals',
            'stocktake_lines',
            'stocktakes',
            'adjustment_requests',
            'quarantine_records',
            'stock_movements',
            'batch_stocks',
            'stock_receipt_lines',
            'stock_receipts',
            'batches',
            'products',
            'categories',
            'customers',
            'tills',
        ];

        try {
            // 1. Delete operational tables in child-to-parent order
            foreach ($deletionOrder as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            // 2. Insert operational tables in parent-to-child order
            $insertionOrder = array_reverse($deletionOrder);
            foreach ($insertionOrder as $table) {
                if (! empty($databaseData[$table]) && Schema::hasTable($table)) {
                    foreach (array_chunk($databaseData[$table], 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            // 3. Upsert user accounts & settings (never delete users to protect audit log FKs)
            foreach ($databaseData['users'] ?? [] as $userRow) {
                DB::table('users')->updateOrInsert(['id' => $userRow['id']], $userRow);
            }
            foreach ($databaseData['settings'] ?? [] as $settingRow) {
                DB::table('settings')->updateOrInsert(['key' => $settingRow['key']], $settingRow);
            }

            // 4. Controlled drug register is append-only: insert any missing rows from archive
            foreach ($databaseData['controlled_drug_register'] ?? [] as $cdrRow) {
                if (Schema::hasTable('controlled_drug_register') && ! DB::table('controlled_drug_register')->where('id', $cdrRow['id'])->exists()) {
                    DB::table('controlled_drug_register')->insert($cdrRow);
                }
            }
        } finally {
            if ($driver === 'sqlite') {
                try {
                    DB::statement('PRAGMA foreign_keys = ON;');
                } catch (Throwable) {
                }
            } elseif ($driver === 'mysql') {
                try {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                } catch (Throwable) {
                }
            }
        }
    }

    /**
     * Custom retention cleanup (STEP 2):
     * Keeps `backup.retention_daily` (30) daily and `backup.retention_month_end` (12) month-end copies.
     *
     * @return list<string> Deleted backup filenames
     */
    public function clean(): array
    {
        $dailyLimit = (int) (setting('backup.retention_daily') ?? config('backup.retention_daily', 30));
        $monthEndLimit = (int) (setting('backup.retention_month_end') ?? config('backup.retention_month_end', 12));

        $successfulRuns = BackupRun::successful()->orderByDesc('started_at')->get();

        $monthEndKept = 0;
        $dailyKept = 0;
        $deleted = [];

        $disk = Storage::disk(config('backup.disk', 'backup'));

        foreach ($successfulRuns as $run) {
            $isMonthEnd = $run->started_at->copy()->endOfDay()->isSameDay($run->started_at->copy()->endOfMonth());

            if ($isMonthEnd && $monthEndKept < $monthEndLimit) {
                $monthEndKept++;

                continue;
            }

            if ($dailyKept < $dailyLimit) {
                $dailyKept++;

                continue;
            }

            // Exceeds both retention windows: delete file and record
            if ($disk->exists($run->filename)) {
                $disk->delete($run->filename);
            }
            $deleted[] = $run->filename;
            $run->delete();
        }

        return $deleted;
    }

    /**
     * Check if backups are stale (>26 hours) or failed (A1).
     */
    public function checkStaleness(): bool
    {
        $lastSuccess = BackupRun::successful()->latest('completed_at')->first();
        $latestRun = BackupRun::latest('started_at')->first();

        $isStale = false;
        $reason = '';

        if (! $lastSuccess) {
            $isStale = true;
            $reason = 'No successful backup has ever been completed.';
        } elseif ($lastSuccess->completed_at < now()->subHours(26)) {
            $isStale = true;
            $hours = now()->diffInHours($lastSuccess->completed_at);
            $reason = "No successful backup in the last {$hours} hours (threshold is 26 hours).";
        } elseif ($latestRun && $latestRun->isFailed()) {
            $isStale = true;
            $reason = "Most recent backup attempt failed: {$latestRun->error_message}";
        }

        if ($isStale) {
            Notifier::toRoles(
                [Role::SystemAdmin, Role::PharmacyManager, Role::Owner],
                'backup.alert',
                ['reason' => $reason],
                dedupeKey: 'backup.stale.'.now()->format('Y-m-d-H')
            );

            AuditLogger::record(
                action: 'backup.stale',
                subject: null,
                old: [],
                new: [],
                metadata: ['reason' => $reason],
                description: "Backup staleness alert triggered: {$reason}"
            );
        }

        return $isStale;
    }

    /**
     * Check if currently within opening hours for hourly backup.
     */
    public function isWithinOpeningHours(): bool
    {
        $hours = (string) (setting('backup.opening_hours') ?? config('backup.opening_hours', '08:00-20:00'));
        $parts = explode('-', $hours);
        if (count($parts) !== 2) {
            return true;
        }

        $now = now()->format('H:i');
        $start = trim($parts[0]);
        $end = trim($parts[1]);

        return $now >= $start && $now <= $end;
    }

    /**
     * Get the binary encryption key from environment (never stored in DB or visible to SA).
     */
    public function getEncryptionKey(): string
    {
        $raw = (string) (config('backup.encryption_key') ?: env('APP_KEY'));
        if (str_starts_with($raw, 'base64:')) {
            $raw = base64_decode(substr($raw, 7));
        }

        return hash('sha256', $raw, true); // 32-byte key for AES-256
    }

    protected function triggerFailureAlert(string $error, BackupRun $run): void
    {
        Notifier::toRoles(
            [Role::SystemAdmin, Role::PharmacyManager, Role::Owner],
            'backup.failed',
            ['error' => $error, 'run_id' => $run->id],
            dedupeKey: 'backup.failed.'.$run->id
        );

        AuditLogger::record(
            action: 'backup.failed',
            subject: $run,
            old: [],
            new: ['status' => BackupRun::STATUS_FAILED, 'error' => $error],
            description: "Backup run #{$run->id} failed: {$error}"
        );
    }
}
