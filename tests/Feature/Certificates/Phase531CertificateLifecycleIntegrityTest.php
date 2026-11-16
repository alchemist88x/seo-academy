<?php

namespace Tests\Feature\Certificates;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Jobs\GenerateCertificatePdfJob;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Certificates\Services\CertificatePdfService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseTrainer;
use App\Domain\Courses\Models\Module;
use App\Domain\Grading\Models\AssessmentWeight;
use App\Domain\Grading\Models\CourseGradingScheme;
use App\Domain\Grading\Models\GradingCategory;
use App\Domain\Grading\Services\GradebookCalculationService;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Phase531CertificateLifecycleIntegrityTest extends TestCase
{
    private static bool $mysqlMigrated = false;

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
            'queue.default' => 'sync',
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

            if (! static::$mysqlMigrated) {
                Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
                static::$mysqlMigrated = true;
            } else {
                $tables = DB::connection('mysql')->select('SHOW TABLES');
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 0;');
                foreach ($tables as $t) {
                    $vals = array_values((array) $t);
                    $table = $vals[0];
                    if ($table !== 'migrations') {
                        DB::connection('mysql')->table($table)->truncate();
                    }
                }
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 1;');
            }
        }

        $this->seed(RoleSeeder::class);
    }

    private function createAcademicEnvironment(): array
    {
        $academy = Academy::create([
            'name' => 'SEO Academic Institute',
            'slug' => 'seo-academic-inst-'.Str::random(6),
            'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Dean Administrator',
            'email' => 'admin-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
        $admin->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'admin']);

        $trainer = User::create([
            'name' => 'Lead Trainer',
            'email' => 'trainer-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $trainer->assignRole('trainer');
        $trainer->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'trainer']);

        $studentUser = User::create([
            'name' => 'Alice Walker',
            'email' => 'alice-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $studentUser->assignRole('student');
        $studentUser->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'student']);

        $course = Course::create([
            'academy_id' => $academy->id,
            'title' => 'Advanced Technical SEO Masterclass',
            'slug' => 'adv-seo-'.Str::random(6),
            'status' => 'published',
        ]);

        CourseTrainer::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'user_id' => $trainer->id,
            'is_primary' => true,
        ]);

        $scheme = CourseGradingScheme::create([
            'course_id' => $course->id,
            'name' => 'Standard Masterclass Scheme',
            'grading_scale' => 'percentage',
            'passing_percentage' => '60.00',
            'is_active' => true,
        ]);
        $course->update(['active_grading_scheme_id' => $scheme->id]);

        $category = GradingCategory::create([
            'course_id' => $course->id,
            'grading_scheme_id' => $scheme->id,
            'name' => 'Examinations',
            'weight' => '100.00',
            'drop_lowest' => 0,
            'is_active' => true,
        ]);

        $module = Module::create([
            'course_id' => $course->id,
            'title' => 'Module 1: Crawling',
            'order_column' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);

        $lesson = Lesson::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Lesson 1.1: Spiders',
            'order_column' => 1,
            'status' => 'Active',
            'is_required' => true,
        ]);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Final Exam',
            'type' => 'exam',
            'pass_percentage' => 60,
            'status' => 'Active',
            'is_required' => false,
        ]);

        AssessmentWeight::create([
            'grading_scheme_id' => $scheme->id,
            'grading_category_id' => $category->id,
            'assessment_type' => 'quiz',
            'assessment_id' => $quiz->id,
            'custom_weight' => '100.00',
            'is_mandatory' => false,
        ]);

        $profile = StudentProfile::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'student_code' => 'STU-'.strtoupper(Str::random(6)),
        ]);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => Carbon::now()->subDays(10)->toDateString(),
        ]);

        return [$admin, $trainer, $studentUser, $profile, $course, $module, $lesson, $quiz, $enrollment, $academy];
    }

    #[DataProvider('engineProvider')]
    public function test_scenario_a_course_completion_before_passing_grade_publication(string $engine): void
    {
        $this->useEngine($engine);
        [$admin, $trainer, $studentUser, $profile, $course, $module, $lesson, $quiz, $enrollment, $academy] = $this->createAcademicEnvironment();

        Queue::fake();

        // Step 1: Student completes curriculum first
        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $completionService = app(CourseCompletionService::class);
        $completed = $completionService->completeCourse($enrollment);
        $this->assertTrue($completed);
        $this->assertTrue($enrollment->fresh()->isCompleted());

        // Step 2: No passing grade published yet -> Certificate must NOT be issued
        $issuanceService = app(CertificateIssuanceService::class);
        $this->assertNull($issuanceService->getCertificateForEnrollment($enrollment));
        Queue::assertNothingPushed();

        // Step 3: Record quiz score and publish passing grade
        DB::table('quiz_submissions')->insert([
            'id' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'user_id' => $studentUser->id,
            'score' => 88.00,
            'passed' => true,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        $calcService = app(GradebookCalculationService::class);
        $finalGrade = $calcService->publishEnrollmentGrade(
            $enrollment,
            $trainer,
            'Passed examination on first attempt.'
        );

        $this->assertTrue($finalGrade->is_passing);

        // Step 4: Certificate is automatically issued!
        $certificate = $issuanceService->getCertificateForEnrollment($enrollment);
        $this->assertNotNull($certificate);
        $this->assertSame(Certificate::STATUS_ISSUED, $certificate->status);
        $this->assertSame($enrollment->id, $certificate->enrollment_id);
        $this->assertSame($enrollment->id, $certificate->original_enrollment_id);
        $this->assertSame(1, $certificate->version);
        $this->assertSame('Alice Walker', $certificate->issuance_snapshot['student']['name']);

        Queue::assertPushed(GenerateCertificatePdfJob::class, function ($job) use ($certificate) {
            return $job->certificateId === $certificate->id;
        });
    }

    #[DataProvider('engineProvider')]
    public function test_scenario_b_passing_grade_published_before_course_completion(string $engine): void
    {
        $this->useEngine($engine);
        [$admin, $trainer, $studentUser, $profile, $course, $module, $lesson, $quiz, $enrollment, $academy] = $this->createAcademicEnvironment();

        Queue::fake();

        // Step 1: Grade is published first while course is still incomplete
        DB::table('quiz_submissions')->insert([
            'id' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'user_id' => $studentUser->id,
            'score' => 92.50,
            'passed' => true,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        $calcService = app(GradebookCalculationService::class);
        $finalGrade = $calcService->publishEnrollmentGrade(
            $enrollment,
            $trainer,
            'Early examination completed with high honors.'
        );

        $this->assertTrue($finalGrade->is_passing);
        $this->assertFalse($enrollment->fresh()->isCompleted());

        // Step 2: Policy 2 strictly enforced: No certificate issued yet because enrollment is incomplete
        $issuanceService = app(CertificateIssuanceService::class);
        $this->assertNull($issuanceService->getCertificateForEnrollment($enrollment));
        Queue::assertNothingPushed();

        // Step 3: Student completes course curriculum later
        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $completionService = app(CourseCompletionService::class);
        $completed = $completionService->completeCourse($enrollment);
        $this->assertTrue($completed);
        $this->assertTrue($enrollment->fresh()->isCompleted());

        // Step 4: Certificate is automatically issued upon completion!
        $certificate = $issuanceService->getCertificateForEnrollment($enrollment);
        $this->assertNotNull($certificate);
        $this->assertSame(Certificate::STATUS_ISSUED, $certificate->status);
        $this->assertSame($enrollment->id, $certificate->enrollment_id);
        $this->assertSame($enrollment->id, $certificate->original_enrollment_id);
        $this->assertSame(1, $certificate->version);

        Queue::assertPushed(GenerateCertificatePdfJob::class, function ($job) use ($certificate) {
            return $job->certificateId === $certificate->id;
        });
    }

    #[DataProvider('engineProvider')]
    public function test_duplicate_events_and_concurrent_issuance_prevention(string $engine): void
    {
        $this->useEngine($engine);
        [$admin, $trainer, $studentUser, $profile, $course, $module, $lesson, $quiz, $enrollment, $academy] = $this->createAcademicEnvironment();

        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        DB::table('quiz_submissions')->insert([
            'id' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'user_id' => $studentUser->id,
            'score' => 85.00,
            'passed' => true,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        $completionService = app(CourseCompletionService::class);
        $completionService->completeCourse($enrollment);

        $calcService = app(GradebookCalculationService::class);
        $calcService->publishEnrollmentGrade($enrollment, $trainer, 'Graded.');

        $issuanceService = app(CertificateIssuanceService::class);
        $cert1 = $issuanceService->getCertificateForEnrollment($enrollment);
        $this->assertNotNull($cert1);

        // Attempt second issuance on same enrollment
        $cert2 = $issuanceService->issueCertificate($enrollment->fresh());
        $this->assertSame($cert1->id, $cert2->id);
        $this->assertSame($cert1->certificate_number, $cert2->certificate_number);
        $this->assertSame(1, Certificate::where('original_enrollment_id', $enrollment->id)->count());

        // Repeat completeCourse call
        $completionService->completeCourse($enrollment->fresh());
        $this->assertSame(1, Certificate::where('original_enrollment_id', $enrollment->id)->count());
    }

    #[DataProvider('engineProvider')]
    public function test_multiple_consecutive_reissues_and_historical_enrollment_preservation(string $engine): void
    {
        $this->useEngine($engine);
        [$admin, $trainer, $studentUser, $profile, $course, $module, $lesson, $quiz, $enrollment, $academy] = $this->createAcademicEnvironment();

        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        DB::table('quiz_submissions')->insert([
            'id' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'user_id' => $studentUser->id,
            'score' => 85.00,
            'passed' => true,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        app(CourseCompletionService::class)->completeCourse($enrollment);
        app(GradebookCalculationService::class)->publishEnrollmentGrade($enrollment, $trainer, 'Passed.');

        $issuanceService = app(CertificateIssuanceService::class);
        $certV1 = $issuanceService->getCertificateForEnrollment($enrollment);
        $this->assertNotNull($certV1);
        $this->assertSame(1, $certV1->version);
        $this->assertSame($enrollment->id, $certV1->enrollment_id);
        $this->assertSame($enrollment->id, $certV1->original_enrollment_id);

        // First Reissue: v1 -> v2
        $certV2 = $issuanceService->reissueCertificate(
            $certV1,
            $admin,
            'Reissuing v2 due to corrected legal name spelling.'
        );

        $this->assertSame(2, $certV2->version);
        $this->assertSame($certV1->id, $certV2->supersedes_id);
        $this->assertSame($enrollment->id, $certV2->enrollment_id);
        $this->assertSame($enrollment->id, $certV2->original_enrollment_id);

        $certV1Fresh = $certV1->fresh();
        $this->assertSame(Certificate::STATUS_SUPERSEDED, $certV1Fresh->status);
        $this->assertNull($certV1Fresh->enrollment_id); // Unique slot vacated for active cert
        $this->assertSame($enrollment->id, $certV1Fresh->original_enrollment_id); // Original association permanently preserved!
        $this->assertSame($certV2->id, $certV1Fresh->superseded_by_id);

        // Second Reissue: v2 -> v3
        $certV3 = $issuanceService->reissueCertificate(
            $certV2,
            $admin,
            'Reissuing v3 due to updated template branding.'
        );

        $this->assertSame(3, $certV3->version);
        $this->assertSame($certV2->id, $certV3->supersedes_id);
        $this->assertSame($enrollment->id, $certV3->enrollment_id);
        $this->assertSame($enrollment->id, $certV3->original_enrollment_id);

        $certV2Fresh = $certV2->fresh();
        $this->assertSame(Certificate::STATUS_SUPERSEDED, $certV2Fresh->status);
        $this->assertNull($certV2Fresh->enrollment_id);
        $this->assertSame($enrollment->id, $certV2Fresh->original_enrollment_id);
        $this->assertSame($certV3->id, $certV2Fresh->superseded_by_id);

        // Verify full historical lineage and relationships
        $history = $issuanceService->getCertificateHistoryForEnrollment($enrollment);
        $this->assertCount(3, $history);
        $this->assertSame(3, $history[0]->version);
        $this->assertSame(2, $history[1]->version);
        $this->assertSame(1, $history[2]->version);

        // Traceability from every historical certificate back to enrollment
        $this->assertSame($enrollment->id, $certV1Fresh->enrollment->id);
        $this->assertSame($enrollment->id, $certV2Fresh->enrollment->id);
        $this->assertSame($enrollment->id, $certV3->fresh()->enrollment->id);

        // Enrollment relationship queries
        $enrollmentFresh = $enrollment->fresh();
        $this->assertCount(3, $enrollmentFresh->certificates);
        $this->assertSame($certV3->id, $enrollmentFresh->activeCertificate->id);

        // Historical records are NEVER mistaken for active certificates
        $this->assertFalse($certV1Fresh->isIssued());
        $this->assertFalse($certV2Fresh->isIssued());
        $this->assertTrue($certV3->fresh()->isIssued());
    }

    #[DataProvider('engineProvider')]
    public function test_verification_token_security_and_qr_code_url(string $engine): void
    {
        $this->useEngine($engine);
        [$admin, $trainer, $studentUser, $profile, $course, $module, $lesson, $quiz, $enrollment, $academy] = $this->createAcademicEnvironment();

        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        DB::table('quiz_submissions')->insert([
            'id' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'user_id' => $studentUser->id,
            'score' => 90.00,
            'passed' => true,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        app(CourseCompletionService::class)->completeCourse($enrollment);
        app(GradebookCalculationService::class)->publishEnrollmentGrade($enrollment, $trainer, 'Graded.');

        $issuanceService = app(CertificateIssuanceService::class);
        $cert = $issuanceService->getCertificateForEnrollment($enrollment);

        // 1. Verification token is cryptographically random (32 chars)
        $this->assertSame(32, strlen($cert->verification_code));
        $this->assertSame(64, strlen($cert->verification_token_hash));
        $this->assertSame(hash('sha256', $cert->verification_code), $cert->verification_token_hash);

        // 2. Token is not exposed in issuance_snapshot
        $snapshot = $cert->issuance_snapshot;
        $this->assertArrayNotHasKey('verification_code', $snapshot);
        $this->assertArrayNotHasKey('verification_token_hash', $snapshot);

        // 3. QR code / verification URL verification
        $pdfService = app(CertificatePdfService::class);
        $pdfContent = $pdfService->renderPdfBinary($cert);
        $this->assertNotEmpty($pdfContent);

        // 4. Public verification requires opaque token or its SHA256 hash
        $respByToken = $this->get('/certificates/verify/'.$cert->verification_code);
        $respByToken->assertOk();
        $respByToken->assertSee('VALID CREDENTIAL');

        $respByHash = $this->get('/certificates/verify/'.$cert->verification_token_hash);
        $respByHash->assertOk();
        $respByHash->assertSee('VALID CREDENTIAL');

        // 5. Predictable sequential certificate numbers and internal IDs CANNOT act as authentication tokens
        $respByCertNumber = $this->get('/certificates/verify/'.$cert->certificate_number);
        $respByCertNumber->assertOk();
        $respByCertNumber->assertSee('NOT FOUND');

        $respById = $this->get('/certificates/verify/'.$cert->id);
        $respById->assertOk();
        $respById->assertSee('NOT FOUND');

        // 6. Token after reissuance
        $certV2 = $issuanceService->reissueCertificate($cert, $admin, 'Reissuance token test.');
        $this->assertNotSame($cert->verification_code, $certV2->verification_code);
        $this->assertNotSame($cert->verification_token_hash, $certV2->verification_token_hash);

        // Old token reports historical superseded record without exposing replacement token
        $respOldToken = $this->get('/certificates/verify/'.$cert->verification_code);
        $respOldToken->assertOk();
        $respOldToken->assertSee('SUPERSEDED / REISSUED');
        $respOldToken->assertDontSee($certV2->verification_code);

        // New token reports active valid credential
        $respNewToken = $this->get('/certificates/verify/'.$certV2->verification_code);
        $respNewToken->assertOk();
        $respNewToken->assertSee('VALID CREDENTIAL');
    }

    #[DataProvider('engineProvider')]
    public function test_administrative_role_and_authorization_consistency(string $engine): void
    {
        $this->useEngine($engine);
        [$admin, $trainer, $studentUser, $profile, $course, $module, $lesson, $quiz, $enrollment, $academy] = $this->createAcademicEnvironment();

        $supportStaff = User::create([
            'name' => 'Support Agent',
            'email' => 'support-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $supportStaff->assignRole('support_staff');

        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        DB::table('quiz_submissions')->insert([
            'id' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'user_id' => $studentUser->id,
            'score' => 88.00,
            'passed' => true,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        app(CourseCompletionService::class)->completeCourse($enrollment);
        app(GradebookCalculationService::class)->publishEnrollmentGrade($enrollment, $trainer, 'Graded.');

        $issuanceService = app(CertificateIssuanceService::class);
        $cert = $issuanceService->getCertificateForEnrollment($enrollment);

        // Non-admin (trainer, support_staff, student) CANNOT resolve audits
        $issuanceService->flagForAudit($cert, $admin, 'Suspicious grading');
        $certFresh = $cert->fresh();

        $this->expectException(AuthorizationException::class);
        $issuanceService->resolveAudit($certFresh, $trainer, 'cleared', 'Trainer clearance');
    }

    #[DataProvider('engineProvider')]
    public function test_non_admin_cannot_reissue_or_revoke_certificates(string $engine): void
    {
        $this->useEngine($engine);
        [$admin, $trainer, $studentUser, $profile, $course, $module, $lesson, $quiz, $enrollment, $academy] = $this->createAcademicEnvironment();

        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        DB::table('quiz_submissions')->insert([
            'id' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'user_id' => $studentUser->id,
            'score' => 88.00,
            'passed' => true,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        app(CourseCompletionService::class)->completeCourse($enrollment);
        app(GradebookCalculationService::class)->publishEnrollmentGrade($enrollment, $trainer, 'Graded.');

        $issuanceService = app(CertificateIssuanceService::class);
        $cert = $issuanceService->getCertificateForEnrollment($enrollment);

        // Non-admin cannot reissue
        try {
            $issuanceService->reissueCertificate($cert, $trainer, 'Trainer reissue attempt');
            $this->fail('Expected AuthorizationException for trainer reissue.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        // Non-admin cannot revoke
        try {
            $issuanceService->revokeCertificate($cert, $trainer, 'Trainer revoke attempt');
            $this->fail('Expected AuthorizationException for trainer revocation.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    #[DataProvider('engineProvider')]
    public function test_download_authorization_and_privacy_consent(string $engine): void
    {
        $this->useEngine($engine);
        [$admin, $trainer, $studentUser, $profile, $course, $module, $lesson, $quiz, $enrollment, $academy] = $this->createAcademicEnvironment();

        $supportStaff = User::create([
            'name' => 'Support Agent',
            'email' => 'support-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $supportStaff->assignRole('support_staff');

        $otherStudent = User::create([
            'name' => 'Other Student',
            'email' => 'other-student-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $otherStudent->assignRole('student');

        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        DB::table('quiz_submissions')->insert([
            'id' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'user_id' => $studentUser->id,
            'score' => 88.00,
            'passed' => true,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        app(CourseCompletionService::class)->completeCourse($enrollment);
        app(GradebookCalculationService::class)->publishEnrollmentGrade($enrollment, $trainer, 'Graded.');

        $issuanceService = app(CertificateIssuanceService::class);
        $cert = $issuanceService->getCertificateForEnrollment($enrollment);

        // Student owner can download
        $this->actingAs($studentUser)->get('/certificates/'.$cert->id.'/download')->assertOk();

        // Admin can download
        $this->actingAs($admin)->get('/certificates/'.$cert->id.'/download')->assertOk();

        // Assigned Trainer can download
        $this->actingAs($trainer)->get('/certificates/'.$cert->id.'/download')->assertOk();

        // Support Staff CANNOT download
        $this->actingAs($supportStaff)->get('/certificates/'.$cert->id.'/download')->assertStatus(403);

        // Unrelated Student CANNOT download
        $this->actingAs($otherStudent)->get('/certificates/'.$cert->id.'/download')->assertStatus(403);

        // Privacy consent toggle
        $this->assertFalse((bool) $cert->public_identity_consent);
        $verifyDefault = $this->get('/certificates/verify/'.$cert->verification_code);
        $verifyDefault->assertSee(Certificate::maskName('Alice Walker'));

        $issuanceService->setPublicIdentityConsent($cert, $studentUser, true);
        $verifyConsent = $this->get('/certificates/verify/'.$cert->verification_code);
        $verifyConsent->assertSee('Alice Walker');

        $issuanceService->setPublicIdentityConsent($cert, $studentUser, false);
        $verifyRevokedConsent = $this->get('/certificates/verify/'.$cert->verification_code);
        $verifyRevokedConsent->assertSee(Certificate::maskName('Alice Walker'));
    }
}
