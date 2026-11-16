<?php

namespace Tests\Feature\Grading;

use App\Domain\Courses\Models\Course;
use App\Domain\Grading\Models\EnrollmentFinalGrade;
use App\Domain\Grading\Services\DecimalPrecisionEngine;
use App\Domain\Platform\Exceptions\IdempotencyConcurrentConflictException;
use App\Domain\Platform\Exceptions\IdempotencyPayloadMismatchException;
use App\Domain\Platform\Exceptions\IdempotencyStaleWorkerException;
use App\Domain\Platform\Services\DatabaseImmutabilityService;
use App\Domain\Platform\Services\IdempotencyService;
use App\Domain\Students\Models\Enrollment;
use DivisionByZeroError;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GradebookDatabaseFoundationTest extends TestCase
{
    protected static bool $mysqlMigrated = false;

    public static function engineProvider(): array
    {
        return [
            'sqlite' => ['sqlite'],
            'mysql' => ['mysql'],
        ];
    }

    protected function tearDown(): void
    {
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

    private function useEngine(string $engine): void
    {
        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
        ]);
        app('cache')->forgetDriver();

        if ($engine === 'sqlite') {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.foreign_key_constraints' => true,
            ]);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');
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
        }

        $this->assertSame($engine, DB::connection()->getDriverName());

        if ($engine === 'sqlite') {
            $this->assertSame(0, Artisan::call('migrate:fresh', ['--database' => $engine, '--force' => true]));
        } else {
            if (! self::$mysqlMigrated) {
                $this->assertSame(0, Artisan::call('migrate:fresh', ['--database' => $engine, '--force' => true]));
                self::$mysqlMigrated = true;
            } else {
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
                $tables = [
                    'enrollment_current_grades',
                    'enrollment_final_grades',
                    'enrollment_grade_drafts',
                    'assessment_weights',
                    'grading_categories',
                    'course_grading_schemes',
                    'enrollments',
                    'student_profiles',
                    'courses',
                    'academies',
                    'users',
                    'idempotency_records',
                    'certificate_audit_events',
                ];
                foreach ($tables as $t) {
                    if (Schema::connection('mysql')->hasTable($t)) {
                        DB::connection('mysql')->statement("TRUNCATE TABLE `{$t}`");
                    }
                }
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    #[DataProvider('engineProvider')]
    public function test_all_gradebook_tables_exist_after_migration(string $engine): void
    {
        $this->useEngine($engine);

        $tables = [
            'course_grading_schemes',
            'grading_categories',
            'assessment_weights',
            'enrollment_grade_drafts',
            'enrollment_final_grades',
            'enrollment_current_grades',
            'grade_override_events',
            'assessment_result_publication_events',
            'enrollment_grade_lifecycle_events',
            'idempotency_records',
            'certificate_audit_events',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::connection($engine)->hasTable($table), "Table [{$table}] must exist on engine [{$engine}]");
        }

        $this->assertTrue(Schema::connection($engine)->hasColumn('courses', 'active_grading_scheme_id'));
        $this->assertTrue(Schema::connection($engine)->hasColumn('certificates', 'requires_audit'));
        $this->assertTrue(Schema::connection($engine)->hasColumn('certificates', 'audited_at'));
        $this->assertTrue(Schema::connection($engine)->hasColumn('certificates', 'reinstated_at'));
    }

    #[DataProvider('engineProvider')]
    public function test_composite_fk_course_scheme_isolation(string $engine): void
    {
        $this->useEngine($engine);

        $academyId = (string) Str::uuid();
        DB::table('academies')->insert([
            'id' => $academyId,
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::random(5),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseAId = (string) Str::uuid();
        $courseBId = (string) Str::uuid();

        DB::table('courses')->insert([
            ['id' => $courseAId, 'academy_id' => $academyId, 'title' => 'Course A', 'slug' => 'course-a-'.Str::random(5), 'created_at' => now(), 'updated_at' => now()],
            ['id' => $courseBId, 'academy_id' => $academyId, 'title' => 'Course B', 'slug' => 'course-b-'.Str::random(5), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $schemeAId = (string) Str::uuid();
        $schemeBId = (string) Str::uuid();

        DB::table('course_grading_schemes')->insert([
            ['id' => $schemeAId, 'course_id' => $courseAId, 'version' => 1, 'name' => 'Scheme A', 'status' => 'active', 'passing_grade' => 70.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $schemeBId, 'course_id' => $courseBId, 'version' => 1, 'name' => 'Scheme B', 'status' => 'active', 'passing_grade' => 70.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 1. Valid assignment: Course A points to Scheme A (same course) -> SUCCESS
        DB::table('courses')->where('id', $courseAId)->update(['active_grading_scheme_id' => $schemeAId]);
        $this->assertSame($schemeAId, DB::table('courses')->where('id', $courseAId)->value('active_grading_scheme_id'));

        // 2. Cross-course violation: Attempting to assign Scheme B to Course A -> MUST FAIL
        $failed = false;
        try {
            DB::table('courses')->where('id', $courseAId)->update(['active_grading_scheme_id' => $schemeBId]);
        } catch (QueryException $e) {
            $failed = true;
        }

        $this->assertTrue($failed, "Database on [{$engine}] must reject assigning a scheme from Course B to Course A via composite FK.");
    }

    #[DataProvider('engineProvider')]
    public function test_composite_fk_enrollment_current_grade_isolation(string $engine): void
    {
        $this->useEngine($engine);

        $academyId = (string) Str::uuid();
        DB::table('academies')->insert([
            'id' => $academyId,
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::random(5),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Student User',
            'email' => 'student-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profileId = (string) Str::uuid();
        $profileBId = (string) Str::uuid();
        $userBId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $userBId,
            'name' => 'Student User B',
            'email' => 'student-b-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('student_profiles')->insert([
            ['id' => $profileId, 'user_id' => $userId, 'academy_id' => $academyId, 'student_code' => 'STU-'.Str::random(6), 'created_at' => now(), 'updated_at' => now()],
            ['id' => $profileBId, 'user_id' => $userBId, 'academy_id' => $academyId, 'student_code' => 'STU-'.Str::random(6), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $courseId = (string) Str::uuid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'academy_id' => $academyId,
            'title' => 'Course 101',
            'slug' => 'course-101-'.Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schemeId = (string) Str::uuid();
        DB::table('course_grading_schemes')->insert([
            'id' => $schemeId,
            'course_id' => $courseId,
            'version' => 1,
            'name' => 'Default Scheme',
            'status' => 'active',
            'passing_grade' => 70.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollmentAId = (string) Str::uuid();
        $enrollmentBId = (string) Str::uuid();

        DB::table('enrollments')->insert([
            ['id' => $enrollmentAId, 'academy_id' => $academyId, 'student_profile_id' => $profileId, 'course_id' => $courseId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $enrollmentBId, 'academy_id' => $academyId, 'student_profile_id' => $profileBId, 'course_id' => $courseId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $finalGradeAId = (string) Str::uuid();
        $finalGradeBId = (string) Str::uuid();

        DB::table('enrollment_final_grades')->insert([
            [
                'id' => $finalGradeAId,
                'enrollment_id' => $enrollmentAId,
                'grading_scheme_id' => $schemeId,
                'version' => 1,
                'final_score' => '88.50',
                'letter_grade' => 'B+',
                'is_passing' => 1,
                'academic_status' => 'passed',
                'calculation_snapshot' => json_encode(['score' => 88.5]),
                'published_at' => now(),
                'published_by' => $userId,
                'created_at' => now(),
            ],
            [
                'id' => $finalGradeBId,
                'enrollment_id' => $enrollmentBId,
                'grading_scheme_id' => $schemeId,
                'version' => 1,
                'final_score' => '92.00',
                'letter_grade' => 'A',
                'is_passing' => 1,
                'academic_status' => 'passed',
                'calculation_snapshot' => json_encode(['score' => 92.0]),
                'published_at' => now(),
                'published_by' => $userId,
                'created_at' => now(),
            ],
        ]);

        // 1. Valid pointer: Enrollment A points to Final Grade A
        DB::table('enrollment_current_grades')->insert([
            'id' => (string) Str::uuid(),
            'enrollment_id' => $enrollmentAId,
            'published_final_grade_id' => $finalGradeAId,
            'status' => 'published',
            'lock_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame($finalGradeAId, DB::table('enrollment_current_grades')->where('enrollment_id', $enrollmentAId)->value('published_final_grade_id'));

        // 2. Cross-enrollment pointer violation: Attempting to point Enrollment A's current grade to Final Grade B
        $failed = false;
        try {
            DB::table('enrollment_current_grades')->where('enrollment_id', $enrollmentAId)->update([
                'published_final_grade_id' => $finalGradeBId,
            ]);
        } catch (QueryException $e) {
            $failed = true;
        }

        $this->assertTrue($failed, "Database on [{$engine}] must reject setting published_final_grade_id from another enrollment via composite FK.");
    }

    #[DataProvider('engineProvider')]
    public function test_immutability_triggers_prevent_direct_sql_updates_and_deletes(string $engine): void
    {
        $this->useEngine($engine);

        $immutabilityService = new DatabaseImmutabilityService;
        if (! $immutabilityService->canInstallTriggers($engine)) {
            $this->markTestSkipped("Triggers unsupported on [{$engine}] environment.");
        }

        $academyId = (string) Str::uuid();
        DB::table('academies')->insert([
            'id' => $academyId,
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::random(5),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Student User',
            'email' => 'student-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profileId = (string) Str::uuid();
        DB::table('student_profiles')->insert([
            'id' => $profileId,
            'user_id' => $userId,
            'academy_id' => $academyId,
            'student_code' => 'STU-'.Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = (string) Str::uuid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'academy_id' => $academyId,
            'title' => 'Course 101',
            'slug' => 'course-101-'.Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollmentId = (string) Str::uuid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'academy_id' => $academyId,
            'student_profile_id' => $profileId,
            'course_id' => $courseId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schemeId = (string) Str::uuid();
        DB::table('course_grading_schemes')->insert([
            'id' => $schemeId,
            'course_id' => $courseId,
            'version' => 1,
            'name' => 'Default Scheme',
            'status' => 'active',
            'passing_grade' => 70.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $finalGradeId = (string) Str::uuid();
        DB::table('enrollment_final_grades')->insert([
            'id' => $finalGradeId,
            'enrollment_id' => $enrollmentId,
            'grading_scheme_id' => $schemeId,
            'version' => 1,
            'final_score' => '85.00',
            'letter_grade' => 'B',
            'is_passing' => 1,
            'academic_status' => 'passed',
            'calculation_snapshot' => json_encode(['score' => 85]),
            'published_at' => now(),
            'published_by' => $userId,
            'created_at' => now(),
        ]);

        // Attempt direct SQL UPDATE on enrollment_final_grades -> MUST FAIL
        $updateBlocked = false;
        try {
            DB::table('enrollment_final_grades')->where('id', $finalGradeId)->update([
                'final_score' => '99.00',
            ]);
        } catch (QueryException $e) {
            $updateBlocked = true;
        }

        $this->assertTrue($updateBlocked, "Direct SQL UPDATE on enrollment_final_grades must be blocked by database trigger on [{$engine}].");

        // Attempt direct SQL DELETE on enrollment_final_grades -> MUST FAIL
        $deleteBlocked = false;
        try {
            DB::table('enrollment_final_grades')->where('id', $finalGradeId)->delete();
        } catch (QueryException $e) {
            $deleteBlocked = true;
        }

        $this->assertTrue($deleteBlocked, "Direct SQL DELETE on enrollment_final_grades must be blocked by database trigger on [{$engine}].");
    }

    public function test_eloquent_level_immutability_protection(): void
    {
        $this->useEngine('sqlite');

        $academyId = (string) Str::uuid();
        DB::table('academies')->insert([
            'id' => $academyId,
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::random(5),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Student User',
            'email' => 'student-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profileId = (string) Str::uuid();
        DB::table('student_profiles')->insert([
            'id' => $profileId,
            'user_id' => $userId,
            'academy_id' => $academyId,
            'student_code' => 'STU-'.Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = (string) Str::uuid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'academy_id' => $academyId,
            'title' => 'Course 101',
            'slug' => 'course-101-'.Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollmentId = (string) Str::uuid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'academy_id' => $academyId,
            'student_profile_id' => $profileId,
            'course_id' => $courseId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schemeId = (string) Str::uuid();
        DB::table('course_grading_schemes')->insert([
            'id' => $schemeId,
            'course_id' => $courseId,
            'version' => 1,
            'name' => 'Default Scheme',
            'status' => 'active',
            'passing_grade' => 70.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $finalGrade = EnrollmentFinalGrade::create([
            'enrollment_id' => $enrollmentId,
            'grading_scheme_id' => $schemeId,
            'version' => 1,
            'final_score' => '85.00',
            'letter_grade' => 'B',
            'is_passing' => true,
            'academic_status' => 'passed',
            'calculation_snapshot' => ['score' => 85],
            'published_at' => now(),
            'published_by' => $userId,
        ]);

        // Eloquent update must throw LogicException
        $updateBlocked = false;
        try {
            $finalGrade->update(['final_score' => '99.00']);
        } catch (LogicException $e) {
            $updateBlocked = true;
        }
        $this->assertTrue($updateBlocked, 'Eloquent update on immutable model must throw LogicException.');

        // Eloquent delete must throw LogicException
        $deleteBlocked = false;
        try {
            $finalGrade->delete();
        } catch (LogicException $e) {
            $deleteBlocked = true;
        }
        $this->assertTrue($deleteBlocked, 'Eloquent delete on immutable model must throw LogicException.');
    }

    public function test_decimal_precision_engine_calculations_and_rounding(): void
    {
        // Addition & subtraction
        $this->assertSame('15.5000', DecimalPrecisionEngine::add('10.25', '5.25', 4));
        $this->assertSame('5.0000', DecimalPrecisionEngine::sub('10.25', '5.25', 4));

        // Multiplication & division
        $this->assertSame('53.8125', DecimalPrecisionEngine::mul('10.25', '5.25', 4));
        $this->assertSame('2.0000', DecimalPrecisionEngine::div('10.00', '5.00', 4));

        // Division by zero
        $this->expectException(DivisionByZeroError::class);
        DecimalPrecisionEngine::div('10.00', '0.00');
    }

    public function test_decimal_precision_engine_half_up_rounding(): void
    {
        $this->assertSame('85.46', DecimalPrecisionEngine::roundHalfUp('85.455', 2));
        $this->assertSame('85.45', DecimalPrecisionEngine::roundHalfUp('85.454', 2));
        $this->assertSame('10.00', DecimalPrecisionEngine::roundHalfUp('9.995', 2));
        $this->assertSame('-1.24', DecimalPrecisionEngine::roundHalfUp('-1.235', 2));
        $this->assertSame('0.00', DecimalPrecisionEngine::roundHalfUp('-0.00', 2));
        $this->assertSame('100.00', DecimalPrecisionEngine::roundHalfUp('100', 2));

        // Percentage & weighted score
        $this->assertSame('85.4550', DecimalPrecisionEngine::percentage('85.455', '100', 4));
        $this->assertSame('85.46', DecimalPrecisionEngine::percentage('85.455', '100', 2));
        $this->assertSame('17.0910', DecimalPrecisionEngine::weightedScore('85.455', '20', 4));
        $this->assertSame('17.09', DecimalPrecisionEngine::weightedScore('85.455', '20', 2));

        // Bounds check
        $this->assertTrue(DecimalPrecisionEngine::boundsCheck('85.455', '0', '100'));
        $this->assertTrue(DecimalPrecisionEngine::boundsCheck('0', '0', '100'));
        $this->assertTrue(DecimalPrecisionEngine::boundsCheck('100', '0', '100'));
        $this->assertFalse(DecimalPrecisionEngine::boundsCheck('100.01', '0', '100'));
        $this->assertFalse(DecimalPrecisionEngine::boundsCheck('-0.01', '0', '100'));
    }

    public function test_idempotency_service_atomic_lifecycle_and_conflict_rejection(): void
    {
        $this->useEngine('sqlite');

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Instructor',
            'email' => 'instructor-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new IdempotencyService;
        $action = 'publish_grade';
        $key = 'grade-pub-key-101';
        $resourceType = 'enrollment';
        $resourceId = (string) Str::uuid();
        $payload1 = ['percentage' => '95.50', 'letter' => 'A'];
        $payload2 = ['percentage' => '85.00', 'letter' => 'B'];

        // 1. Initial atomic claim -> Success (is_new = true, status = reserved)
        $claim1 = $service->claimReservation($userId, $action, $key, $resourceType, $resourceId, $payload1, 60);
        $this->assertTrue($claim1['is_new']);
        $this->assertSame(IdempotencyService::STATUS_RESERVED, $claim1['status']);
        $this->assertNotEmpty($claim1['ownership_token']);

        // 2. Duplicate claim while reserved -> Concurrent conflict
        $this->expectException(IdempotencyConcurrentConflictException::class);
        $service->claimReservation($userId, $action, $key, $resourceType, $resourceId, $payload1, 60);
    }

    public function test_idempotency_payload_mismatch_rejection(): void
    {
        $this->useEngine('sqlite');

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Instructor',
            'email' => 'instructor-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new IdempotencyService;
        $action = 'publish_grade';
        $key = 'grade-pub-key-102';
        $resourceType = 'enrollment';
        $resourceId = (string) Str::uuid();

        $service->claimReservation($userId, $action, $key, $resourceType, $resourceId, ['a' => 1], 60);

        // Claim same key with different payload -> Payload mismatch
        $this->expectException(IdempotencyPayloadMismatchException::class);
        $service->claimReservation($userId, $action, $key, $resourceType, $resourceId, ['a' => 2], 60);
    }

    public function test_idempotency_completion_and_stale_worker_rejection(): void
    {
        $this->useEngine('sqlite');

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Instructor',
            'email' => 'instructor-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new IdempotencyService;
        $action = 'publish_grade';
        $key = 'grade-pub-key-103';
        $resourceType = 'enrollment';
        $resourceId = (string) Str::uuid();
        $payload = ['status' => 'final'];

        $claim = $service->claimReservation($userId, $action, $key, $resourceType, $resourceId, $payload, 60);

        // Stale worker attempt (wrong ownership token) -> StaleWorkerException
        $staleThrown = false;
        try {
            $service->completeReservation($claim['record_id'], 'invalid-token', 200, ['result' => 'ok']);
        } catch (IdempotencyStaleWorkerException $e) {
            $staleThrown = true;
        }
        $this->assertTrue($staleThrown, 'Stale worker with mismatched token must be rejected.');

        // Valid completion
        $service->completeReservation($claim['record_id'], $claim['ownership_token'], 200, ['result' => 'grade_published']);

        // Subsequent claim returns cached completed response
        $subsequent = $service->claimReservation($userId, $action, $key, $resourceType, $resourceId, $payload, 60);
        $this->assertFalse($subsequent['is_new']);
        $this->assertSame(IdempotencyService::STATUS_COMPLETED, $subsequent['status']);
        $this->assertSame(200, $subsequent['response_code']);
        $this->assertSame(['result' => 'grade_published'], $subsequent['response_body']);
    }
}
