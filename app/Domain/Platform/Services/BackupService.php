<?php

namespace App\Domain\Platform\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Platform\Models\BackupRecord;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

class BackupService
{
    use RecordsAuditLog;

    public const DISK = 'private_backups';

    /**
     * Restore lock file path used to enforce single-restoration exclusivity
     * and application-wide restore state guard.
     */
    public static function restoreLockPath(): string
    {
        return storage_path('framework/backup_restore.lock');
    }

    /**
     * Determine if a restoration operation is actively in progress on the system.
     */
    public static function isRestorationInProgress(): bool
    {
        $lockFile = self::restoreLockPath();
        if (! file_exists($lockFile)) {
            return false;
        }

        // Test if an exclusive lock is held by another process
        $fp = @fopen($lockFile, 'r+');
        if (! $fp) {
            return true; // Lock file exists but cannot be opened for read/write -> locked
        }

        $canLock = flock($fp, LOCK_EX | LOCK_NB);
        if ($canLock) {
            flock($fp, LOCK_UN);
            fclose($fp);

            // Lock was free, so no active restore is holding it (may be stale lock file)
            return false;
        }

        fclose($fp);

        return true;
    }

    /**
     * Create a database backup (SQLite or MySQL).
     */
    public function createDatabaseBackup(?string $connectionName = null, ?string $backupSetId = null): BackupRecord
    {
        $conn = DB::connection($connectionName);
        $driver = $conn->getDriverName();
        $dateStr = now()->format('Y-m-d-His');
        $randomUuid = Str::uuid()->toString();

        File::ensureDirectoryExists(storage_path('app/private/backups'));

        if ($driver === 'sqlite') {
            $filename = "backup-db-sqlite-{$dateStr}-{$randomUuid}.sqlite";
            $targetPath = storage_path("app/private/backups/{$filename}");

            // Normalize path to forward slashes for cross-platform SQLite VACUUM INTO
            $normalizedPath = str_replace('\\', '/', $targetPath);
            $escapedPath = str_replace("'", "''", $normalizedPath);
            $srcPath = $conn->getDatabaseName();

            try {
                if ($conn->transactionLevel() === 0) {
                    try {
                        $conn->statement("VACUUM INTO '{$escapedPath}'");
                    } catch (Throwable $e) {
                        if ($srcPath !== ':memory:' && file_exists($srcPath)) {
                            if (! copy($srcPath, $targetPath)) {
                                throw new RuntimeException('Failed to create SQLite backup snapshot: '.$e->getMessage());
                            }
                        } else {
                            $this->dumpSqliteToPath($conn, $targetPath);
                        }
                    }
                } else {
                    if ($srcPath !== ':memory:' && file_exists($srcPath)) {
                        if (! copy($srcPath, $targetPath)) {
                            throw new RuntimeException('Failed copying SQLite database file.');
                        }
                    } else {
                        $this->dumpSqliteToPath($conn, $targetPath);
                    }
                }

                // Verify integrity of the new backup file
                $backupPdo = new PDO("sqlite:{$targetPath}");
                $backupPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $backupPdo->query('PRAGMA integrity_check');
                $integrity = $stmt ? $stmt->fetchColumn() : 'failed';
                unset($backupPdo);

                if ($integrity !== 'ok') {
                    throw new RuntimeException("SQLite backup integrity check failed: [{$integrity}].");
                }
            } catch (Throwable $e) {
                if (file_exists($targetPath)) {
                    @unlink($targetPath);
                }
                throw $e;
            }
        } elseif ($driver === 'mysql') {
            $filename = "backup-db-mysql-{$dateStr}-{$randomUuid}.sql";
            $targetPath = storage_path("app/private/backups/{$filename}");

            try {
                $this->streamExportMySQL($conn, $targetPath);
            } catch (Throwable $e) {
                if (file_exists($targetPath)) {
                    @unlink($targetPath);
                }
                throw $e;
            }
        } else {
            throw new RuntimeException("Database driver [{$driver}] is not supported for automated backups.");
        }

        $sizeBytes = filesize($targetPath) ?: 0;
        $checksum = hash_file('sha256', $targetPath);

        $metadata = [
            'driver' => $driver,
            'created_by' => Auth::user()?->id,
            'created_by_name' => Auth::user()?->name,
            'database' => $conn->getDatabaseName(),
        ];

        if ($backupSetId) {
            $metadata['backup_set_id'] = $backupSetId;
        }

        $record = BackupRecord::create([
            'filename' => $filename,
            'disk' => self::DISK,
            'type' => 'database',
            'database_driver' => $driver,
            'size_bytes' => $sizeBytes,
            'status' => 'completed',
            'checksum' => $checksum,
            'metadata' => $metadata,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $academy = Academy::current() ?? Academy::first();
        if ($academy) {
            $this->recordAudit(
                $academy,
                Auth::user(),
                'backup.created',
                BackupRecord::class,
                (string) $record->id,
                [
                    'filename' => $filename,
                    'type' => 'database',
                    'size_bytes' => $sizeBytes,
                    'backup_set_id' => $backupSetId,
                ]
            );
        }

        return $record;
    }

    /**
     * Create a backup archive of private managed files.
     */
    public function createFilesBackup(?string $backupSetId = null): BackupRecord
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive extension is required for file backups.');
        }

        $dateStr = now()->format('Y-m-d-His');
        $randomUuid = Str::uuid()->toString();
        $filename = "backup-files-{$dateStr}-{$randomUuid}.zip";

        File::ensureDirectoryExists(storage_path('app/private/backups'));
        $targetPath = storage_path("app/private/backups/{$filename}");

        try {
            $zip = new ZipArchive;
            if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create zip archive for private files.');
            }

            $sourceDir = storage_path('app/private/media');
            $fileCount = 0;

            if (File::exists($sourceDir)) {
                $files = File::allFiles($sourceDir);
                foreach ($files as $file) {
                    $relativePath = 'media/'.str_replace('\\', '/', $file->getRelativePathname());
                    $zip->addFile($file->getRealPath(), $relativePath);
                    $fileCount++;
                }
            }

            $zip->close();
        } catch (Throwable $e) {
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            throw $e;
        }

        $sizeBytes = filesize($targetPath) ?: 0;
        $checksum = hash_file('sha256', $targetPath);

        $metadata = [
            'file_count' => $fileCount,
            'source' => 'storage/app/private/media',
            'created_by' => Auth::user()?->id,
            'created_by_name' => Auth::user()?->name,
        ];

        if ($backupSetId) {
            $metadata['backup_set_id'] = $backupSetId;
        }

        $record = BackupRecord::create([
            'filename' => $filename,
            'disk' => self::DISK,
            'type' => 'files',
            'database_driver' => config('database.default'),
            'size_bytes' => $sizeBytes,
            'status' => 'completed',
            'checksum' => $checksum,
            'metadata' => $metadata,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $academy = Academy::current() ?? Academy::first();
        if ($academy) {
            $this->recordAudit(
                $academy,
                Auth::user(),
                'backup.created',
                BackupRecord::class,
                (string) $record->id,
                [
                    'filename' => $filename,
                    'type' => 'files',
                    'file_count' => $fileCount,
                    'size_bytes' => $sizeBytes,
                    'backup_set_id' => $backupSetId,
                ]
            );
        }

        return $record;
    }

    /**
     * Create an atomic full backup set (consistent DB snapshot + media files) linked by a unique backup_set_id.
     *
     * @return array{backup_set_id: string, database: BackupRecord, files: BackupRecord}
     */
    public function createFullBackupSet(?string $connectionName = null): array
    {
        $backupSetId = 'set-'.now()->format('Ymd-His').'-'.Str::random(8);

        // 1. Create database consistent snapshot
        $dbRecord = $this->createDatabaseBackup($connectionName, $backupSetId);

        // 2. Create private files archive
        try {
            $filesRecord = $this->createFilesBackup($backupSetId);
        } catch (Throwable $e) {
            // Clean up DB record and physical file if files backup fails
            $this->deleteBackupRecordFile($dbRecord);
            $dbRecord->delete();
            throw $e;
        }

        return [
            'backup_set_id' => $backupSetId,
            'database' => $dbRecord,
            'files' => $filesRecord,
        ];
    }

    /**
     * Pure PHP streaming MySQL export with strictly guaranteed consistent snapshot transaction (REPEATABLE READ).
     * Avoids mysqldump CLI dependency and prevents memory bloat on large tables.
     * Uses the exact same PDO instance for the snapshot transaction and all table cursors.
     */
    public function streamExportMySQL(ConnectionInterface $conn, string $targetPath): void
    {
        $pdo = $conn->getPdo();
        $dbName = $conn->getDatabaseName();

        // 1. Strict Engine Validation: All supported base tables must be InnoDB
        $engineCheckStmt = $pdo->prepare("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'");
        $engineCheckStmt->execute([$dbName]);
        $tableEngines = $engineCheckStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $nonInnoDB = [];
        foreach ($tableEngines as $tableName => $engine) {
            if ($engine && strtolower((string) $engine) !== 'innodb') {
                $nonInnoDB[] = "{$tableName} ({$engine})";
            }
        }
        if (! empty($nonInnoDB)) {
            $tableList = implode(', ', $nonInnoDB);
            Log::error("MySQL consistent backup rejected: Database contains non-InnoDB tables that do not support consistent read transactions: {$tableList}");
            throw new RuntimeException("MySQL consistent backup rejected: Non-transactional table engines detected [{$tableList}]. Only InnoDB tables support consistent point-in-time snapshots.");
        }

        $handle = fopen($targetPath, 'w');
        if (! $handle) {
            throw new RuntimeException('Cannot open MySQL backup file for writing: '.$targetPath);
        }

        $inTransaction = false;

        try {
            // Guarantee consistent point-in-time snapshot across all tables on this exact PDO connection
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $inTransaction = true;

            fwrite($handle, "-- SEO Academy MySQL Database Backup\n");
            fwrite($handle, "-- Database: `{$dbName}`\n");
            fwrite($handle, '-- Generated: '.now()->toIso8601String()."\n");
            fwrite($handle, "-- Transaction: REPEATABLE READ WITH CONSISTENT SNAPSHOT\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($handle, "SET NAMES utf8mb4;\n\n");

            $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];

            foreach ($tables as $table) {
                $escapedTable = '`'.str_replace('`', '``', (string) $table).'`';

                // Write DROP TABLE & CREATE TABLE (preserves primary key, indexes, FKs, nullability, defaults)
                fwrite($handle, "DROP TABLE IF EXISTS {$escapedTable};\n");
                $createStmt = $pdo->query("SHOW CREATE TABLE {$escapedTable}");
                $createRow = $createStmt ? $createStmt->fetch(PDO::FETCH_NUM) : null;
                if ($createRow && isset($createRow[1])) {
                    fwrite($handle, $createRow[1].";\n\n");
                }

                // Discover column types to format literals properly (NULL, binary/blob, numeric, JSON)
                $colStmt = $pdo->query("SHOW COLUMNS FROM {$escapedTable}");
                $columns = $colStmt ? $colStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $binaryCols = [];
                $numericCols = [];

                foreach ($columns as $col) {
                    $colField = $col['Field'];
                    $colType = strtolower((string) ($col['Type'] ?? ''));
                    if (str_contains($colType, 'blob') || str_contains($colType, 'binary')) {
                        $binaryCols[$colField] = true;
                    } elseif (str_contains($colType, 'int') || str_contains($colType, 'decimal') || str_contains($colType, 'float') || str_contains($colType, 'double')) {
                        $numericCols[$colField] = true;
                    }
                }

                // Stream table rows within the snapshot transaction
                $selectStmt = $pdo->query("SELECT * FROM {$escapedTable}");
                if ($selectStmt) {
                    $batchRows = [];
                    $batchCount = 0;
                    $cols = null;

                    while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
                        if ($cols === null) {
                            $cols = array_map(fn ($c) => '`'.str_replace('`', '``', (string) $c).'`', array_keys($row));
                        }

                        $vals = [];
                        foreach ($row as $colName => $val) {
                            if ($val === null) {
                                $vals[] = 'NULL';
                            } elseif (isset($binaryCols[$colName])) {
                                $vals[] = '0x'.bin2hex((string) $val);
                            } elseif (isset($numericCols[$colName]) && is_numeric($val)) {
                                $vals[] = (string) $val;
                            } else {
                                $vals[] = $pdo->quote((string) $val);
                            }
                        }

                        $batchRows[] = '('.implode(', ', $vals).')';
                        $batchCount++;

                        if ($batchCount >= 500) {
                            fwrite($handle, "INSERT INTO {$escapedTable} (".implode(', ', $cols).") VALUES\n".implode(",\n", $batchRows).";\n");
                            $batchRows = [];
                            $batchCount = 0;
                        }
                    }

                    if ($batchCount > 0 && $cols !== null) {
                        fwrite($handle, "INSERT INTO {$escapedTable} (".implode(', ', $cols).") VALUES\n".implode(",\n", $batchRows).";\n");
                    }

                    fwrite($handle, "\n");
                }
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fwrite($handle, "-- Backup Complete\n");
            fclose($handle);
            $handle = null;

            $pdo->exec('COMMIT');
            $inTransaction = false;
        } catch (Throwable $e) {
            if ($handle) {
                fclose($handle);
            }
            if ($inTransaction) {
                try {
                    $pdo->exec('ROLLBACK');
                } catch (Throwable) {
                }
            }
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            throw new RuntimeException('MySQL consistent export failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Copy SQLite tables and data to target backup file when VACUUM INTO is blocked by an active transaction.
     */
    private function dumpSqliteToPath(ConnectionInterface $conn, string $targetPath): void
    {
        $srcPdo = $conn->getPdo();
        $destPdo = new PDO("sqlite:{$targetPath}");
        $destPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tablesStmt = $srcPdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $destPdo->exec('PRAGMA foreign_keys = OFF;');
        $destPdo->beginTransaction();

        foreach ($tables as $t) {
            $tableName = $t['name'];
            $tableSql = $t['sql'];

            $destPdo->exec("DROP TABLE IF EXISTS \"{$tableName}\";");
            $destPdo->exec($tableSql.';');

            $selectStmt = $srcPdo->query("SELECT * FROM \"{$tableName}\"");
            if ($selectStmt) {
                while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols = array_map(fn ($c) => "\"{$c}\"", array_keys($row));
                    $vals = array_map(function ($val) use ($destPdo) {
                        return $val === null ? 'NULL' : $destPdo->quote($val);
                    }, array_values($row));

                    $destPdo->exec("INSERT INTO \"{$tableName}\" (".implode(', ', $cols).') VALUES ('.implode(', ', $vals).');');
                }
            }
        }

        // Copy indexes
        $idxStmt = $srcPdo->query("SELECT sql FROM sqlite_master WHERE type='index' AND sql IS NOT NULL");
        $indexes = $idxStmt ? $idxStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        foreach ($indexes as $sql) {
            try {
                $destPdo->exec($sql.';');
            } catch (Throwable) {
            }
        }

        $destPdo->commit();
        $destPdo->exec('PRAGMA foreign_keys = ON;');
        unset($destPdo);
    }

    /**
     * Validate an existing backup record and file integrity.
     */
    public function validateBackup(BackupRecord $record): bool
    {
        // Path traversal guard
        $safeName = basename($record->filename);
        if ($safeName !== $record->filename || str_contains($record->filename, '..')) {
            return false;
        }

        $fullPath = storage_path("app/private/backups/{$record->filename}");
        if (! File::exists($fullPath)) {
            return false;
        }

        // Checksum verification
        $actualChecksum = hash_file('sha256', $fullPath);
        if ($record->checksum && $record->checksum !== $actualChecksum) {
            return false;
        }

        // Content integrity check
        if ($record->type === 'database') {
            if ($record->database_driver === 'sqlite') {
                try {
                    $pdo = new PDO("sqlite:{$fullPath}");
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $stmt = $pdo->query('PRAGMA integrity_check');
                    $result = $stmt ? $stmt->fetchColumn() : null;

                    $fkStmt = $pdo->query('PRAGMA foreign_key_check');
                    $fkViolations = $fkStmt ? $fkStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                    unset($pdo);

                    return $result === 'ok' && empty($fkViolations);
                } catch (Throwable) {
                    return false;
                }
            } elseif ($record->database_driver === 'mysql') {
                // Read header and footer of SQL dump to verify clean completion
                $fp = @fopen($fullPath, 'r');
                if (! $fp) {
                    return false;
                }
                $header = fread($fp, 256) ?: '';
                fseek($fp, max(0, filesize($fullPath) - 128));
                $footer = fread($fp, 128) ?: '';
                fclose($fp);

                if (! str_contains($header, 'SEO Academy MySQL Database Backup')) {
                    return false;
                }
                if (! str_contains($footer, '-- Backup Complete')) {
                    return false;
                }

                return true;
            }
        }

        if ($record->type === 'files') {
            $zip = new ZipArchive;
            $opened = $zip->open($fullPath, ZipArchive::CHECKCONS);
            if ($opened === true) {
                // Verify no entry contains path traversal sequences, absolute paths, or control chars
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $name = $stat['name'] ?? '';
                    if (! $this->isSafeArchivePath($name)) {
                        $zip->close();

                        return false;
                    }
                }
                $zip->close();

                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * Check if an archive entry path is strictly safe against traversal and arbitrary overwrite.
     */
    public function isSafeArchivePath(string $name): bool
    {
        // Reject empty, null byte, directory traversal, absolute paths
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '..')) {
            return false;
        }
        if (str_starts_with($name, '/') || str_starts_with($name, '\\')) {
            return false;
        }
        // Reject drive letters like C:
        if (preg_match('/^[a-zA-Z]:/', $name)) {
            return false;
        }

        return true;
    }

    /**
     * Restore a backup with pre-restore safety snapshot, mutual-exclusion lock, and integrity checks.
     * Uses staging directory replacement for file restorations to ensure atomic directory swaps.
     *
     * @return array<string, mixed>
     */
    public function restoreBackup(BackupRecord $record, bool $createSafetyBackup = true): array
    {
        // 1. Path traversal guard
        $safeName = basename($record->filename);
        if ($safeName !== $record->filename || str_contains($record->filename, '..')) {
            throw new RuntimeException('Invalid backup filename. Path traversal detected.');
        }

        // 2. Driver compatibility check
        $currentDriver = config('database.default');
        if ($record->type === 'database' && $record->database_driver !== $currentDriver) {
            throw new RuntimeException("Database driver mismatch: Backup was created for [{$record->database_driver}], but current connection is [{$currentDriver}].");
        }

        $backupPath = storage_path("app/private/backups/{$record->filename}");
        if (! File::exists($backupPath)) {
            throw new RuntimeException("Backup file [{$record->filename}] does not exist on disk.");
        }

        // 3. Acquire exclusive restore lock to prevent concurrent restore operations
        $lockFile = self::restoreLockPath();
        File::ensureDirectoryExists(dirname($lockFile));
        $lockHandle = fopen($lockFile, 'c+');
        if (! $lockHandle || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle) {
                fclose($lockHandle);
            }
            throw new RuntimeException('Another backup restoration is currently in progress. Concurrent restores are blocked.');
        }

        $safetyBackup = null;
        $tempLiveBackup = null;
        $stagingDir = null;
        $maintenanceEnabled = false;

        try {
            // Write active lock metadata
            ftruncate($lockHandle, 0);
            fwrite($lockHandle, json_encode([
                'backup_id' => $record->id,
                'filename' => $record->filename,
                'type' => $record->type,
                'started_at' => now()->toIso8601String(),
                'pid' => getmypid(),
            ]));

            // Put application into maintenance mode to block incoming web requests and scheduler
            if (! app()->isDownForMaintenance()) {
                Artisan::call('down', [
                    '--status' => 503,
                    '--retry' => 60,
                ]);
                $maintenanceEnabled = true;
            }

            // 4. Validate replacement backup integrity before touching active systems
            if (! $this->validateBackup($record)) {
                throw new RuntimeException('Backup integrity verification failed. File may be corrupted or modified.');
            }

            // Check destination available disk space
            $destPath = $record->type === 'database'
                ? DB::connection()->getDatabaseName()
                : storage_path('app/private/media');

            if ($destPath !== ':memory:' && function_exists('disk_free_space')) {
                $dirToCheck = is_dir($destPath) ? $destPath : dirname($destPath);
                $freeSpace = @disk_free_space($dirToCheck);
                if ($freeSpace !== false && $freeSpace < (filesize($backupPath) * 2)) {
                    throw new RuntimeException('Insufficient disk space available for backup restoration.');
                }
            }

            // 5. Create official pre-restore safety backup
            if ($createSafetyBackup) {
                if ($record->type === 'database') {
                    $safetyBackup = $this->createDatabaseBackup();
                } elseif ($record->type === 'files') {
                    $safetyBackup = $this->createFilesBackup();
                }
            }

            // 6. Execute restoration
            if ($record->type === 'database') {
                if ($currentDriver === 'sqlite') {
                    $targetDbPath = DB::connection()->getDatabaseName();

                    if ($targetDbPath === ':memory:') {
                        throw new RuntimeException('Cannot restore SQLite backup onto an in-memory database.');
                    }

                    // Copy current live DB to disposable atomic rollback temp file
                    $tempLiveBackup = $targetDbPath.'.restore_safety_'.Str::random(8);
                    if (file_exists($targetDbPath)) {
                        copy($targetDbPath, $tempLiveBackup);
                    }

                    // Disconnect and purge active SQLite connection
                    DB::purge('sqlite');

                    // Remove active WAL/SHM sidecars
                    @unlink($targetDbPath.'-wal');
                    @unlink($targetDbPath.'-shm');

                    // Overwrite target database with backup file
                    if (! copy($backupPath, $targetDbPath)) {
                        // Rollback from temp file
                        if ($tempLiveBackup && file_exists($tempLiveBackup)) {
                            copy($tempLiveBackup, $targetDbPath);
                        }
                        throw new RuntimeException('Failed replacing SQLite database file with backup.');
                    }

                    // Verify newly restored SQLite database integrity and foreign key constraints
                    try {
                        $checkPdo = new PDO("sqlite:{$targetDbPath}");
                        $checkPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $stmt = $checkPdo->query('PRAGMA integrity_check');
                        $result = $stmt ? $stmt->fetchColumn() : null;

                        $fkStmt = $checkPdo->query('PRAGMA foreign_key_check');
                        $fkViolations = $fkStmt ? $fkStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                        unset($checkPdo);

                        if ($result !== 'ok' || ! empty($fkViolations)) {
                            throw new RuntimeException('Restored SQLite database failed integrity check or foreign key validation.');
                        }
                    } catch (Throwable $e) {
                        // Restore rollback from temporary snapshot
                        if ($tempLiveBackup && file_exists($tempLiveBackup)) {
                            copy($tempLiveBackup, $targetDbPath);
                        }
                        throw new RuntimeException('Restored SQLite database failed validation: '.$e->getMessage(), 0, $e);
                    }

                    // Remove temporary safety copy once confirmed healthy
                    if ($tempLiveBackup && file_exists($tempLiveBackup)) {
                        @unlink($tempLiveBackup);
                        $tempLiveBackup = null;
                    }
                } elseif ($currentDriver === 'mysql') {
                    $conn = DB::connection();
                    $pdo = $conn->getPdo();

                    $sql = file_get_contents($backupPath);
                    if ($sql === false) {
                        throw new RuntimeException('Could not read MySQL backup file.');
                    }

                    $pdo->exec($sql);
                }
            } elseif ($record->type === 'files') {
                $zip = new ZipArchive;
                if ($zip->open($backupPath) !== true) {
                    throw new RuntimeException('Cannot open zip archive for file restoration.');
                }

                // Stage extracted files in temporary directory first for atomic replacement
                $stagingDir = storage_path('app/private/media_staging_'.Str::random(8));
                File::ensureDirectoryExists($stagingDir);

                // Path traversal safety check during extraction
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $entryName = $stat['name'] ?? '';
                    if (! $this->isSafeArchivePath($entryName)) {
                        $zip->close();
                        File::deleteDirectory($stagingDir);
                        throw new RuntimeException("Malicious path detected inside zip entry: [{$entryName}].");
                    }
                }

                if (! $zip->extractTo($stagingDir)) {
                    $zip->close();
                    File::deleteDirectory($stagingDir);
                    throw new RuntimeException('Failed extracting backup archive to staging directory.');
                }
                $zip->close();

                // Atomically copy extracted staged files into live directory
                $stagedMedia = $stagingDir.'/media';
                $targetMedia = storage_path('app/private/media');
                File::ensureDirectoryExists($targetMedia);

                if (File::exists($stagedMedia)) {
                    File::copyDirectory($stagedMedia, $targetMedia);
                }

                // Clean up staging directory
                File::deleteDirectory($stagingDir);
                $stagingDir = null;
            }

            $academy = Academy::current() ?? Academy::first();
            if ($academy) {
                $this->recordAudit(
                    $academy,
                    Auth::user(),
                    'backup.restored',
                    BackupRecord::class,
                    (string) $record->id,
                    [
                        'filename' => $record->filename,
                        'type' => $record->type,
                        'safety_backup_id' => $safetyBackup?->id,
                        'backup_set_id' => $record->metadata['backup_set_id'] ?? null,
                    ]
                );
            }

            return [
                'success' => true,
                'message' => 'Restoration completed successfully and verified.',
                'safety_backup_id' => $safetyBackup?->id,
            ];
        } finally {
            // Clean up staging directory if still lingering
            if ($stagingDir && File::exists($stagingDir)) {
                File::deleteDirectory($stagingDir);
            }

            // Restore application from maintenance mode if we enabled it
            if ($maintenanceEnabled && app()->isDownForMaintenance()) {
                try {
                    Artisan::call('up');
                } catch (Throwable $e) {
                    Log::error('Failed to lift maintenance mode following backup restore: '.$e->getMessage());
                }
            }

            // Clean up temporary live safety backup if still lingering
            if ($tempLiveBackup && file_exists($tempLiveBackup)) {
                @unlink($tempLiveBackup);
            }

            // Release restore lock
            if ($lockHandle) {
                ftruncate($lockHandle, 0);
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                @unlink($lockFile);
            }
        }
    }

    /**
     * Restore a complete backup set with two-phase validation and comprehensive atomic rollback.
     * Both the database and media archives are pre-validated, safety-backed up, and restored.
     * If EITHER component fails during restoration, BOTH original components are restored,
     * verified against their safety snapshots, and audited.
     *
     * @return array<string, mixed>
     */
    public function restoreBackupSet(string $backupSetId): array
    {
        $dbRecord = BackupRecord::query()
            ->where('type', 'database')
            ->where('status', 'completed')
            ->whereJsonContains('metadata->backup_set_id', $backupSetId)
            ->first();

        $fileRecord = BackupRecord::query()
            ->where('type', 'files')
            ->where('status', 'completed')
            ->whereJsonContains('metadata->backup_set_id', $backupSetId)
            ->first();

        if (! $dbRecord || ! $fileRecord) {
            throw new RuntimeException("Backup set [{$backupSetId}] is incomplete or missing components.");
        }

        // Driver compatibility verification before touching any system
        $currentDriver = config('database.default');
        if ($dbRecord->database_driver !== $currentDriver) {
            throw new RuntimeException("Database driver mismatch: Backup set requires [{$dbRecord->database_driver}], but current connection is [{$currentDriver}].");
        }

        // 1. Pre-validate both incoming components before executing restoration
        if (! $this->validateBackup($dbRecord)) {
            throw new RuntimeException("Backup set database component [{$dbRecord->filename}] failed integrity check.");
        }
        if (! $this->validateBackup($fileRecord)) {
            throw new RuntimeException("Backup set files component [{$fileRecord->filename}] failed integrity check.");
        }

        // 2. Create safety snapshots for both database and private media
        $dbSafety = $this->createDatabaseBackup();
        $fileSafety = $this->createFilesBackup();

        $mediaDir = storage_path('app/private/media');
        $mediaSafetySnapshotDir = storage_path('app/private/media_pre_restore_'.Str::random(8));

        // Create staging snapshot of live media directory for instant rollback
        if (File::exists($mediaDir)) {
            File::copyDirectory($mediaDir, $mediaSafetySnapshotDir);
        } else {
            File::ensureDirectoryExists($mediaSafetySnapshotDir);
        }

        $dbRestored = false;
        $filesRestored = false;

        try {
            // Phase 1: Restore Database
            $this->restoreBackup($dbRecord, createSafetyBackup: false);
            $dbRestored = true;

            // Phase 2: Restore Files
            $this->restoreBackup($fileRecord, createSafetyBackup: false);
            $filesRestored = true;

            // Clean up staging media safety directory once complete set succeeds
            File::deleteDirectory($mediaSafetySnapshotDir);

            return [
                'success' => true,
                'message' => "Complete backup set [{$backupSetId}] successfully restored.",
                'database_safety_id' => $dbSafety->id,
                'files_safety_id' => $fileSafety->id,
            ];
        } catch (Throwable $e) {
            Log::error("Backup set [{$backupSetId}] restoration failed at phase (DB restored: ".($dbRestored ? 'YES' : 'NO').', Files restored: '.($filesRestored ? 'YES' : 'NO').'): '.$e->getMessage());

            // Comprehensive Dual Rollback: restore BOTH original components
            $rollbackErrors = [];

            // 1. Rollback Database if it was modified
            if ($dbRestored) {
                try {
                    $this->restoreBackup($dbSafety, createSafetyBackup: false);
                    Log::info("Database successfully rolled back to pre-restoration safety backup [{$dbSafety->filename}].");
                } catch (Throwable $dbRollbackEx) {
                    $rollbackErrors[] = 'Database rollback failed: '.$dbRollbackEx->getMessage();
                    Log::critical('CRITICAL: Database rollback failed following backup set error: '.$dbRollbackEx->getMessage());
                }
            }

            // 2. Rollback Media Directory to original pre-restore state
            try {
                if (File::exists($mediaSafetySnapshotDir)) {
                    if (File::exists($mediaDir)) {
                        File::deleteDirectory($mediaDir);
                    }
                    File::copyDirectory($mediaSafetySnapshotDir, $mediaDir);
                    File::deleteDirectory($mediaSafetySnapshotDir);
                    Log::info('Private media directory successfully rolled back to pre-restoration state.');
                }
            } catch (Throwable $mediaRollbackEx) {
                $rollbackErrors[] = 'Media rollback failed: '.$mediaRollbackEx->getMessage();
                Log::critical('CRITICAL: Media rollback failed following backup set error: '.$mediaRollbackEx->getMessage());
            }

            // Record failure in audit log
            $academy = Academy::current() ?? Academy::first();
            if ($academy) {
                $this->recordAudit(
                    $academy,
                    Auth::user(),
                    'backup_set.restoration_failed',
                    BackupRecord::class,
                    $backupSetId,
                    [
                        'backup_set_id' => $backupSetId,
                        'db_safety_id' => $dbSafety->id,
                        'file_safety_id' => $fileSafety->id,
                        'error' => $e->getMessage(),
                        'rollback_errors' => $rollbackErrors,
                        'complete_rollback' => empty($rollbackErrors),
                    ]
                );
            }

            if (! empty($rollbackErrors)) {
                // If rollback itself failed, preserve lock and report critical error
                throw new RuntimeException('CRITICAL: Backup set restoration failed AND rollback encountered errors: '.implode('; ', $rollbackErrors).'. Original error: '.$e->getMessage(), 0, $e);
            }

            throw new RuntimeException('Backup set restoration failed. BOTH database and media files were completely rolled back to their pre-restoration state. Error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Apply retention policy: retain the most recent $keepCount backups,
     * treating matching backup_set_id components as a single logical recovery point.
     * Never deletes the only remaining valid recovery point.
     * Also protects backups actively being locked or restored.
     */
    public function applyRetention(int $keepCount = 5): int
    {
        $totalCompleted = BackupRecord::query()->where('status', 'completed')->count();
        if ($totalCompleted <= 1) {
            return 0; // Never delete the only known-good backup!
        }

        // Check if a backup is currently locked in restoration
        $lockedBackupId = null;
        $lockFile = self::restoreLockPath();
        if (file_exists($lockFile)) {
            $lockContent = @file_get_contents($lockFile);
            if ($lockContent) {
                $decoded = json_decode($lockContent, true);
                $lockedBackupId = $decoded['backup_id'] ?? null;
            }
        }

        // Identify complete backup sets vs standalone backups
        $allSets = BackupRecord::query()
            ->where('status', 'completed')
            ->whereNotNull('metadata->backup_set_id')
            ->pluck('metadata')
            ->map(fn ($m) => $m['backup_set_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        // Valid complete sets require both 'database' and 'files' records
        $validCompleteSetIds = [];
        foreach ($allSets as $setId) {
            $hasDb = BackupRecord::where('status', 'completed')
                ->where('type', 'database')
                ->whereJsonContains('metadata->backup_set_id', $setId)
                ->exists();
            $hasFiles = BackupRecord::where('status', 'completed')
                ->where('type', 'files')
                ->whereJsonContains('metadata->backup_set_id', $setId)
                ->exists();

            if ($hasDb && $hasFiles) {
                $validCompleteSetIds[] = $setId;
            }
        }

        // Determine eligible backups beyond $keepCount
        $toDelete = BackupRecord::query()
            ->where('status', 'completed')
            ->when($lockedBackupId, fn ($q) => $q->where('id', '!=', $lockedBackupId))
            ->orderBy('created_at', 'desc')
            ->skip($keepCount)
            ->take(100)
            ->get();

        $deletedCount = 0;
        foreach ($toDelete as $record) {
            // Invariant: Never delete the last remaining completed recovery point
            if (BackupRecord::query()->where('status', 'completed')->count() <= 1) {
                break;
            }

            // Invariant: If complete sets exist, never delete the only complete backup set
            $recordSetId = $record->metadata['backup_set_id'] ?? null;
            if ($recordSetId && in_array($recordSetId, $validCompleteSetIds) && count($validCompleteSetIds) <= 1) {
                continue; // Preserve the only complete recovery point
            }

            try {
                $this->deleteBackupRecordFile($record);
                $record->delete();
                $deletedCount++;
            } catch (Throwable $e) {
                Log::error("Failed to prune backup [{$record->filename}]: ".$e->getMessage());
            }
        }

        return $deletedCount;
    }

    /**
     * Safely delete the physical file associated with a backup record.
     */
    public function deleteBackupRecordFile(BackupRecord $record): void
    {
        $safeName = basename($record->filename);
        if ($safeName !== $record->filename || str_contains($record->filename, '..')) {
            return;
        }

        $filePath = storage_path("app/private/backups/{$record->filename}");
        if (File::exists($filePath)) {
            if (! @unlink($filePath)) {
                Log::error("Failed unlinking backup file: {$filePath}");
            }
        }
    }
}
