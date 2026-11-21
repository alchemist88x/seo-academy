<?php

declare(strict_types=1);

namespace Tests\Feature\Student;

use App\Domain\Students\Models\Enrollment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class HeartbeatConcurrencyTest extends TestCase
{
    private string $phpBinary = 'C:\\laragon\\bin\\php\\php-8.3.36-Win32-vs16-x64\\php.exe';

    private string $workerScript;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.connections.mysql.database' => 'seo_academy_test',
        ]);
        DB::purge('mysql');
        $this->workerScript = base_path('scratch/heartbeat_concurrency_worker.php');
    }

    /**
     * Helper to spawn concurrent workers and release them at an atomic barrier.
     *
     * @param  array<string, array<string, mixed>>  $workerConfigs
     * @return array<string, array<string, mixed>>
     */
    private function runConcurrentWorkers(array $workerConfigs, string $barrierDir): array
    {
        if (! File::exists($barrierDir)) {
            File::makeDirectory($barrierDir, 0755, true);
        }

        /** @var array<string, Process> $processes */
        $processes = [];

        foreach ($workerConfigs as $workerId => $config) {
            $cmd = [
                $this->phpBinary,
                $this->workerScript,
                "--connection={$config['connection']}",
                "--database={$config['database']}",
                "--barrier-dir={$barrierDir}",
                "--worker-id={$workerId}",
                "--action={$config['action']}",
                "--enrollment-id={$config['enrollment_id']}",
                "--lesson-id={$config['lesson_id']}",
                "--user-id={$config['user_id']}",
                "--position={$config['position']}",
            ];

            $proc = new Process($cmd, base_path(), null, null, 30.0);
            $proc->start();
            $processes[$workerId] = $proc;
        }

        // Wait until all workers write their .ready file
        $startWait = microtime(true);
        while (true) {
            $readyCount = 0;
            foreach (array_keys($workerConfigs) as $wId) {
                if (File::exists($barrierDir.DIRECTORY_SEPARATOR.$wId.'.ready')) {
                    $readyCount++;
                }
            }

            if ($readyCount === count($workerConfigs)) {
                break;
            }

            if ((microtime(true) - $startWait) > 15.0) {
                throw new \RuntimeException("Timed out waiting for all workers to reach ready barrier. Ready: {$readyCount}/".count($workerConfigs));
            }

            usleep(2000);
        }

        // Release the barrier atomically
        touch($barrierDir.DIRECTORY_SEPARATOR.'go.signal');

        // Collect results
        $results = [];
        foreach ($processes as $workerId => $proc) {
            $proc->wait();
            $output = trim($proc->getOutput());
            $json = json_decode($output, true);

            $results[$workerId] = [
                'exit_code' => $proc->getExitCode(),
                'json' => $json,
                'raw_output' => $output,
                'error_output' => $proc->getErrorOutput(),
            ];
        }

        File::deleteDirectory($barrierDir);

        return $results;
    }

    private function createHarness(string $connection = 'mysql'): array
    {
        $academyId = (string) Str::uuid();
        $courseId = (string) Str::uuid();
        $moduleId = (string) Str::uuid();
        $lessonId = (string) Str::uuid();
        $userIdA = (string) Str::uuid();
        $userIdB = (string) Str::uuid();
        $profileIdA = (string) Str::uuid();
        $profileIdB = (string) Str::uuid();
        $enrollmentIdA = (string) Str::uuid();
        $enrollmentIdB = (string) Str::uuid();

        $conn = DB::connection($connection);

        $conn->table('academies')->insert([
            'id' => $academyId,
            'name' => 'Concurrency Academy',
            'slug' => 'concurrency-'.Str::random(6),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $conn->table('courses')->insert([
            'id' => $courseId,
            'academy_id' => $academyId,
            'title' => 'Concurrent Learning Course',
            'slug' => 'concurrent-course-'.Str::random(6),
            'status' => 'Published',
            'visibility' => 'public',
            'difficulty' => 'intermediate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $conn->table('modules')->insert([
            'id' => $moduleId,
            'academy_id' => $academyId,
            'course_id' => $courseId,
            'title' => 'Concurrency Module',
            'display_order' => 1,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $conn->table('lessons')->insert([
            'id' => $lessonId,
            'academy_id' => $academyId,
            'module_id' => $moduleId,
            'title' => 'Concurrency Video Lesson',
            'type' => 'video',
            'duration_seconds' => 600,
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([[$userIdA, $profileIdA, $enrollmentIdA, 'student_'.$userIdA.'@example.com'], [$userIdB, $profileIdB, $enrollmentIdB, 'student_'.$userIdB.'@example.com']] as [$uId, $pId, $eId, $email]) {
            $conn->table('users')->insert([
                'id' => $uId,
                'name' => 'Test Student '.$uId,
                'email' => $email,
                'password' => bcrypt('password'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conn->table('student_profiles')->insert([
                'id' => $pId,
                'academy_id' => $academyId,
                'user_id' => $uId,
                'student_code' => 'STU-'.Str::upper(Str::random(6)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conn->table('enrollments')->insert([
                'id' => $eId,
                'academy_id' => $academyId,
                'student_profile_id' => $pId,
                'course_id' => $courseId,
                'status' => 'active',
                'enrolled_at' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'academy_id' => $academyId,
            'course_id' => $courseId,
            'lesson_id' => $lessonId,
            'student_a' => ['user_id' => $userIdA, 'enrollment_id' => $enrollmentIdA],
            'student_b' => ['user_id' => $userIdB, 'enrollment_id' => $enrollmentIdB],
        ];
    }

    /**
     * Requirement 2A: Multiple simultaneous heartbeat requests on MySQL 8.4 InnoDB.
     * Verifies row locking, atomic updates, and zero duplicate lesson_progress records.
     */
    public function test_concurrent_heartbeats_on_same_enrollment_and_lesson_no_duplicates_mysql(): void
    {
        $harness = $this->createHarness('mysql');
        $enrollmentId = $harness['student_a']['enrollment_id'];
        $lessonId = $harness['lesson_id'];
        $userId = $harness['student_a']['user_id'];

        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hb_barr_mysql_'.uniqid();

        // 4 concurrent workers with staggered playback positions
        $workerConfigs = [
            'w1' => [
                'connection' => 'mysql',
                'database' => 'seo_academy_test',
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'user_id' => $userId,
                'position' => '30.5',
            ],
            'w2' => [
                'connection' => 'mysql',
                'database' => 'seo_academy_test',
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'user_id' => $userId,
                'position' => '45.0',
            ],
            'w3' => [
                'connection' => 'mysql',
                'database' => 'seo_academy_test',
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'user_id' => $userId,
                'position' => '60.0',
            ],
            'w4' => [
                'connection' => 'mysql',
                'database' => 'seo_academy_test',
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'user_id' => $userId,
                'position' => '75.2',
            ],
        ];

        $results = $this->runConcurrentWorkers($workerConfigs, $barrierDir);

        // Verify all 4 processes ran with unique PIDs
        $pids = [];
        foreach ($results as $wId => $res) {
            $this->assertSame(0, $res['exit_code'], "Worker {$wId} failed: {$res['error_output']} {$res['raw_output']}");
            $this->assertSame('success', $res['json']['status']);
            $this->assertNotNull($res['json']['pid']);
            $pids[] = $res['json']['pid'];
        }
        $this->assertCount(4, array_unique($pids), 'All 4 workers must execute in distinct OS processes');

        // CRITICAL CONCURRENCY INVARIANT: Exactly 1 lesson_progress record exists in MySQL
        $progressRecords = DB::connection('mysql')->table('lesson_progress')
            ->where('enrollment_id', $enrollmentId)
            ->where('lesson_id', $lessonId)
            ->get();

        $this->assertCount(1, $progressRecords, 'Concurrency assertion failed: duplicate lesson_progress records detected in MySQL!');

        $record = $progressRecords->first();
        $this->assertSame('in_progress', $record->status);
        $this->assertGreaterThanOrEqual(30.0, (float) $record->last_position_seconds);
        $this->assertLessThanOrEqual(76.0, (float) $record->last_position_seconds);
    }

    /**
     * Requirement 2B: Cross-student progress modification rejection.
     * Student B attempting to submit heartbeat for Student A's enrollment must be rejected.
     */
    public function test_cross_student_heartbeat_modification_rejected(): void
    {
        $harness = $this->createHarness('mysql');
        $enrollmentIdA = $harness['student_a']['enrollment_id'];
        $lessonId = $harness['lesson_id'];
        $userIdB = $harness['student_b']['user_id'];

        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hb_barr_cross_'.uniqid();

        $workerConfigs = [
            'attacker_worker' => [
                'connection' => 'mysql',
                'database' => 'seo_academy_test',
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentIdA,
                'lesson_id' => $lessonId,
                'user_id' => $userIdB, // Student B attempting to mutate Student A
                'position' => '120.0',
            ],
        ];

        $results = $this->runConcurrentWorkers($workerConfigs, $barrierDir);

        // Process must fail and report AuthorizationException
        $this->assertSame(1, $results['attacker_worker']['exit_code']);
        $this->assertSame('error', $results['attacker_worker']['json']['status']);
        $this->assertSame(AuthorizationException::class, $results['attacker_worker']['json']['error_class']);

        // Verify Student A's progress was not created or modified
        $progressA = DB::connection('mysql')->table('lesson_progress')
            ->where('enrollment_id', $enrollmentIdA)
            ->where('lesson_id', $lessonId)
            ->first();

        $this->assertNull($progressA, "Student A's progress must remain unaffected by unauthorized student heartbeat attempts");
    }

    /**
     * Requirement 2C: Non-finite and invalid playback positions.
     * Verifies that NaN, INF, and negative values do not corrupt database or crash queries.
     */
    public function test_non_finite_and_invalid_playback_positions_sanitized_mysql(): void
    {
        $harness = $this->createHarness('mysql');
        $enrollmentId = $harness['student_a']['enrollment_id'];
        $lessonId = $harness['lesson_id'];
        $userId = $harness['student_a']['user_id'];

        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hb_barr_sanit_'.uniqid();

        // 1. Send initial valid position
        $worker1 = [
            'init_worker' => [
                'connection' => 'mysql',
                'database' => 'seo_academy_test',
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'user_id' => $userId,
                'position' => '85.0',
            ],
        ];
        $this->runConcurrentWorkers($worker1, $barrierDir.'_1');

        // 2. Concurrently attempt invalid positions (NaN, INF, negative)
        $invalidConfigs = [
            'nan_worker' => [
                'connection' => 'mysql',
                'database' => 'seo_academy_test',
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'user_id' => $userId,
                'position' => 'NaN',
            ],
            'inf_worker' => [
                'connection' => 'mysql',
                'database' => 'seo_academy_test',
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'user_id' => $userId,
                'position' => 'INF',
            ],
            'neg_worker' => [
                'connection' => 'mysql',
                'database' => 'seo_academy_test',
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'user_id' => $userId,
                'position' => '-99.0',
            ],
        ];

        $results = $this->runConcurrentWorkers($invalidConfigs, $barrierDir.'_2');

        foreach ($results as $wId => $res) {
            $this->assertSame(0, $res['exit_code'], "Worker {$wId} should succeed without SQL crash");
        }

        // Verify database position was not corrupted to negative or invalid float
        $record = DB::connection('mysql')->table('lesson_progress')
            ->where('enrollment_id', $enrollmentId)
            ->where('lesson_id', $lessonId)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame(85.0, (float) $record->last_position_seconds, 'Invalid/non-finite positions must be sanitized and ignored');
    }

    /**
     * Requirement 2D: SQLite concurrent-write semantics and file-locking limitation analysis.
     */
    public function test_sqlite_concurrent_write_semantics_and_zero_duplicate_guarantee(): void
    {
        $tempSqlite = tempnam(sys_get_temp_dir(), 'seo_hb_sq_').'.sqlite';
        touch($tempSqlite);

        // Build schema in temp sqlite file
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $tempSqlite,
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);

        $harness = $this->createHarness('sqlite');
        $enrollmentId = $harness['student_a']['enrollment_id'];
        $lessonId = $harness['lesson_id'];
        $userId = $harness['student_a']['user_id'];

        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hb_barr_sq_'.uniqid();

        $workerConfigs = [
            'sq_w1' => [
                'connection' => 'sqlite',
                'database' => $tempSqlite,
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'user_id' => $userId,
                'position' => '20.0',
            ],
            'sq_w2' => [
                'connection' => 'sqlite',
                'database' => $tempSqlite,
                'action' => 'heartbeat',
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'user_id' => $userId,
                'position' => '40.0',
            ],
        ];

        $results = $this->runConcurrentWorkers($workerConfigs, $barrierDir);

        // Check outcomes: SQLite will serialize via busy_timeout or return database locked
        $atLeastOneSuccess = false;
        foreach ($results as $res) {
            if ($res['exit_code'] === 0 && ($res['json']['status'] ?? '') === 'success') {
                $atLeastOneSuccess = true;
            }
        }
        $this->assertTrue($atLeastOneSuccess, 'At least one worker must successfully write in SQLite');

        // Guarantee: exactly 1 progress record exists (never duplicated)
        $count = DB::connection('sqlite')->table('lesson_progress')
            ->where('enrollment_id', $enrollmentId)
            ->where('lesson_id', $lessonId)
            ->count();

        $this->assertSame(1, $count, 'SQLite unique index guarantees zero duplicate records even under concurrent attempts');

        @unlink($tempSqlite);
    }
}
