<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Models\AssessmentAttemptAnswer;
use App\Domain\Assessments\Services\AssessmentService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Quizzes\Models\QuizQuestion;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Phase422MultiProcessConcurrencyTest extends TestCase
{
    protected ?string $tempSqliteFile = null;

    public static function engineProvider(): array
    {
        return [
            'sqlite' => ['sqlite'],
            'mysql' => ['mysql'],
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if ($this->tempSqliteFile && file_exists($this->tempSqliteFile)) {
            @unlink($this->tempSqliteFile);
            $this->tempSqliteFile = null;
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

    private function useEngine(string $engine): string
    {
        config(['cache.default' => 'array', 'permission.cache.store' => 'array']);

        if ($engine === 'sqlite') {
            $this->tempSqliteFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seo_test_'.uniqid().'.sqlite';
            touch($this->tempSqliteFile);

            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $this->tempSqliteFile,
                'database.connections.sqlite.foreign_key_constraints' => true,
                'database.connections.sqlite.busy_timeout' => 10000,
            ]);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');
            $database = $this->tempSqliteFile;
        } else {
            $database = 'seo_academy_test';
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => '127.0.0.1',
                'database.connections.mysql.port' => 3306,
                'database.connections.mysql.database' => $database,
                'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => '',
            ]);
            DB::purge('mysql');
            DB::setDefaultConnection('mysql');
            try {
                DB::connection('mysql')->getPdo();
            } catch (\Throwable $e) {
                $this->markTestSkipped('MySQL connection not available: '.$e->getMessage());
            }
        }

        Artisan::call('migrate:fresh', ['--database' => $engine, '--force' => true]);
        $this->seed(RoleSeeder::class);

        return $database;
    }

    private function createHarness(Academy $academy): array
    {
        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'status' => 'Published',
        ]);

        $module = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Core Module',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);

        $lesson = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'Core Lesson',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'is_mandatory' => true,
        ]);

        $studentUser = User::factory()->create(['is_active' => true]);
        $studentUser->assignRole('student');
        $studentUser->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $studentProfile = StudentProfile::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'student_code' => 'STU-'.Str::upper(Str::random(6)),
        ]);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'student_profile_id' => $studentProfile->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Concurrency Exam',
            'status' => 'active',
            'published_at' => now()->subDay(),
            'is_required' => true,
            'passing_percentage' => 70,
            'attempt_limit' => 5,
            'time_limit_minutes' => 60,
            'grace_period_seconds' => 90,
            'is_scheduled_exam' => false,
        ]);

        $q1 = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'What is canonical URL?',
            'question_type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'options' => [
                ['key' => 'a', 'text' => 'Preferred master URL'],
                ['key' => 'b', 'text' => 'Deprecated URL'],
            ],
            'correct_answer' => 'a',
            'points' => 5,
            'display_order' => 1,
        ]);

        return [
            'academy' => $academy,
            'course' => $course,
            'student' => $studentUser,
            'enrollment' => $enrollment,
            'quiz' => $quiz,
            'q1' => $q1,
        ];
    }

    /**
     * Coordinate independent OS worker processes across an atomic barrier.
     *
     * @param  array<int, array>  $workerConfigs
     * @return array<int, array{pid: int, output: array, exit_code: int, raw: string}>
     */
    private function runConcurrentWorkers(array $workerConfigs, string $engine, string $database): array
    {
        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'barrier_'.uniqid();
        File::makeDirectory($barrierDir);

        $workerScript = __DIR__.DIRECTORY_SEPARATOR.'concurrency_worker.php';
        $processes = [];
        $pids = [];
        $expectedReady = [];

        foreach ($workerConfigs as $index => $cfg) {
            $workerId = 'w_'.$index.'_'.uniqid();
            $expectedReady[] = $barrierDir.DIRECTORY_SEPARATOR.'ready_'.$workerId;

            $cmd = [
                PHP_BINARY,
                $workerScript,
                '--connection='.$engine,
                '--database='.$database,
                '--barrier-dir='.$barrierDir,
                '--worker-id='.$workerId,
                '--action='.($cfg['action'] ?? 'save_answer'),
                '--attempt-id='.($cfg['attempt_id'] ?? ''),
            ];

            if (isset($cfg['question_id'])) {
                $cmd[] = '--question-id='.$cfg['question_id'];
            }
            if (isset($cfg['answer'])) {
                $cmd[] = '--answer='.$cfg['answer'];
            }
            if (isset($cfg['revision'])) {
                $cmd[] = '--revision='.$cfg['revision'];
            }
            if (isset($cfg['idempotency_key'])) {
                $cmd[] = '--idempotency-key='.$cfg['idempotency_key'];
            }
            if (isset($cfg['trigger'])) {
                $cmd[] = '--trigger='.$cfg['trigger'];
            }
            if (isset($cfg['user_id'])) {
                $cmd[] = '--user-id='.$cfg['user_id'];
            }
            if (isset($cfg['evaluator_id'])) {
                $cmd[] = '--evaluator-id='.$cfg['evaluator_id'];
            }
            if (isset($cfg['manual_scores'])) {
                $cmd[] = '--manual-scores='.json_encode($cfg['manual_scores']);
            }

            $proc = new Process($cmd);
            $proc->start();
            $processes[$workerId] = $proc;
            $pids[$workerId] = $proc->getPid();
        }

        // Wait until all processes reach the barrier
        $deadline = microtime(true) + 12.0;
        foreach ($expectedReady as $readyPath) {
            while (! file_exists($readyPath)) {
                if (microtime(true) > $deadline) {
                    File::deleteDirectory($barrierDir);
                    throw new \RuntimeException('Worker failed to reach concurrency barrier in time: '.$readyPath);
                }
                usleep(5000);
            }
        }

        // Trigger simultaneous start
        file_put_contents($barrierDir.DIRECTORY_SEPARATOR.'start', (string) time());

        // Await completion of all independent OS processes
        $results = [];
        foreach ($processes as $workerId => $proc) {
            $proc->wait();
            $raw = trim($proc->getOutput());
            $parsed = json_decode($raw, true) ?: [];
            $workerPid = $parsed['pid'] ?? $pids[$workerId];
            $results[] = [
                'pid' => $workerPid,
                'worker_id' => $workerId,
                'exit_code' => $proc->getExitCode(),
                'output' => $parsed,
                'raw' => $raw,
            ];
        }

        File::deleteDirectory($barrierDir);

        return $results;
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_same_revision_same_idempotency_key(string $engine): void
    {
        $database = $this->useEngine($engine);
        $academy = Academy::factory()->create();
        $harness = $this->createHarness($academy);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($harness['enrollment'], $harness['quiz'], $harness['student']);
        $q1Id = (string) $harness['q1']->id;
        $idempotencyKey = (string) Str::uuid();

        // Two separate OS processes replay identical revision 1 with identical idempotency key
        $workers = [
            [
                'action' => 'save_answer',
                'attempt_id' => $attempt->id,
                'question_id' => $q1Id,
                'answer' => 'a',
                'revision' => 1,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'action' => 'save_answer',
                'attempt_id' => $attempt->id,
                'question_id' => $q1Id,
                'answer' => 'a',
                'revision' => 1,
                'idempotency_key' => $idempotencyKey,
            ],
        ];

        $results = $this->runConcurrentWorkers($workers, $engine, $database);

        $this->assertCount(2, $results);
        $this->assertNotEquals($results[0]['pid'], $results[1]['pid'], 'Expected two distinct OS processes.');

        foreach ($results as $res) {
            $this->assertEquals(0, $res['exit_code'], 'Worker failed with: '.$res['raw']);
            $this->assertEquals('success', $res['output']['status']);
        }

        // Verify exactly 1 answer row created in database with revision 1
        $answers = AssessmentAttemptAnswer::where('attempt_id', $attempt->id)
            ->where('canonical_question_id', $q1Id)
            ->get();

        $this->assertCount(1, $answers);
        $this->assertEquals('a', $answers->first()->student_answer);
        $this->assertEquals(1, $answers->first()->revision);
        $this->assertEquals($idempotencyKey, $answers->first()->last_idempotency_key);
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_same_revision_conflicting_idempotency_keys(string $engine): void
    {
        $database = $this->useEngine($engine);
        $academy = Academy::factory()->create();
        $harness = $this->createHarness($academy);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($harness['enrollment'], $harness['quiz'], $harness['student']);
        $q1Id = (string) $harness['q1']->id;

        // Two separate OS processes attempt same revision 1 with DIFFERENT idempotency keys
        $workers = [
            [
                'action' => 'save_answer',
                'attempt_id' => $attempt->id,
                'question_id' => $q1Id,
                'answer' => 'val_worker_1',
                'revision' => 1,
                'idempotency_key' => (string) Str::uuid(),
            ],
            [
                'action' => 'save_answer',
                'attempt_id' => $attempt->id,
                'question_id' => $q1Id,
                'answer' => 'val_worker_2',
                'revision' => 1,
                'idempotency_key' => (string) Str::uuid(),
            ],
        ];

        $results = $this->runConcurrentWorkers($workers, $engine, $database);

        $this->assertCount(2, $results);
        $this->assertNotEquals($results[0]['pid'], $results[1]['pid']);

        $successes = array_filter($results, fn ($r) => ($r['output']['status'] ?? '') === 'success');
        $conflicts = array_filter($results, fn ($r) => ($r['output']['status'] ?? '') === 'error' && str_contains($r['output']['error_message'] ?? '', 'Conflicting revision'));

        // At most one process can succeed; the competing process must be rejected with conflicting revision
        $this->assertCount(1, $successes, 'Expected exactly one process to win initial revision reservation.');
        $this->assertCount(1, $conflicts, 'Expected conflicting worker to be rejected.');

        // Database must contain exactly 1 answer row matching winning process
        $answers = AssessmentAttemptAnswer::where('attempt_id', $attempt->id)
            ->where('canonical_question_id', $q1Id)
            ->get();

        $this->assertCount(1, $answers);
        $this->assertEquals(1, $answers->first()->revision);
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_different_revisions_ordering_and_stale_rejection(string $engine): void
    {
        $database = $this->useEngine($engine);
        $academy = Academy::factory()->create();
        $harness = $this->createHarness($academy);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($harness['enrollment'], $harness['quiz'], $harness['student']);
        $q1Id = (string) $harness['q1']->id;

        // Pre-populate revision 2
        $service->saveAttemptAnswer(
            attempt: $attempt,
            questionId: $q1Id,
            studentAnswer: 'rev_2_initial',
            revision: 2,
            idempotencyKey: (string) Str::uuid(),
            user: $harness['student']
        );

        // Process 1 sends higher revision 3 ('rev_3_winner'); Process 2 sends stale revision 1 ('rev_1_stale')
        $workers = [
            [
                'action' => 'save_answer',
                'attempt_id' => $attempt->id,
                'question_id' => $q1Id,
                'answer' => 'rev_3_winner',
                'revision' => 3,
                'idempotency_key' => (string) Str::uuid(),
            ],
            [
                'action' => 'save_answer',
                'attempt_id' => $attempt->id,
                'question_id' => $q1Id,
                'answer' => 'rev_1_stale',
                'revision' => 1,
                'idempotency_key' => (string) Str::uuid(),
            ],
        ];

        $results = $this->runConcurrentWorkers($workers, $engine, $database);

        $this->assertCount(2, $results);

        $rev3Result = collect($results)->first(fn ($r) => ($r['output']['data']['revision'] ?? null) === 3);
        $this->assertNotNull($rev3Result);
        $this->assertEquals('success', $rev3Result['output']['status']);

        $staleResult = collect($results)->first(fn ($r) => str_contains($r['output']['error_message'] ?? '', 'Stale answer revision'));
        $this->assertNotNull($staleResult, 'Stale revision must be rejected with DomainException.');

        // Verify DB preserved revision 3
        $answer = AssessmentAttemptAnswer::where('attempt_id', $attempt->id)
            ->where('canonical_question_id', $q1Id)
            ->first();

        $this->assertEquals(3, $answer->revision);
        $this->assertEquals('rev_3_winner', $answer->student_answer);
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_autosave_racing_against_finalization(string $engine): void
    {
        $database = $this->useEngine($engine);
        $academy = Academy::factory()->create();
        $harness = $this->createHarness($academy);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($harness['enrollment'], $harness['quiz'], $harness['student']);
        $q1Id = (string) $harness['q1']->id;

        // Worker 1 attempts to autosave answer; Worker 2 attempts to finalize attempt
        $workers = [
            [
                'action' => 'save_answer',
                'attempt_id' => $attempt->id,
                'question_id' => $q1Id,
                'answer' => 'a',
                'revision' => 1,
                'idempotency_key' => (string) Str::uuid(),
            ],
            [
                'action' => 'finalize_attempt',
                'attempt_id' => $attempt->id,
                'trigger' => 'student_submit',
            ],
        ];

        $results = $this->runConcurrentWorkers($workers, $engine, $database);

        $attempt->refresh();
        $this->assertNotEquals(AssessmentAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertTrue(in_array($attempt->status, [AssessmentAttempt::STATUS_SUBMITTED, AssessmentAttempt::STATUS_EVALUATED], true));

        // Invariant: no uncommitted or corrupted state; attempt is finalized cleanly
        $this->assertNotNull($attempt->submitted_at);
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_two_finalization_workers_idempotency(string $engine): void
    {
        $database = $this->useEngine($engine);
        $academy = Academy::factory()->create();
        $harness = $this->createHarness($academy);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($harness['enrollment'], $harness['quiz'], $harness['student']);

        // Persist an answer first
        $service->saveAttemptAnswer(
            attempt: $attempt,
            questionId: (string) $harness['q1']->id,
            studentAnswer: 'a',
            revision: 1,
            idempotencyKey: (string) Str::uuid(),
            user: $harness['student']
        );

        // Two independent processes attempt to finalize the same attempt simultaneously
        $workers = [
            [
                'action' => 'finalize_attempt',
                'attempt_id' => $attempt->id,
                'trigger' => 'student_submit',
            ],
            [
                'action' => 'finalize_attempt',
                'attempt_id' => $attempt->id,
                'trigger' => 'timeout_expired',
            ],
        ];

        $results = $this->runConcurrentWorkers($workers, $engine, $database);

        $this->assertCount(2, $results);
        $this->assertNotEquals($results[0]['pid'], $results[1]['pid']);

        // Both workers must return clean success (idempotent guard)
        foreach ($results as $res) {
            $this->assertEquals('success', $res['output']['status'], 'Finalizer failed: '.$res['raw']);
        }

        $attempt->refresh();
        $this->assertNotEquals(AssessmentAttempt::STATUS_IN_PROGRESS, $attempt->status);

        // Score must be evaluated exactly once
        $this->assertEquals(5.0, (float) $attempt->earned_points);
        $this->assertEquals(5.0, (float) $attempt->total_points);
        $this->assertEquals(100.0, (float) $attempt->score_percentage);
        $this->assertTrue($attempt->passed);
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_concurrent_grading_workers(string $engine): void
    {
        $database = $this->useEngine($engine);
        $academy = Academy::factory()->create();
        $harness = $this->createHarness($academy);

        $admin1 = User::factory()->create(['is_active' => true]);
        $admin1->assignRole('admin');
        $admin2 = User::factory()->create(['is_active' => true]);
        $admin2->assignRole('admin');

        $subjQ = QuizQuestion::create([
            'quiz_id' => $harness['quiz']->id,
            'question_text' => 'Essay question',
            'question_type' => QuizQuestion::TYPE_LONG_ANSWER,
            'points' => 10,
            'display_order' => 2,
        ]);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($harness['enrollment'], $harness['quiz'], $harness['student']);
        $submitted = $service->submitAttempt($attempt, [
            (string) $harness['q1']->id => 'a',
            (string) $subjQ->id => 'My answer',
        ], $harness['student']);

        $this->assertEquals(AssessmentAttempt::STATUS_SUBMITTED, $submitted->status);
        $this->assertTrue($submitted->requires_manual_grading);

        // Two independent admin processes grade the attempt concurrently
        $workers = [
            [
                'action' => 'grade_attempt',
                'attempt_id' => $submitted->id,
                'evaluator_id' => $admin1->id,
                'manual_scores' => [
                    (string) $subjQ->id => ['points' => 8, 'feedback' => 'Reviewer A'],
                ],
            ],
            [
                'action' => 'grade_attempt',
                'attempt_id' => $submitted->id,
                'evaluator_id' => $admin2->id,
                'manual_scores' => [
                    (string) $subjQ->id => ['points' => 9, 'feedback' => 'Reviewer B'],
                ],
            ],
        ];

        $results = $this->runConcurrentWorkers($workers, $engine, $database);

        $this->assertCount(2, $results);
        $this->assertNotEquals($results[0]['pid'], $results[1]['pid']);

        // Invariant: both workers succeed atomically without deadlock or crash
        foreach ($results as $res) {
            $this->assertEquals('success', $res['output']['status'], 'Grading worker failed: '.$res['raw']);
        }

        $submitted->refresh();
        $this->assertEquals(AssessmentAttempt::STATUS_EVALUATED, $submitted->status);
        $this->assertFalse($submitted->requires_manual_grading);
        $this->assertTrue($submitted->isPassed());
    }
}
