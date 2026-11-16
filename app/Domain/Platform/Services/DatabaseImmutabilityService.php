<?php

namespace App\Domain\Platform\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;
use Throwable;

class DatabaseImmutabilityService
{
    /**
     * Tables requiring database-level immutability (rejection of UPDATE and DELETE).
     */
    public const IMMUTABLE_TABLES = [
        'enrollment_final_grades',
        'grade_override_events',
        'assessment_result_publication_events',
        'enrollment_grade_lifecycle_events',
        'certificate_audit_events',
    ];

    /**
     * Test whether the target database connection supports trigger creation and execution.
     * Uses a permanent probe table with an installation-unique name and try/finally cleanup.
     */
    public function canInstallTriggers(?string $connection = null): bool
    {
        $conn = $connection ?? config('database.default', 'sqlite');
        $uuid = str_replace('-', '', (string) Str::uuid());
        $probeTable = "__preflight_probe_{$uuid}";
        $triggerName = "trig_probe_{$uuid}";

        try {
            $pdo = DB::connection($conn)->getPdo();
        } catch (Throwable $e) {
            Log::warning("ImmutabilityService: Unable to get PDO for connection [{$conn}]: ".$e->getMessage());

            return false;
        }

        $supported = false;

        try {
            $driver = DB::connection($conn)->getDriverName();

            if ($driver === 'sqlite') {
                $pdo->exec("CREATE TABLE {$probeTable} (id INTEGER PRIMARY KEY, val TEXT)");
                $pdo->exec("INSERT INTO {$probeTable} (id, val) VALUES (1, 'initial')");
                $pdo->exec("
                    CREATE TRIGGER {$triggerName} BEFORE UPDATE ON {$probeTable}
                    BEGIN
                        SELECT RAISE(ABORT, 'Test trigger aborted');
                    END
                ");
            } else {
                $pdo->exec("CREATE TABLE {$probeTable} (id INT PRIMARY KEY, val VARCHAR(50)) ENGINE=InnoDB");
                $pdo->exec("INSERT INTO {$probeTable} (id, val) VALUES (1, 'initial')");
                $pdo->exec("
                    CREATE TRIGGER {$triggerName} BEFORE UPDATE ON {$probeTable}
                    FOR EACH ROW
                    BEGIN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test trigger aborted';
                    END
                ");
            }

            // Test execution: update must be blocked by the trigger
            try {
                $pdo->exec("UPDATE {$probeTable} SET val = 'modified' WHERE id = 1");
                // If it reached here, update was not blocked
                $supported = false;
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'Test trigger aborted') || str_contains($e->getMessage(), '45000')) {
                    $supported = true;
                }
            }
        } catch (Throwable $e) {
            Log::info("ImmutabilityService: Trigger creation unsupported on connection [{$conn}]: ".$e->getMessage());
            $supported = false;
        } finally {
            try {
                $pdo->exec("DROP TRIGGER IF EXISTS {$triggerName}");
                $pdo->exec("DROP TABLE IF EXISTS {$probeTable}");
            } catch (Throwable) {
            }
        }

        return $supported;
    }

    /**
     * Install immutability triggers on all designated tables.
     * Returns array of installed triggers, or empty if unsupported.
     *
     * @return array<string, array<string, bool>>
     */
    public function installTriggers(?string $connection = null): array
    {
        $conn = $connection ?? config('database.default', 'sqlite');

        if (! $this->canInstallTriggers($conn)) {
            Log::warning("ImmutabilityService: Database triggers not installed on connection [{$conn}]. Falling back to Eloquent-level immutability.");

            return [];
        }

        $pdo = DB::connection($conn)->getPdo();
        $driver = DB::connection($conn)->getDriverName();
        $results = [];

        foreach (self::IMMUTABLE_TABLES as $table) {
            $results[$table] = [
                'update' => $this->installTableTrigger($pdo, $driver, $table, 'UPDATE'),
                'delete' => $this->installTableTrigger($pdo, $driver, $table, 'DELETE'),
            ];
        }

        return $results;
    }

    /**
     * Remove immutability triggers on all designated tables.
     */
    public function dropTriggers(?string $connection = null): void
    {
        $conn = $connection ?? config('database.default', 'sqlite');
        try {
            $pdo = DB::connection($conn)->getPdo();
        } catch (Throwable) {
            return;
        }

        foreach (self::IMMUTABLE_TABLES as $table) {
            $updateTrig = "trig_no_update_{$table}";
            $deleteTrig = "trig_no_delete_{$table}";
            try {
                $pdo->exec("DROP TRIGGER IF EXISTS {$updateTrig}");
                $pdo->exec("DROP TRIGGER IF EXISTS {$deleteTrig}");
            } catch (Throwable) {
            }
        }
    }

    protected function installTableTrigger(PDO $pdo, string $driver, string $table, string $event): bool
    {
        $eventLower = strtolower($event);
        $triggerName = "trig_no_{$eventLower}_{$table}";

        try {
            $pdo->exec("DROP TRIGGER IF EXISTS {$triggerName}");

            if ($driver === 'sqlite') {
                $sql = "
                    CREATE TRIGGER IF NOT EXISTS {$triggerName}
                    BEFORE {$event} ON {$table}
                    FOR EACH ROW
                    BEGIN
                        SELECT RAISE(ABORT, 'Immutable record: {$table} records cannot be {$eventLower}d');
                    END;
                ";
            } else {
                $sql = "
                    CREATE TRIGGER {$triggerName}
                    BEFORE {$event} ON {$table}
                    FOR EACH ROW
                    BEGIN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Immutable record: {$table} records cannot be {$eventLower}d';
                    END;
                ";
            }

            $pdo->exec($sql);

            return true;
        } catch (Throwable $e) {
            Log::warning("Failed to install {$event} trigger on table [{$table}]: ".$e->getMessage());

            return false;
        }
    }
}
