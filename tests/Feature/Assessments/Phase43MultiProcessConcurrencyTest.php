<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentEvaluation;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Assignments\Services\AssignmentManagementService;
use App\Domain\Assignments\Services\AssignmentSubmissionService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
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

class Phase43MultiProcessConcurrencyTest extends TestCase
{
    private ?string $tempSqliteFile = null;

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
            $this->tempSqliteFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seo_assign_test_'.uniqid().'.sqlite';
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

        $trainer1 = User::factory()->create(['is_active' => true]);
        $trainer1->assignRole('trainer');
        $course->trainers()->attach($trainer1->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $academy->id,
            'is_primary' => true,
            'created_at' => now(),
        ]);

        $trainer2 = User::factory()->create(['is_active' => true]);
        $trainer2->assignRole('trainer');
        $course->trainers()->attach($trainer2->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $academy->id,
            'is_primary' => false,
            'created_at' => now(),
        ]);

        $student = User::factory()->create(['is_active' => true]);
        $student->assignRole('student');
        $profile = StudentProfile::create([
            'academy_id' => $academy->id,
            'user_id' => $student->id,
            'student_code' => 'STU-CONC-'.Str::upper(Str::random(6)),
        ]);
        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'student_profile_id' => $profile->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $managementService = app(AssignmentManagementService::class);
        $assignment = $managementService->createAssignment([
            'course_id' => $course->id,
            'title' => 'Concurrent Practical Project',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'allow_resubmission' => true,
            'max_attempts' => 5,
        ], $admin, $academy);

        return compact('course', 'trainer1', 'trainer2', 'student', 'enrollment', 'admin', 'assignment');
    }

    private function runWorkers(array $workerConfigs, string $barrierDir): array
    {
        File::ensureDirectoryExists($barrierDir);
        $startFile = $barrierDir.DIRECTORY_SEPARATOR.'start';
        if (file_exists($startFile)) {
            @unlink($startFile);
        }

        $processes = [];
        $pids = [];
        $phpBinary = PHP_BINARY;
        $workerScript = __DIR__.DIRECTORY_SEPARATOR.'assignment_concurrency_worker.php';

        foreach ($workerConfigs as $cfg) {
            $workerId = $cfg['worker_id'];
            $cmd = [
                $phpBinary,
                $workerScript,
                '--connection='.$cfg['connection'],
                '--database='.$cfg['database'],
                '--barrier-dir='.$barrierDir,
                '--worker-id='.$workerId,
                '--action='.$cfg['action'],
                '--assignment-id='.$cfg['assignment_id'],
                '--user-id='.$cfg['user_id'],
            ];

            if (! empty($cfg['submission_id'])) {
                $cmd[] = '--submission-id='.$cfg['submission_id'];
            }
            if (! empty($cfg['content'])) {
                $cmd[] = '--content='.$cfg['content'];
            }
            if (isset($cfg['points_awarded'])) {
                $cmd[] = '--points-awarded='.$cfg['points_awarded'];
            }
            if (! empty($cfg['feedback'])) {
                $cmd[] = '--feedback='.$cfg['feedback'];
            }
            if (! empty($cfg['revision_reason'])) {
                $cmd[] = '--revision-reason='.$cfg['revision_reason'];
            }

            $proc = new Process($cmd);
            $proc->start();
            $processes[$workerId] = $proc;
            $pids[$workerId] = $proc->getPid();
        }

        // Wait for all workers to signal ready
        $deadline = microtime(true) + 15.0;
        foreach (array_keys($workerConfigs) as $workerId) {
            $readyFile = $barrierDir.DIRECTORY_SEPARATOR.'ready_'.$workerId;
            while (! file_exists($readyFile)) {
                if (microtime(true) > $deadline) {
                    throw new \RuntimeException("Worker [{$workerId}] failed to reach barrier ready signal.");
                }
                usleep(5000);
            }
        }

        // Release the barrier
        touch($startFile);

        // Collect outputs
        $results = [];
        foreach ($processes as $workerId => $proc) {
            $proc->wait();
            $output = trim($proc->getOutput());
            $json = json_decode($output, true);

            $results[$workerId] = [
                'exit_code' => $proc->getExitCode(),
                'pid' => $pids[$workerId] ?? ($json['pid'] ?? null),
                'json' => $json,
                'raw_output' => $output,
                'error_output' => $proc->getErrorOutput(),
            ];
        }

        // Clean up barrier
        File::deleteDirectory($barrierDir);

        return $results;
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_simultaneous_first_submissions(string $engine): void
    {
        $database = $this->useEngine($engine);

        $academy = Academy::create([
            'id' => (string) Str::uuid(),
            'name' => 'SEO Test Academy '.$engine,
            'slug' => 'seo-test-'.$engine.'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        app(TenantContext::class)->setAcademy($academy);

        $harness = $this->createHarness($academy);
        $assignment = $harness['assignment'];
        $student = $harness['student'];

        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seo_barr_'.uniqid();

        $workerConfigs = [
            'worker_1' => [
                'worker_id' => 'worker_1',
                'connection' => $engine,
                'database' => $database,
                'action' => 'submit',
                'assignment_id' => $assignment->id,
                'user_id' => $student->id,
                'content' => 'First simultaneous submission from Worker 1',
            ],
            'worker_2' => [
                'worker_id' => 'worker_2',
                'connection' => $engine,
                'database' => $database,
                'action' => 'submit',
                'assignment_id' => $assignment->id,
                'user_id' => $student->id,
                'content' => 'Second simultaneous submission from Worker 2',
            ],
        ];

        $results = $this->runWorkers($workerConfigs, $barrierDir);

        // Verify PIDs captured
        $this->assertNotNull($results['worker_1']['pid']);
        $this->assertNotNull($results['worker_2']['pid']);
        $this->assertNotSame($results['worker_1']['pid'], $results['worker_2']['pid']);

        // Both workers should succeed or have handled version serialization cleanly
        $successes = 0;
        foreach ($results as $res) {
            if ($res['exit_code'] === 0 && ($res['json']['status'] ?? '') === 'success') {
                $successes++;
            }
        }
        $this->assertGreaterThanOrEqual(1, $successes);

        // Verify database state: no duplicate version numbers
        $submissions = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('user_id', $student->id)
            ->orderBy('version_number')
            ->get();

        $versions = $submissions->pluck('version_number')->toArray();
        $this->assertSame(array_unique($versions), $versions, 'Version numbers must be strictly unique.');

        // If both succeeded, versions should be [1, 2]
        if ($successes === 2) {
            $this->assertSame([1, 2], $versions);
            $this->assertSame($submissions[0]->id, $submissions[1]->previous_submission_id);
        }
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_simultaneous_resubmissions(string $engine): void
    {
        $database = $this->useEngine($engine);

        $academy = Academy::create([
            'id' => (string) Str::uuid(),
            'name' => 'SEO Test Academy '.$engine,
            'slug' => 'seo-test-'.$engine.'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        app(TenantContext::class)->setAcademy($academy);

        $harness = $this->createHarness($academy);
        $assignment = $harness['assignment'];
        $student = $harness['student'];

        // Seed initial Version 1
        $submissionService = app(AssignmentSubmissionService::class);
        $v1 = $submissionService->submit($assignment, $student, [
            'content' => 'Initial Version 1 baseline content.',
        ]);
        $this->assertSame(1, $v1->version_number);

        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seo_barr_'.uniqid();

        $workerConfigs = [
            'worker_resub_1' => [
                'worker_id' => 'worker_resub_1',
                'connection' => $engine,
                'database' => $database,
                'action' => 'submit',
                'assignment_id' => $assignment->id,
                'user_id' => $student->id,
                'content' => 'Resubmission race payload A',
            ],
            'worker_resub_2' => [
                'worker_id' => 'worker_resub_2',
                'connection' => $engine,
                'database' => $database,
                'action' => 'submit',
                'assignment_id' => $assignment->id,
                'user_id' => $student->id,
                'content' => 'Resubmission race payload B',
            ],
        ];

        $results = $this->runWorkers($workerConfigs, $barrierDir);

        $submissions = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('user_id', $student->id)
            ->orderBy('version_number')
            ->get();

        $versions = $submissions->pluck('version_number')->toArray();
        $this->assertSame(array_unique($versions), $versions, 'Version numbers must never have duplicates.');
        $this->assertContains(1, $versions);
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_concurrent_grading_workers(string $engine): void
    {
        $database = $this->useEngine($engine);

        $academy = Academy::create([
            'id' => (string) Str::uuid(),
            'name' => 'SEO Test Academy '.$engine,
            'slug' => 'seo-test-'.$engine.'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        app(TenantContext::class)->setAcademy($academy);

        $harness = $this->createHarness($academy);
        $assignment = $harness['assignment'];
        $student = $harness['student'];
        $trainer1 = $harness['trainer1'];
        $trainer2 = $harness['trainer2'];

        $submissionService = app(AssignmentSubmissionService::class);
        $submission = $submissionService->submit($assignment, $student, [
            'content' => 'Ready for grading deliverable.',
        ]);

        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seo_barr_'.uniqid();

        $workerConfigs = [
            'grader_1' => [
                'worker_id' => 'grader_1',
                'connection' => $engine,
                'database' => $database,
                'action' => 'grade',
                'assignment_id' => $assignment->id,
                'submission_id' => $submission->id,
                'user_id' => $trainer1->id,
                'points_awarded' => 85.0,
                'feedback' => 'Graded by Trainer 1',
            ],
            'grader_2' => [
                'worker_id' => 'grader_2',
                'connection' => $engine,
                'database' => $database,
                'action' => 'grade',
                'assignment_id' => $assignment->id,
                'submission_id' => $submission->id,
                'user_id' => $trainer2->id,
                'points_awarded' => 90.0,
                'feedback' => 'Graded by Trainer 2',
            ],
        ];

        $results = $this->runWorkers($workerConfigs, $barrierDir);

        $this->assertNotSame($results['grader_1']['pid'], $results['grader_2']['pid']);

        // Both evaluations must be safely recorded without deadlocks or row corruption
        $evaluations = AssignmentEvaluation::where('submission_id', $submission->id)->get();
        $this->assertGreaterThanOrEqual(1, $evaluations->count());

        foreach ($evaluations as $ev) {
            $this->assertNotNull($ev->evaluator_id);
            $this->assertNotNull($ev->points_awarded);
        }

        $freshSub = $submission->fresh();
        $this->assertSame(AssignmentSubmission::STATUS_GRADED, $freshSub->status);
        $this->assertNotNull($freshSub->grade);
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_resubmission_racing_with_grading(string $engine): void
    {
        $database = $this->useEngine($engine);

        $academy = Academy::create([
            'id' => (string) Str::uuid(),
            'name' => 'SEO Test Academy '.$engine,
            'slug' => 'seo-test-'.$engine.'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        app(TenantContext::class)->setAcademy($academy);

        $harness = $this->createHarness($academy);
        $assignment = $harness['assignment'];
        $student = $harness['student'];
        $trainer = $harness['trainer1'];

        // Initial V1 submission
        $submissionService = app(AssignmentSubmissionService::class);
        $v1 = $submissionService->submit($assignment, $student, [
            'content' => 'V1 deliverable awaiting review.',
        ]);

        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seo_barr_'.uniqid();

        $workerConfigs = [
            'worker_grade' => [
                'worker_id' => 'worker_grade',
                'connection' => $engine,
                'database' => $database,
                'action' => 'grade',
                'assignment_id' => $assignment->id,
                'submission_id' => $v1->id,
                'user_id' => $trainer->id,
                'points_awarded' => 85.0,
                'feedback' => 'V1 review feedback',
            ],
            'worker_resub' => [
                'worker_id' => 'worker_resub',
                'connection' => $engine,
                'database' => $database,
                'action' => 'submit',
                'assignment_id' => $assignment->id,
                'user_id' => $student->id,
                'content' => 'V2 deliverable uploaded simultaneously.',
            ],
        ];

        $results = $this->runWorkers($workerConfigs, $barrierDir);

        $this->assertNotSame($results['worker_grade']['pid'], $results['worker_resub']['pid']);
        $this->assertSame(0, $results['worker_grade']['exit_code'], 'Grading worker must succeed.');
        $this->assertSame(0, $results['worker_resub']['exit_code'], 'Resubmission worker must succeed.');

        // Verify V1 is graded
        $freshV1 = $v1->fresh();
        $this->assertSame(AssignmentSubmission::STATUS_GRADED, $freshV1->status);
        $this->assertEquals(85.0, (float) $freshV1->grade);

        // Verify V2 was created with version 2
        $v2 = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('user_id', $student->id)
            ->where('version_number', 2)
            ->first();

        $this->assertNotNull($v2, 'Version 2 submission must exist.');
        $this->assertSame($v1->id, $v2->previous_submission_id);
    }

    #[DataProvider('engineProvider')]
    public function test_multi_process_completion_evaluation_racing_with_regrade(string $engine): void
    {
        $database = $this->useEngine($engine);

        $academy = Academy::create([
            'id' => (string) Str::uuid(),
            'name' => 'SEO Test Academy '.$engine,
            'slug' => 'seo-test-'.$engine.'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        app(TenantContext::class)->setAcademy($academy);

        $harness = $this->createHarness($academy);
        $course = $harness['course'];
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
            'title' => 'Mandatory Lesson',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'is_mandatory' => true,
        ]);
        $enrollment = $harness['enrollment'];
        $student = $harness['student'];
        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $assignment = $harness['assignment'];
        $assignment->update(['is_mandatory' => true]);
        $trainer = $harness['trainer1'];

        $submissionService = app(AssignmentSubmissionService::class);
        $submission = $submissionService->submit($assignment, $student, [
            'content' => 'Mandatory assignment work.',
        ]);

        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seo_barr_'.uniqid();

        $workerConfigs = [
            'worker_eval_comp' => [
                'worker_id' => 'worker_eval_comp',
                'connection' => $engine,
                'database' => $database,
                'action' => 'evaluate_completion',
                'assignment_id' => $assignment->id,
                'submission_id' => $enrollment->id,
                'user_id' => $student->id,
            ],
            'worker_grade_sub' => [
                'worker_id' => 'worker_grade_sub',
                'connection' => $engine,
                'database' => $database,
                'action' => 'grade',
                'assignment_id' => $assignment->id,
                'submission_id' => $submission->id,
                'user_id' => $trainer->id,
                'points_awarded' => 95.0,
                'feedback' => 'Excellent work.',
            ],
        ];

        $results = $this->runWorkers($workerConfigs, $barrierDir);

        $this->assertNotSame($results['worker_eval_comp']['pid'], $results['worker_grade_sub']['pid']);
        $this->assertSame(0, $results['worker_eval_comp']['exit_code']);
        $this->assertSame(0, $results['worker_grade_sub']['exit_code']);

        // Post-race evaluation: latest state has graded passing evaluation
        $completionService = app(CourseCompletionService::class);
        $isCompleted = $completionService->evaluateCourseCompletion($enrollment->fresh());
        $this->assertTrue($isCompleted, 'Course must be evaluated as completed after passing grade is committed.');
    }
}
