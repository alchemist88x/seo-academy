<?php

namespace Tests\Feature\Platform;

use App\Domain\Platform\Exceptions\IdempotencyConcurrentConflictException;
use App\Domain\Platform\Exceptions\IdempotencyPayloadMismatchException;
use App\Domain\Platform\Exceptions\IdempotencyStaleWorkerException;
use App\Domain\Platform\Services\IdempotencyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IdempotencyConcurrencyTest extends TestCase
{
    protected static bool $mysqlMigrated = false;

    protected string $sqliteTestDb;

    public static function engineProvider(): array
    {
        return [
            'sqlite' => ['sqlite'],
            'mysql' => ['mysql'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sqliteTestDb = storage_path('framework/testing/concurrency_test.sqlite');
        if (! File::exists(dirname($this->sqliteTestDb))) {
            File::makeDirectory(dirname($this->sqliteTestDb), 0755, true, true);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->sqliteTestDb)) {
            @unlink($this->sqliteTestDb);
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('mysql');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        parent::tearDown();
    }

    private function setupEngine(string $engine): void
    {
        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
        ]);
        app('cache')->forgetDriver();

        if ($engine === 'sqlite') {
            if (File::exists($this->sqliteTestDb)) {
                @unlink($this->sqliteTestDb);
            }
            touch($this->sqliteTestDb);

            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $this->sqliteTestDb,
                'database.connections.sqlite.foreign_key_constraints' => true,
            ]);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');
            Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
        } else {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => '127.0.0.1',
                'database.connections.mysql.port' => 3306,
                'database.connections.mysql.database' => 'seo_academy_test',
                'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => '',
            ]);
            DB::purge('mysql');
            DB::setDefaultConnection('mysql');

            if (! self::$mysqlMigrated) {
                Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
                self::$mysqlMigrated = true;
            } else {
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
                if (Schema::connection('mysql')->hasTable('idempotency_records')) {
                    DB::connection('mysql')->statement('TRUNCATE TABLE `idempotency_records`');
                }
                if (Schema::connection('mysql')->hasTable('users')) {
                    DB::connection('mysql')->statement('TRUNCATE TABLE `users`');
                }
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    private function runWorkerProcess(array $args): array
    {
        $phpBinary = 'C:\\laragon\\bin\\php\\php-8.3.36-Win32-vs16-x64\\php.exe';
        $scriptPath = base_path('scratch/idempotency_cli_worker.php');

        $cmdArgs = [$phpBinary, $scriptPath];
        foreach ($args as $k => $v) {
            $cmdArgs[] = "--{$k}=".escapeshellarg($v);
        }

        $command = implode(' ', $cmdArgs);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, base_path());
        if (! is_resource($process)) {
            return ['exit_code' => -1, 'stdout' => '', 'stderr' => 'Failed to start process'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exit_code' => $exitCode,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
            'json' => json_decode($stdout, true),
        ];
    }

    #[DataProvider('engineProvider')]
    public function test_concurrent_multi_process_race_condition_handling(string $engine): void
    {
        $this->setupEngine($engine);

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Concurrent Actor',
            'email' => 'concurrent-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $action = 'publish_grade';
        $key = 'race-key-'.Str::random(8);
        $resourceType = 'enrollment';
        $resourceId = (string) Str::uuid();
        $payload = json_encode(['score' => '85.50']);

        $baseArgs = [
            'engine' => $engine,
            'sqlite_path' => $this->sqliteTestDb,
            'actor_id' => $userId,
            'action' => $action,
            'key' => $key,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'payload' => $payload,
            'ttl' => '30',
        ];

        // Launch two worker processes concurrently
        $phpBinary = 'C:\\laragon\\bin\\php\\php-8.3.36-Win32-vs16-x64\\php.exe';
        $scriptPath = base_path('scratch/idempotency_cli_worker.php');

        $buildCmd = function (array $extra) use ($baseArgs, $phpBinary, $scriptPath) {
            $cmd = "{$phpBinary} {$scriptPath}";
            foreach (array_merge($baseArgs, $extra) as $k => $v) {
                $cmd .= " --{$k}=\"".addslashes($v).'"';
            }

            return $cmd;
        };

        $cmd1 = $buildCmd(['delay_ms' => '500', 'complete' => '1']);
        $cmd2 = $buildCmd(['delay_ms' => '0', 'complete' => '0']);

        $pipes1 = [];
        $pipes2 = [];
        $desc = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $p1 = proc_open($cmd1, $desc, $pipes1, base_path());
        // Start process 2 immediately
        $p2 = proc_open($cmd2, $desc, $pipes2, base_path());

        fclose($pipes1[0]);
        fclose($pipes2[0]);

        $out1 = stream_get_contents($pipes1[1]);
        $err1 = stream_get_contents($pipes1[2]);
        fclose($pipes1[1]);
        fclose($pipes1[2]);
        $code1 = proc_close($p1);

        $out2 = stream_get_contents($pipes2[1]);
        $err2 = stream_get_contents($pipes2[2]);
        fclose($pipes2[1]);
        fclose($pipes2[2]);
        $code2 = proc_close($p2);

        $json1 = json_decode(trim($out1), true);
        $json2 = json_decode(trim($out2), true);

        // Exactly one worker must win the reservation, and one must encounter a conflict
        $wins = 0;
        $conflicts = 0;

        if ($json1 && ($json1['success'] ?? false) === true) {
            $wins++;
        } elseif ($json1 && ($json1['error_type'] ?? '') === 'conflict') {
            $conflicts++;
        }

        if ($json2 && ($json2['success'] ?? false) === true) {
            $wins++;
        } elseif ($json2 && ($json2['error_type'] ?? '') === 'conflict') {
            $conflicts++;
        }

        $this->assertSame(1, $wins, "Exactly one worker must succeed in acquiring atomic reservation on [{$engine}]. Outputs: W1={$out1}, W2={$out2}");
        $this->assertSame(1, $conflicts, "Exactly one worker must be rejected with conflict on [{$engine}]. Outputs: W1={$out1}, W2={$out2}");
    }

    #[DataProvider('engineProvider')]
    public function test_expired_reservation_reclaiming_and_stale_worker_cas(string $engine): void
    {
        $this->setupEngine($engine);

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Expired Test Actor',
            'email' => 'expired-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new IdempotencyService;
        $action = 'publish_grade';
        $key = 'reclaim-key-'.Str::random(8);
        $resourceType = 'enrollment';
        $resourceId = (string) Str::uuid();
        $payload = ['score' => '90.00'];

        // 1. Worker 1 claims with short TTL of 1 second
        $claim1 = $service->claimReservation($userId, $action, $key, $resourceType, $resourceId, $payload, 1);
        $this->assertTrue($claim1['is_new']);
        $this->assertSame('reserved', $claim1['status']);
        $token1 = $claim1['ownership_token'];
        $recordId = $claim1['record_id'];

        // Wait 1.5 seconds for reservation to expire
        usleep(1500000);

        // 2. Worker 2 claims the same key after expiration -> successfully reclaims expired reservation
        $claim2 = $service->claimReservation($userId, $action, $key, $resourceType, $resourceId, $payload, 60);
        $this->assertTrue($claim2['is_new'], "Worker 2 must successfully reclaim expired reservation on [{$engine}].");
        $this->assertSame('reserved', $claim2['status']);
        $this->assertNotSame($token1, $claim2['ownership_token']);

        // 3. Worker 1 (stale worker) attempts to complete reservation with old token -> MUST BE REJECTED
        $staleWorkerRejected = false;
        try {
            $service->completeReservation($recordId, $token1, 200, ['data' => 'stale']);
        } catch (IdempotencyStaleWorkerException $e) {
            $staleWorkerRejected = true;
        }

        $this->assertTrue($staleWorkerRejected, "Stale worker CAS completion must be rejected on [{$engine}].");

        // 4. Worker 2 (active owner) successfully completes reservation
        $service->completeReservation($recordId, $claim2['ownership_token'], 200, ['data' => 'valid']);

        // 5. Subsequent request returns completed result
        $subsequent = $service->claimReservation($userId, $action, $key, $resourceType, $resourceId, $payload, 60);
        $this->assertFalse($subsequent['is_new']);
        $this->assertSame('completed', $subsequent['status']);
        $this->assertSame(200, $subsequent['response_code']);
    }

    #[DataProvider('engineProvider')]
    public function test_concurrent_operations_against_same_enrollment(string $engine): void
    {
        $this->setupEngine($engine);

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Enrollment Actor',
            'email' => 'enrollment-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollmentId = (string) Str::uuid();
        $action = 'calculate_final_grade';
        $key = 'enrollment-key-'.Str::random(8);
        $payload = ['score' => '94.50', 'academic_status' => 'passed'];

        $service = new IdempotencyService;

        // Worker 1 claims operation for enrollment
        $claim1 = $service->claimReservation($userId, $action, $key, 'enrollment', $enrollmentId, $payload, 30);
        $this->assertTrue($claim1['is_new']);
        $this->assertSame('reserved', $claim1['status']);

        // Worker 2 attempts concurrent claim for same enrollment and action
        $conflictDetected = false;
        try {
            $service->claimReservation($userId, $action, $key, 'enrollment', $enrollmentId, $payload, 30);
        } catch (IdempotencyConcurrentConflictException $e) {
            $conflictDetected = true;
        }
        $this->assertTrue($conflictDetected, "Concurrent reservation against same enrollment must throw conflict exception on [{$engine}].");

        // Worker 1 completes the enrollment grade calculation
        $service->completeReservation(
            $claim1['record_id'],
            $claim1['ownership_token'],
            200,
            ['final_grade' => '94.50', 'status' => 'published']
        );

        // Completed response replay: replaying identical request returns cached result
        $replay = $service->claimReservation($userId, $action, $key, 'enrollment', $enrollmentId, $payload, 30);
        $this->assertFalse($replay['is_new']);
        $this->assertSame('completed', $replay['status']);
        $this->assertSame(200, $replay['response_code']);
        $this->assertEquals(['final_grade' => '94.50', 'status' => 'published'], $replay['response_body']);

        // Same-key payload mismatch: attempting to reuse key with modified payload must be rejected
        $mismatchDetected = false;
        try {
            $service->claimReservation($userId, $action, $key, 'enrollment', $enrollmentId, ['score' => '99.00'], 30);
        } catch (IdempotencyPayloadMismatchException $e) {
            $mismatchDetected = true;
        }
        $this->assertTrue($mismatchDetected, "Same-key payload mismatch against enrollment must be rejected on [{$engine}].");
    }
}
