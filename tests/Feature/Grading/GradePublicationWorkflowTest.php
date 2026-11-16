<?php

namespace Tests\Feature\Grading;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Courses\Models\Course;
use App\Domain\Grading\Models\AssessmentWeight;
use App\Domain\Grading\Models\CourseGradingScheme;
use App\Domain\Grading\Models\EnrollmentFinalGrade;
use App\Domain\Grading\Models\EnrollmentGradeLifecycleEvent;
use App\Domain\Grading\Models\GradingCategory;
use App\Domain\Grading\Services\GradebookCalculationService;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GradePublicationWorkflowTest extends TestCase
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
            Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
            Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--database' => 'sqlite', '--force' => true]);
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
                Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--database' => 'mysql', '--force' => true]);
                self::$mysqlMigrated = true;
            } else {
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
                $tables = [
                    'certificate_audit_events',
                    'certificates',
                    'enrollment_grade_lifecycle_events',
                    'idempotency_records',
                    'enrollment_current_grades',
                    'enrollment_final_grades',
                    'enrollment_grade_drafts',
                    'assessment_weights',
                    'grading_categories',
                    'course_grading_schemes',
                    'course_trainers',
                    'enrollments',
                    'student_profiles',
                    'courses',
                    'academies',
                    'users',
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

    private function createFixtures(string $engine): array
    {
        $academyId = (string) Str::uuid();
        DB::table('academies')->insert([
            'id' => $academyId,
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::random(5),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $adminId,
            'name' => 'Admin User',
            'email' => 'admin-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        User::find($adminId)->assignRole('admin');
        DB::table('academy_users')->insert(['id' => (string) Str::uuid(), 'academy_id' => $academyId, 'user_id' => $adminId, 'role' => 'admin', 'created_at' => now()]);

        $assignedTrainerId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $assignedTrainerId,
            'name' => 'Assigned Trainer',
            'email' => 'trainer1-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        User::find($assignedTrainerId)->assignRole('trainer');
        DB::table('academy_users')->insert(['id' => (string) Str::uuid(), 'academy_id' => $academyId, 'user_id' => $assignedTrainerId, 'role' => 'trainer', 'created_at' => now()]);

        $unassignedTrainerId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $unassignedTrainerId,
            'name' => 'Unassigned Trainer',
            'email' => 'trainer2-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        User::find($unassignedTrainerId)->assignRole('trainer');
        DB::table('academy_users')->insert(['id' => (string) Str::uuid(), 'academy_id' => $academyId, 'user_id' => $unassignedTrainerId, 'role' => 'trainer', 'created_at' => now()]);

        $studentUserId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $studentUserId,
            'name' => 'Student Bob',
            'email' => 'student-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        User::find($studentUserId)->assignRole('student');
        DB::table('academy_users')->insert(['id' => (string) Str::uuid(), 'academy_id' => $academyId, 'user_id' => $studentUserId, 'role' => 'student', 'created_at' => now()]);

        $supportStaffId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $supportStaffId,
            'name' => 'Support Staff',
            'email' => 'support-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        User::find($supportStaffId)->assignRole('support_staff');
        DB::table('academy_users')->insert(['id' => (string) Str::uuid(), 'academy_id' => $academyId, 'user_id' => $supportStaffId, 'role' => 'support_staff', 'created_at' => now()]);

        $profileId = (string) Str::uuid();
        DB::table('student_profiles')->insert([
            'id' => $profileId,
            'academy_id' => $academyId,
            'user_id' => $studentUserId,
            'student_code' => 'STU-'.Str::random(8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = (string) Str::uuid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'academy_id' => $academyId,
            'title' => 'Advanced Technical SEO',
            'slug' => 'adv-seo-'.Str::random(5),
            'status' => 'Published',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign trainer1 to course
        DB::table('course_trainers')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $academyId,
            'course_id' => $courseId,
            'user_id' => $assignedTrainerId,
            'is_primary' => true,
            'created_at' => now(),
        ]);

        $schemeId = (string) Str::uuid();
        DB::table('course_grading_schemes')->insert([
            'id' => $schemeId,
            'course_id' => $courseId,
            'name' => 'Standard Scheme',
            'version' => 1,
            'passing_grade' => '70.00',
            'calculation_method' => 'weighted_categories',
            'status' => 'active',
            'is_active' => true,
            'created_by' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('courses')->where('id', $courseId)->update([
            'active_grading_scheme_id' => $schemeId,
        ]);

        $cat = GradingCategory::create([
            'course_id' => $courseId,
            'grading_scheme_id' => $schemeId,
            'name' => 'Quizzes',
            'weight' => '100.00',
            'is_mandatory' => false,
            'display_order' => 1,
        ]);

        $moduleId = (string) Str::uuid();
        DB::table('modules')->insert([
            'id' => $moduleId,
            'academy_id' => $academyId,
            'course_id' => $courseId,
            'title' => 'Core Module',
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quiz = Quiz::create([
            'module_id' => $moduleId,
            'course_id' => $courseId,
            'title' => 'Core Quiz',
            'passing_percentage' => 70,
        ]);
        AssessmentWeight::create([
            'grading_scheme_id' => $schemeId,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'quiz',
            'assessment_id' => $quiz->id,
            'custom_weight' => '100.00',
            'is_mandatory' => false,
        ]);

        $enrollmentId = (string) Str::uuid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'academy_id' => $academyId,
            'student_profile_id' => $profileId,
            'course_id' => $courseId,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Student attempt 88%
        DB::table('assessment_attempts')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $academyId,
            'assessment_id' => $quiz->id,
            'enrollment_id' => $enrollmentId,
            'user_id' => $studentUserId,
            'attempt_number' => 1,
            'status' => 'evaluated',
            'started_at' => now(),
            'total_points' => '100.00',
            'earned_points' => '88.00',
            'score_percentage' => '88.00',
            'passed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'academy' => Academy::find($academyId),
            'admin' => User::find($adminId),
            'assignedTrainer' => User::find($assignedTrainerId),
            'unassignedTrainer' => User::find($unassignedTrainerId),
            'studentUser' => User::find($studentUserId),
            'studentProfile' => StudentProfile::find($profileId),
            'supportStaff' => User::find($supportStaffId),
            'course' => Course::find($courseId),
            'scheme' => CourseGradingScheme::find($schemeId),
            'enrollment' => Enrollment::find($enrollmentId),
            'quiz' => $quiz,
        ];
    }

    #[DataProvider('engineProvider')]
    public function test_administrator_can_publish_grade(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $service = app(GradebookCalculationService::class);
        $finalGrade = $service->publishEnrollmentGrade(
            $fx['enrollment'],
            $fx['admin'],
            'key_admin_publish',
            'Admin publication'
        );

        $this->assertInstanceOf(EnrollmentFinalGrade::class, $finalGrade);
        $this->assertEquals(1, $finalGrade->version);
        $this->assertEquals('88.00', (string) $finalGrade->final_score);
        $this->assertEquals('B', $finalGrade->letter_grade);
        $this->assertTrue((bool) $finalGrade->is_passing);
        $this->assertEquals($fx['admin']->id, $finalGrade->published_by);

        // Verify enrollment_current_grades pointer
        $current = $fx['enrollment']->fresh()->currentGrade;
        $this->assertNotNull($current);
        $this->assertEquals($finalGrade->id, $current->published_final_grade_id);
        $this->assertEquals('published', $current->status);

        // Verify lifecycle event
        $events = $fx['enrollment']->lifecycleEvents()->where('event_type', 'grade_published')->get();
        $this->assertCount(1, $events);
        $this->assertEquals($finalGrade->id, $events->first()->grade_snapshot_id);
    }

    #[DataProvider('engineProvider')]
    public function test_assigned_trainer_can_publish_grade(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $service = app(GradebookCalculationService::class);
        $finalGrade = $service->publishEnrollmentGrade(
            $fx['enrollment'],
            $fx['assignedTrainer'],
            'key_trainer_publish',
            'Trainer publication'
        );

        $this->assertInstanceOf(EnrollmentFinalGrade::class, $finalGrade);
        $this->assertEquals($fx['assignedTrainer']->id, $finalGrade->published_by);
        $this->assertEquals('published', $fx['enrollment']->fresh()->currentGrade->status);
    }

    #[DataProvider('engineProvider')]
    public function test_unassigned_trainer_cannot_publish_grade_403(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $service = app(GradebookCalculationService::class);

        $this->expectException(AuthorizationException::class);
        $service->publishEnrollmentGrade(
            $fx['enrollment'],
            $fx['unassignedTrainer'],
            'key_unassigned_trainer',
            'Unauthorized publication attempt'
        );
    }

    #[DataProvider('engineProvider')]
    public function test_student_and_support_staff_cannot_publish_grade_403(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $service = app(GradebookCalculationService::class);

        try {
            $service->publishEnrollmentGrade(
                $fx['enrollment'],
                $fx['studentUser'],
                'key_student_attempt',
                'Student publishing own grade'
            );
            $this->fail('Student should not be able to publish grade.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }

        try {
            $service->publishEnrollmentGrade(
                $fx['enrollment'],
                $fx['supportStaff'],
                'key_support_attempt',
                'Support staff publishing grade'
            );
            $this->fail('Support staff should not be able to publish grade.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }
    }

    #[DataProvider('engineProvider')]
    public function test_republishing_creates_version_2_with_supersedes_pointer(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $service = app(GradebookCalculationService::class);

        // Version 1
        $v1 = $service->publishEnrollmentGrade(
            $fx['enrollment'],
            $fx['admin'],
            'key_publish_v1',
            'Initial publication'
        );
        $this->assertEquals(1, $v1->version);
        $this->assertNull($v1->supersedes_grade_id);

        // Version 2 (with different score or correction)
        DB::table('assessment_attempts')
            ->where('enrollment_id', $fx['enrollment']->id)
            ->update(['score_percentage' => '94.00']);

        $v2 = $service->publishEnrollmentGrade(
            $fx['enrollment'],
            $fx['assignedTrainer'],
            'key_publish_v2',
            'Grade correction version 2'
        );

        $this->assertEquals(2, $v2->version);
        $this->assertEquals($v1->id, $v2->supersedes_grade_id);
        $this->assertEquals('94.00', (string) $v2->final_score);
        $this->assertEquals('A', $v2->letter_grade);

        // Current grade points to v2
        $current = $fx['enrollment']->fresh()->currentGrade;
        $this->assertEquals($v2->id, $current->published_final_grade_id);

        // Both versions exist in database (history preserved)
        $grades = EnrollmentFinalGrade::where('enrollment_id', $fx['enrollment']->id)->orderBy('version')->get();
        $this->assertCount(2, $grades);
        $this->assertEquals('88.00', (string) $grades[0]->final_score);
        $this->assertEquals('94.00', (string) $grades[1]->final_score);
    }

    #[DataProvider('engineProvider')]
    public function test_publication_is_strictly_idempotent(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $service = app(GradebookCalculationService::class);
        $sameKey = 'idempotent_publish_key_123';

        $first = $service->publishEnrollmentGrade($fx['enrollment'], $fx['admin'], $sameKey, 'First call');
        $second = $service->publishEnrollmentGrade($fx['enrollment'], $fx['admin'], $sameKey, 'First call');

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, EnrollmentFinalGrade::where('enrollment_id', $fx['enrollment']->id)->count());
    }

    #[DataProvider('engineProvider')]
    public function test_only_administrator_can_resolve_certificate_audits(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        // Mark enrollment complete & publish passing grade
        $fx['enrollment']->update(['status' => Enrollment::STATUS_COMPLETED, 'completed_at' => now()]);
        $service = app(GradebookCalculationService::class);
        $service->publishEnrollmentGrade($fx['enrollment'], $fx['admin'], 'idemp_key_cert', 'Publishing passing grade');

        // Issue certificate
        $certService = app(CertificateIssuanceService::class);
        $cert = $certService->issueCertificate($fx['enrollment']->fresh());

        // Flag for audit
        $flagged = $certService->flagForAudit($cert, $fx['admin'], 'Grade dispute under review');
        $this->assertTrue($flagged->isUnderAudit());

        // Trainer attempts to resolve audit -> 403 Forbidden
        try {
            $certService->resolveAudit($flagged, $fx['assignedTrainer'], 'cleared', 'Trainer cleared audit');
            $this->fail('Trainer should not be able to resolve certificate audit.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Only administrators', $e->getMessage());
        }

        // Student attempts to resolve audit -> 403 Forbidden
        try {
            $certService->resolveAudit($flagged, $fx['studentUser'], 'cleared', 'Student cleared audit');
            $this->fail('Student should not be able to resolve certificate audit.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Only administrators', $e->getMessage());
        }

        // Administrator resolves audit -> Success!
        $resolved = $certService->resolveAudit($flagged, $fx['admin'], 'cleared', 'Admin verified academic records');
        $this->assertFalse($resolved->isUnderAudit());
        $this->assertEquals(Certificate::STATUS_ISSUED, $resolved->status);
    }

    #[DataProvider('engineProvider')]
    public function test_grade_withdrawal_preserves_snapshot_immutability_and_updates_status_idempotently(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $service = app(GradebookCalculationService::class);
        $published = $service->publishEnrollmentGrade($fx['enrollment'], $fx['admin'], 'key_withdraw_test', 'Initial official grade');

        $originalPublishedAttributes = $published->fresh()->toArray();
        $initialLockVersion = $fx['enrollment']->fresh()->currentGrade->lock_version;

        // Execute withdrawal
        $withdrawnCurrent = $service->withdrawEnrollmentGrade($fx['enrollment'], $fx['admin'], 'Academic integrity review pending');

        $this->assertEquals('withdrawn', $withdrawnCurrent->status);
        $this->assertEquals($initialLockVersion + 1, $withdrawnCurrent->lock_version);

        // 1. Original snapshot remains unchanged and available in history
        $afterSnapshot = EnrollmentFinalGrade::find($published->id);
        $this->assertNotNull($afterSnapshot);
        $this->assertEquals($originalPublishedAttributes['final_score'], (string) $afterSnapshot->final_score);
        $this->assertEquals($originalPublishedAttributes['letter_grade'], $afterSnapshot->letter_grade);
        $this->assertEquals($originalPublishedAttributes['version'], $afterSnapshot->version);

        // 2. Withdrawal represented through lifecycle event
        $lifecycleEvent = EnrollmentGradeLifecycleEvent::where('enrollment_id', $fx['enrollment']->id)
            ->where('event_type', 'grade_withdrawn')
            ->first();
        $this->assertNotNull($lifecycleEvent);
        $this->assertEquals($published->id, $lifecycleEvent->grade_snapshot_id);
        $this->assertEquals($fx['admin']->id, $lifecycleEvent->actor_id);
        $this->assertEquals('Academic integrity review pending', $lifecycleEvent->payload['reason']);

        // 3. Repeated withdrawal is strictly idempotent
        $repeatedCurrent = $service->withdrawEnrollmentGrade($fx['enrollment'], $fx['admin'], 'Repeated withdrawal attempt');
        $this->assertEquals('withdrawn', $repeatedCurrent->status);
        $this->assertEquals($withdrawnCurrent->lock_version, $repeatedCurrent->lock_version);
        $this->assertEquals(1, EnrollmentGradeLifecycleEvent::where('enrollment_id', $fx['enrollment']->id)->where('event_type', 'grade_withdrawn')->count());

        // 4. Cannot withdraw when no published grade exists
        $unpubUser = User::create([
            'name' => 'Unpub Student',
            'email' => 'unpub-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $unpubUser->assignRole('student');
        $unpubProfile = StudentProfile::create([
            'academy_id' => $fx['academy']->id,
            'user_id' => $unpubUser->id,
            'student_code' => 'STU-'.Str::random(6),
        ]);
        $unassociatedEnrollment = Enrollment::create([
            'academy_id' => $fx['academy']->id,
            'student_profile_id' => $unpubProfile->id,
            'course_id' => $fx['course']->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);
        try {
            $service->withdrawEnrollmentGrade($unassociatedEnrollment, $fx['admin'], 'Invalid withdrawal');
            $this->fail('Expected exception when withdrawing enrollment without published grade.');
        } catch (\Throwable $e) {
            $this->assertTrue(
                $e instanceof ModelNotFoundException ||
                $e instanceof \InvalidArgumentException
            );
        }
    }

    #[DataProvider('engineProvider')]
    public function test_certificate_eligibility_integration_across_all_scenarios(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $calcService = app(GradebookCalculationService::class);
        $certService = app(CertificateIssuanceService::class);

        // Scenario 1: Completed + Passing -> Eligible
        $fx['enrollment']->update(['status' => Enrollment::STATUS_COMPLETED, 'completed_at' => now()]);
        $calcService->publishEnrollmentGrade($fx['enrollment'], $fx['admin'], 'key_scen1', 'Publish passing');
        $this->assertTrue($calcService->isEligibleForCertificate($fx['enrollment']->fresh()));
        $cert1 = $certService->issueCertificate($fx['enrollment']->fresh());
        $this->assertNotNull($cert1);

        // Test Withdrawal effect: withdrawing published passing grade revokes eligibility
        $calcService->withdrawEnrollmentGrade($fx['enrollment'], $fx['admin'], 'Withdrawal for audit');
        $this->assertFalse($calcService->isEligibleForCertificate($fx['enrollment']->fresh()));

        // Create new student/enrollment for failing test
        $studentFailing = User::create([
            'name' => 'Failing Student',
            'email' => 'failing-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $studentFailing->assignRole('student');
        $profileFailing = StudentProfile::create([
            'academy_id' => $fx['academy']->id,
            'user_id' => $studentFailing->id,
            'student_code' => 'STU-'.Str::random(6),
        ]);
        $enrollmentFailing = Enrollment::create([
            'academy_id' => $fx['academy']->id,
            'student_profile_id' => $profileFailing->id,
            'course_id' => $fx['course']->id,
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => now(),
            'enrolled_at' => now()->toDateString(),
        ]);

        // Insert failing score (40%)
        DB::table('assessment_attempts')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $fx['quiz']->id,
            'enrollment_id' => $enrollmentFailing->id,
            'user_id' => $studentFailing->id,
            'attempt_number' => 1,
            'status' => 'evaluated',
            'started_at' => now(),
            'total_points' => '100.00',
            'earned_points' => '40.00',
            'score_percentage' => '40.00',
            'passed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Scenario 2: Completed + Failing -> Ineligible
        $calcService->publishEnrollmentGrade($enrollmentFailing, $fx['admin'], 'key_scen2', 'Publish failing');
        $this->assertFalse($calcService->isEligibleForCertificate($enrollmentFailing->fresh()));
        try {
            $certService->issueCertificate($enrollmentFailing->fresh());
            $this->fail('Expected InvalidArgumentException for completed but failing grade.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('published passing grade', $e->getMessage());
        }

        // Scenario 3: Incomplete + Passing -> Ineligible
        $studentInc = User::create([
            'name' => 'Incomplete Student',
            'email' => 'inc-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $studentInc->assignRole('student');
        $profileInc = StudentProfile::create([
            'academy_id' => $fx['academy']->id,
            'user_id' => $studentInc->id,
            'student_code' => 'STU-'.Str::random(6),
        ]);
        $enrollmentInc = Enrollment::create([
            'academy_id' => $fx['academy']->id,
            'student_profile_id' => $profileInc->id,
            'course_id' => $fx['course']->id,
            'status' => Enrollment::STATUS_ACTIVE, // Not completed
            'enrolled_at' => now()->toDateString(),
        ]);
        DB::table('assessment_attempts')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $fx['quiz']->id,
            'enrollment_id' => $enrollmentInc->id,
            'user_id' => $studentInc->id,
            'attempt_number' => 1,
            'status' => 'evaluated',
            'started_at' => now(),
            'total_points' => '100.00',
            'earned_points' => '95.00',
            'score_percentage' => '95.00',
            'passed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $calcService->publishEnrollmentGrade($enrollmentInc, $fx['admin'], 'key_scen3', 'Publish passing incomplete');
        $this->assertFalse($calcService->isEligibleForCertificate($enrollmentInc->fresh()));
        try {
            $certService->issueCertificate($enrollmentInc->fresh());
            $this->fail('Expected InvalidArgumentException for incomplete enrollment.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('completed enrollments', $e->getMessage());
        }

        // Scenario 4: Completed + No published grade, active grading scheme -> Ineligible
        // (Even if draft grade is passing / calculated, draft NEVER satisfies eligibility)
        $studentNoPub = User::create([
            'name' => 'NoPub Student',
            'email' => 'nopub-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $studentNoPub->assignRole('student');
        $profileNoPub = StudentProfile::create([
            'academy_id' => $fx['academy']->id,
            'user_id' => $studentNoPub->id,
            'student_code' => 'STU-'.Str::random(6),
        ]);
        $enrollmentNoPub = Enrollment::create([
            'academy_id' => $fx['academy']->id,
            'student_profile_id' => $profileNoPub->id,
            'course_id' => $fx['course']->id,
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => now(),
            'enrolled_at' => now()->toDateString(),
        ]);
        DB::table('assessment_attempts')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $fx['quiz']->id,
            'enrollment_id' => $enrollmentNoPub->id,
            'user_id' => $studentNoPub->id,
            'attempt_number' => 1,
            'status' => 'evaluated',
            'started_at' => now(),
            'total_points' => '100.00',
            'earned_points' => '100.00',
            'score_percentage' => '100.00',
            'passed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Calculate draft score 100%, but DO NOT publish
        $calcService->calculateEnrollmentGrade($enrollmentNoPub, actor: $fx['admin']);
        $this->assertNotNull($enrollmentNoPub->fresh()->gradeDraft);
        $this->assertEquals('100.00', (string) $enrollmentNoPub->fresh()->gradeDraft->current_score);
        // Draft grade NEVER satisfies eligibility
        $this->assertFalse($calcService->isEligibleForCertificate($enrollmentNoPub->fresh()));
        try {
            $certService->issueCertificate($enrollmentNoPub->fresh());
            $this->fail('Expected InvalidArgumentException when no published grade exists.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('published passing grade', $e->getMessage());
        }

        // Scenario 5: Completed + No active grading scheme -> Preserve previously approved behavior (Eligible)
        $courseNoScheme = Course::create([
            'academy_id' => $fx['academy']->id,
            'title' => 'Course Without Scheme',
            'slug' => 'no-scheme-'.Str::random(5),
            'status' => 'Published',
            'visibility' => 'public',
            'active_grading_scheme_id' => null, // No active scheme
        ]);
        $enrollmentNoScheme = Enrollment::create([
            'academy_id' => $fx['academy']->id,
            'student_profile_id' => $profileNoPub->id,
            'course_id' => $courseNoScheme->id,
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => now(),
            'enrolled_at' => now()->toDateString(),
        ]);
        $this->assertTrue($calcService->isEligibleForCertificate($enrollmentNoScheme->fresh()));
        $certNoScheme = $certService->issueCertificate($enrollmentNoScheme->fresh());
        $this->assertNotNull($certNoScheme);
        $this->assertEquals(Certificate::STATUS_ISSUED, $certNoScheme->status);
    }
}
