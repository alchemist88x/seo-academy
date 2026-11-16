<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\Media;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthService
{
    public const SCHEDULER_SENTINEL_FILE = 'framework/schedule-sentinel';

    /**
     * Get application runtime status.
     *
     * @return array<string, mixed>
     */
    public function getApplicationHealth(): array
    {
        return [
            'app_name' => config('app.name', 'SEO Academy'),
            'app_version' => config('app.version', '5.4.0'),
            'laravel_version' => App::version(),
            'php_version' => PHP_VERSION,
            'environment' => App::environment(),
            'debug_mode' => (bool) config('app.debug'),
            'maintenance_mode' => App::isDownForMaintenance(),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone'),
        ];
    }

    /**
     * Get database connectivity and migration status.
     *
     * @return array<string, mixed>
     */
    public function getDatabaseHealth(): array
    {
        $driver = config('database.default');
        $connected = false;
        $latencyMs = null;
        $databaseName = null;
        $migrationsRan = 0;
        $error = null;

        try {
            $start = microtime(true);
            $conn = DB::connection();
            $conn->getPdo();
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            $connected = true;

            $databaseName = $conn->getDatabaseName();
            if ($driver === 'sqlite') {
                $databaseName = basename($databaseName);
            }

            if (Schema::hasTable('migrations')) {
                $migrationsRan = DB::table('migrations')->count();
            }
        } catch (Throwable $e) {
            $connected = false;
            $error = $e->getMessage();
        }

        return [
            'driver' => $driver,
            'connected' => $connected,
            'latency_ms' => $latencyMs,
            'database_name' => $databaseName,
            'migrations_ran' => $migrationsRan,
            'error' => $error,
        ];
    }

    /**
     * Get storage accessibility, writable status, and capacity.
     *
     * @return array<string, mixed>
     */
    public function getStorageHealth(): array
    {
        $storagePath = storage_path('app/private');
        $mediaPath = storage_path('app/private/media');
        $backupsPath = storage_path('app/private/backups');

        $isStorageWritable = is_writable($storagePath);
        $isMediaWritable = @file_exists($mediaPath) ? is_writable($mediaPath) : is_writable($storagePath);
        $isBackupsWritable = @file_exists($backupsPath) ? is_writable($backupsPath) : is_writable($storagePath);

        $totalManagedBytes = 0;
        $totalFiles = 0;

        try {
            if (Schema::hasTable('media')) {
                $totalManagedBytes = (int) Media::sum('size_bytes');
                $totalFiles = Media::count();
            }
        } catch (Throwable) {
        }

        $freeBytes = function_exists('disk_free_space') && @file_exists($storagePath)
            ? @disk_free_space($storagePath)
            : null;

        $totalDiskBytes = function_exists('disk_total_space') && @file_exists($storagePath)
            ? @disk_total_space($storagePath)
            : null;

        return [
            'default_disk' => config('filesystems.default'),
            'storage_writable' => $isStorageWritable,
            'media_writable' => $isMediaWritable,
            'backups_writable' => $isBackupsWritable,
            'total_managed_bytes' => $totalManagedBytes,
            'total_managed_files' => $totalFiles,
            'disk_free_bytes' => $freeBytes !== false ? $freeBytes : null,
            'disk_total_bytes' => $totalDiskBytes !== false ? $totalDiskBytes : null,
        ];
    }

    /**
     * Get queue operational status and job counts.
     *
     * @return array<string, mixed>
     */
    public function getQueueHealth(): array
    {
        $driver = config('queue.default');
        $pendingJobs = null;
        $failedJobs = null;
        $status = 'Configured';

        if ($driver === 'database') {
            try {
                if (Schema::hasTable('jobs')) {
                    $pendingJobs = DB::table('jobs')->count();
                }
                if (Schema::hasTable('failed_jobs')) {
                    $failedJobs = DB::table('failed_jobs')->count();
                }
                $status = 'Operational (Database queue)';
            } catch (Throwable) {
                $status = 'Database queue tables unavailable';
            }
        } elseif ($driver === 'sync') {
            $status = 'Synchronous execution (No background worker required)';
        } else {
            $status = "Driver [{$driver}] configured (Status depends on external daemon)";
        }

        return [
            'driver' => $driver,
            'status_label' => $status,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
        ];
    }

    /**
     * Get scheduler health based on tangible sentinel evidence.
     *
     * @return array<string, mixed>
     */
    public function getSchedulerHealth(): array
    {
        $sentinelPath = storage_path(self::SCHEDULER_SENTINEL_FILE);
        $lastRunAt = null;
        $status = 'Unable to determine';
        $isRunningRecently = false;

        if (File::exists($sentinelPath)) {
            $mtime = File::lastModified($sentinelPath);
            $lastRunAt = Carbon::createFromTimestamp($mtime);

            // If sentinel was touched in the last 70 minutes (assuming hourly cron)
            if ($lastRunAt->diffInMinutes(now()) <= 70) {
                $status = 'Operational (Active cron detected)';
                $isRunningRecently = true;
            } else {
                $status = 'Stale (Last cron executed over an hour ago)';
            }
        } else {
            $status = 'No execution recorded yet (Configure crontab on hosting)';
        }

        return [
            'status' => $status,
            'is_running_recently' => $isRunningRecently,
            'last_run_at' => $lastRunAt,
            'cron_command' => '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
        ];
    }

    /**
     * Record sentinel evidence that the scheduler executed.
     */
    public function touchSchedulerSentinel(): void
    {
        $sentinelPath = storage_path(self::SCHEDULER_SENTINEL_FILE);
        File::ensureDirectoryExists(dirname($sentinelPath));
        File::put($sentinelPath, (string) time());
    }

    /**
     * Verify required PHP extensions and core runtime configurations.
     *
     * @return array<string, mixed>
     */
    public function getPhpHealth(): array
    {
        $requiredExtensions = [
            'pdo' => 'PDO Data Objects',
            'mbstring' => 'Multibyte String',
            'openssl' => 'OpenSSL Cryptography',
            'tokenizer' => 'PHP Tokenizer',
            'xml' => 'XML Parser',
            'ctype' => 'Character Type Checking',
            'json' => 'JSON Parser & Encoder',
            'fileinfo' => 'MIME File Information',
            'curl' => 'cURL HTTP Client',
            'zip' => 'Zip Archive Compression',
        ];

        $extensionStatus = [];
        $allPassed = true;

        foreach ($requiredExtensions as $ext => $label) {
            $loaded = extension_loaded($ext);
            if (! $loaded) {
                $allPassed = false;
            }
            $extensionStatus[$ext] = [
                'name' => $label,
                'loaded' => $loaded,
            ];
        }

        // Database specific PDO driver check
        $dbDriver = config('database.default');
        $dbPdoExt = $dbDriver === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql';
        $dbPdoLoaded = extension_loaded($dbPdoExt);
        if (! $dbPdoLoaded) {
            $allPassed = false;
        }
        $extensionStatus[$dbPdoExt] = [
            'name' => "Database Driver ({$dbPdoExt})",
            'loaded' => $dbPdoLoaded,
        ];

        return [
            'all_extensions_passed' => $allPassed,
            'extensions' => $extensionStatus,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time').'s',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];
    }
}
