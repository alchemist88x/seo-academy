<?php

namespace Tests\Feature\Certificates;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Models\CertificateAuditEvent;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseTrainer;
use App\Domain\Courses\Models\Module;
use App\Domain\Grading\Models\AssessmentWeight;
use App\Domain\Grading\Models\CourseGradingScheme;
use App\Domain\Grading\Models\GradingCategory;
use App\Domain\Grading\Services\GradebookCalculationService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Phase53CertificateLifecycleTest extends TestCase
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
                    $tableName = $vals[0];
                    if ($tableName !== 'migrations') {
                        DB::connection('mysql')->table($tableName)->truncate();
                    }
                }
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 1;');
            }
        }

        $this->seed(RoleSeeder::class);
    }

    private function createFixtures(string $engine): array
    {
        $academy = Academy::create([
            'name' => 'SEO Academic Institute',
            'slug' => 'seo-institute-'.Str::random(5),
            'is_active' => true,
        ]);
        $academyId = $academy->id;

        $admin = User::create([
            'name' => 'Dean Administrator',
            'email' => 'admin-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
        $admin->academies()->attach($academyId, ['id' => (string) Str::uuid(), 'role' => 'admin']);

        $assignedTrainer = User::create([
            'name' => 'Assigned Trainer',
            'email' => 'trainer-assigned-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $assignedTrainer->assignRole('trainer');
        $assignedTrainer->academies()->attach($academyId, ['id' => (string) Str::uuid(), 'role' => 'trainer']);

        $unassignedTrainer = User::create([
            'name' => 'Unassigned Trainer',
            'email' => 'trainer-unassigned-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $unassignedTrainer->assignRole('trainer');
        $unassignedTrainer->academies()->attach($academyId, ['id' => (string) Str::uuid(), 'role' => 'trainer']);

        $studentA = User::create([
            'name' => 'John Doe',
            'email' => 'john.doe-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $studentA->assignRole('student');
        $studentA->academies()->attach($academyId, ['id' => (string) Str::uuid(), 'role' => 'student']);

        $studentB = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane.smith-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $studentB->assignRole('student');
        $studentB->academies()->attach($academyId, ['id' => (string) Str::uuid(), 'role' => 'student']);

        $profileA = StudentProfile::create([
            'academy_id' => $academyId,
            'user_id' => $studentA->id,
            'student_code' => 'STU-'.Str::random(6),
        ]);

        $profileB = StudentProfile::create([
            'academy_id' => $academyId,
            'user_id' => $studentB->id,
            'student_code' => 'STU-'.Str::random(6),
        ]);

        $course = Course::create([
            'academy_id' => $academyId,
            'title' => 'Technical SEO Specialization',
            'slug' => 'tech-seo-'.Str::random(5),
            'status' => 'Published',
            'visibility' => 'public',
        ]);

        CourseTrainer::create([
            'academy_id' => $academyId,
            'course_id' => $course->id,
            'user_id' => $assignedTrainer->id,
            'created_at' => now(),
        ]);

        $module = Module::create([
            'course_id' => $course->id,
            'title' => 'Core Architecture',
            'status' => 'Active',
            'display_order' => 1,
        ]);

        $lesson = Lesson::create([
            'module_id' => $module->id,
            'title' => 'Crawlability and Indexability',
            'status' => 'Active',
            'visibility' => 'published',
            'display_order' => 1,
        ]);

        $schemeId = (string) Str::uuid();
        DB::table('course_grading_schemes')->insert([
            'id' => $schemeId,
            'course_id' => $course->id,
            'name' => 'Standard Scheme',
            'version' => 1,
            'passing_grade' => '70.00',
            'calculation_method' => 'weighted_categories',
            'status' => 'active',
            'is_active' => true,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $scheme = CourseGradingScheme::find($schemeId);
        $course->update(['active_grading_scheme_id' => $schemeId]);

        $cat = GradingCategory::create([
            'course_id' => $course->id,
            'grading_scheme_id' => $scheme->id,
            'name' => 'Exams',
            'weight' => '100.00',
            'is_mandatory' => false,
            'drop_lowest_count' => 0,
            'display_order' => 1,
        ]);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Final Certification Exam',
            'passing_score' => 70,
            'max_attempts' => 3,
            'is_required' => true,
            'status' => 'Active',
        ]);

        AssessmentWeight::create([
            'grading_scheme_id' => $scheme->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'quiz',
            'assessment_id' => $quiz->id,
            'custom_weight' => '100.00',
            'is_mandatory' => true,
        ]);

        $enrollmentA = Enrollment::create([
            'academy_id' => $academyId,
            'student_profile_id' => $profileA->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => now(),
            'enrolled_at' => now()->toDateString(),
        ]);

        // 92% exam score for Student A
        DB::table('assessment_attempts')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $academyId,
            'assessment_id' => $quiz->id,
            'enrollment_id' => $enrollmentA->id,
            'user_id' => $studentA->id,
            'attempt_number' => 1,
            'status' => 'evaluated',
            'started_at' => now(),
            'total_points' => '100.00',
            'earned_points' => '92.00',
            'score_percentage' => '92.00',
            'passed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'academy' => $academy,
            'admin' => $admin,
            'assignedTrainer' => $assignedTrainer,
            'unassignedTrainer' => $unassignedTrainer,
            'studentA' => $studentA,
            'studentB' => $studentB,
            'profileA' => $profileA,
            'profileB' => $profileB,
            'course' => $course,
            'scheme' => $scheme,
            'quiz' => $quiz,
            'enrollmentA' => $enrollmentA,
        ];
    }

    #[DataProvider('engineProvider')]
    public function test_automated_issuance_on_grade_publication_when_completed(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $calcService = app(GradebookCalculationService::class);
        $certService = app(CertificateIssuanceService::class);

        // Publishing passing grade triggers automated certificate issuance
        $calcService->publishEnrollmentGrade($fx['enrollmentA'], $fx['admin'], 'idemp_auto_1', 'Official graduation grade');

        $cert = $certService->getCertificateForEnrollment($fx['enrollmentA']->fresh());
        $this->assertNotNull($cert);
        $this->assertEquals(Certificate::STATUS_ISSUED, $cert->status);
        $this->assertEquals(1, $cert->version);
        $this->assertNotNull($cert->verification_code);
        $this->assertNotNull($cert->verification_token_hash);
        $this->assertEquals(hash('sha256', $cert->verification_code), $cert->verification_token_hash);

        // Immutable issuance snapshot preserved
        $snapshot = $cert->issuance_snapshot;
        $this->assertIsArray($snapshot);
        $this->assertEquals('John Doe', $snapshot['student']['name']);
        $this->assertEquals('Technical SEO Specialization', $snapshot['course']['title']);
        $this->assertEquals('92.00', $snapshot['academic']['final_score']);
        $this->assertTrue($snapshot['academic']['is_passing']);

        // Issuance audit event recorded
        $auditEvent = CertificateAuditEvent::where('certificate_id', $cert->id)
            ->where('event_type', 'issued')
            ->first();
        $this->assertNotNull($auditEvent);
    }

    #[DataProvider('engineProvider')]
    public function test_automated_issuance_on_course_completion_when_grade_already_published(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        // Create student C with active (incomplete) enrollment
        $studentC = User::create([
            'name' => 'Charlie Brown',
            'email' => 'charlie-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $studentC->assignRole('student');
        $profileC = StudentProfile::create([
            'academy_id' => $fx['academy']->id,
            'user_id' => $studentC->id,
            'student_code' => 'STU-'.Str::random(6),
        ]);
        $enrollmentC = Enrollment::create([
            'academy_id' => $fx['academy']->id,
            'student_profile_id' => $profileC->id,
            'course_id' => $fx['course']->id,
            'status' => Enrollment::STATUS_ACTIVE, // Not yet completed
            'enrolled_at' => now()->toDateString(),
        ]);
        DB::table('assessment_attempts')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $fx['quiz']->id,
            'enrollment_id' => $enrollmentC->id,
            'user_id' => $studentC->id,
            'attempt_number' => 1,
            'status' => 'evaluated',
            'started_at' => now(),
            'total_points' => '100.00',
            'earned_points' => '85.00',
            'score_percentage' => '85.00',
            'passed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $calcService = app(GradebookCalculationService::class);
        $certService = app(CertificateIssuanceService::class);

        // 1. Grade published first while enrollment is still incomplete
        $calcService->publishEnrollmentGrade($enrollmentC, $fx['admin'], 'idemp_early_grade', 'Early passing grade');
        $this->assertNull($certService->getCertificateForEnrollment($enrollmentC->fresh()));

        // 2. Course subsequently completed -> triggers issuance
        $enrollmentC->update([
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        $cert = $certService->issueCertificate($enrollmentC->fresh());
        $this->assertNotNull($cert);
        $this->assertEquals(Certificate::STATUS_ISSUED, $cert->status);
    }

    #[DataProvider('engineProvider')]
    public function test_withdrawing_published_grade_flags_certificate_for_audit(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $calcService = app(GradebookCalculationService::class);
        $certService = app(CertificateIssuanceService::class);

        // Publish grade and issue certificate
        $calcService->publishEnrollmentGrade($fx['enrollmentA'], $fx['admin'], 'idemp_withdraw_cert', 'Initial published grade');
        $cert = $certService->getCertificateForEnrollment($fx['enrollmentA']->fresh());
        $this->assertTrue($cert->isIssued());

        // Withdraw grade
        $calcService->withdrawEnrollmentGrade($fx['enrollmentA'], $fx['admin'], 'Academic integrity audit');

        // Certificate must immediately transition to under audit
        $cert = $cert->fresh();
        $this->assertTrue($cert->isUnderAudit());
        $this->assertFalse($cert->isIssued());
        $this->assertEquals(Certificate::STATUS_UNDER_AUDIT, $cert->status);

        // Public verification must show under audit, NOT valid
        $response = $this->get('/certificates/verify/'.$cert->verification_code);
        $response->assertStatus(200);
        $response->assertSee('UNDER ACADEMIC REVIEW');
        $response->assertDontSee('VALID CREDENTIAL');

        // Download must be rejected with 403
        $this->actingAs($fx['studentA']);
        $downloadResponse = $this->get('/certificates/'.$cert->id.'/download');
        $downloadResponse->assertStatus(403);
    }

    #[DataProvider('engineProvider')]
    public function test_public_verification_privacy_masking_and_explicit_consent(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $calcService = app(GradebookCalculationService::class);
        $certService = app(CertificateIssuanceService::class);

        $calcService->publishEnrollmentGrade($fx['enrollmentA'], $fx['admin'], 'idemp_consent_test', 'Published grade');
        $cert = $certService->getCertificateForEnrollment($fx['enrollmentA']->fresh());

        // 1. Default: Identity is masked (no explicit consent)
        $this->assertFalse((bool) $cert->public_identity_consent);
        $response = $this->get('/certificates/verify/'.$cert->verification_code);
        $response->assertStatus(200);
        $response->assertSee('VALID CREDENTIAL');
        $response->assertSee('J*** D**'); // Masked name for John Doe
        $response->assertDontSee('John Doe'); // Raw name hidden

        // 2. Student explicitly grants consent
        $certService->setPublicIdentityConsent($cert, $fx['studentA'], true);
        $this->assertTrue((bool) $cert->fresh()->public_identity_consent);

        $consentResponse = $this->get('/certificates/verify/'.$cert->verification_code);
        $consentResponse->assertStatus(200);
        $consentResponse->assertSee('John Doe'); // Unmasked name visible

        // 3. Student withdraws consent
        $certService->setPublicIdentityConsent($cert, $fx['studentA'], false);
        $this->assertFalse((bool) $cert->fresh()->public_identity_consent);

        $withdrawnResponse = $this->get('/certificates/verify/'.$cert->verification_code);
        $withdrawnResponse->assertStatus(200);
        $withdrawnResponse->assertSee('J*** D**');
        $withdrawnResponse->assertDontSee('John Doe');

        // Audit events recorded for consent
        $this->assertEquals(2, CertificateAuditEvent::where('certificate_id', $cert->id)
            ->whereIn('event_type', ['consent_granted', 'consent_withdrawn'])
            ->count());
    }

    #[DataProvider('engineProvider')]
    public function test_revocation_and_reissuance_lifecycle_and_historical_preservation(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $calcService = app(GradebookCalculationService::class);
        $certService = app(CertificateIssuanceService::class);

        $calcService->publishEnrollmentGrade($fx['enrollmentA'], $fx['admin'], 'idemp_reissue_pub', 'Published grade');
        $certV1 = $certService->getCertificateForEnrollment($fx['enrollmentA']->fresh());
        $v1Code = $certV1->verification_code;

        // 1. Revocation requires mandatory reason
        try {
            $certService->revokeCertificate($certV1, $fx['admin'], '   ');
            $this->fail('Expected InvalidArgumentException for empty revocation reason.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('mandatory reason', $e->getMessage());
        }

        // Revoke certificate v1
        $revokedV1 = $certService->revokeCertificate($certV1, $fx['admin'], 'Administrative review required');
        $this->assertTrue($revokedV1->isRevoked());

        // Public verification of revoked certificate shows revoked status
        $revResponse = $this->get('/certificates/verify/'.$v1Code);
        $revResponse->assertStatus(200);
        $revResponse->assertSee('CERTIFICATE REVOKED');

        // 2. Reissuance by administrator creates v2
        $certV2 = $certService->reissueCertificate($revokedV1, $fx['admin'], 'Correction verified and cleared');
        $this->assertEquals(2, $certV2->version);
        $this->assertEquals(Certificate::STATUS_ISSUED, $certV2->status);
        $this->assertEquals($revokedV1->id, $certV2->supersedes_id);
        $this->assertEquals($certV2->id, $revokedV1->fresh()->superseded_by_id);
        $this->assertEquals(Certificate::STATUS_SUPERSEDED, $revokedV1->fresh()->status);

        // 3. Old verification code shows SUPERSEDED / REISSUED (does not leak new code)
        $oldCodeResponse = $this->get('/certificates/verify/'.$v1Code);
        $oldCodeResponse->assertStatus(200);
        $oldCodeResponse->assertSee('SUPERSEDED / REISSUED');
        $oldCodeResponse->assertDontSee($certV2->verification_code);

        // 4. New verification code shows VALID CREDENTIAL
        $newCodeResponse = $this->get('/certificates/verify/'.$certV2->verification_code);
        $newCodeResponse->assertStatus(200);
        $newCodeResponse->assertSee('VALID CREDENTIAL');

        // 5. Non-admin cannot reissue -> 403 Forbidden
        try {
            $certService->reissueCertificate($certV2, $fx['assignedTrainer'], 'Trainer reissuance');
            $this->fail('Trainer should not be able to reissue certificate.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Only administrators', $e->getMessage());
        }
    }

    #[DataProvider('engineProvider')]
    public function test_authorization_matrix_and_idor_protection(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $calcService = app(GradebookCalculationService::class);
        $certService = app(CertificateIssuanceService::class);

        $calcService->publishEnrollmentGrade($fx['enrollmentA'], $fx['admin'], 'idemp_auth_test', 'Published grade');
        $cert = $certService->getCertificateForEnrollment($fx['enrollmentA']->fresh());

        // 1. Student A can download own certificate
        $this->actingAs($fx['studentA']);
        $respA = $this->get('/certificates/'.$cert->id.'/download');
        $respA->assertStatus(200);
        $this->assertEquals('application/pdf', $respA->headers->get('Content-Type'));

        // 2. Student B CANNOT download Student A's certificate (IDOR protection) -> 403
        $this->actingAs($fx['studentB']);
        $respB = $this->get('/certificates/'.$cert->id.'/download');
        $respB->assertStatus(403);

        // 3. Assigned Trainer can download certificate for course they instruct
        $this->actingAs($fx['assignedTrainer']);
        $respTrainer = $this->get('/certificates/'.$cert->id.'/download');
        $respTrainer->assertStatus(200);

        // 4. Unassigned Trainer CANNOT download certificate -> 403
        $this->actingAs($fx['unassignedTrainer']);
        $respUnassigned = $this->get('/certificates/'.$cert->id.'/download');
        $respUnassigned->assertStatus(403);

        // 5. Administrator can download
        $this->actingAs($fx['admin']);
        $respAdmin = $this->get('/certificates/'.$cert->id.'/download');
        $respAdmin->assertStatus(200);
    }

    #[DataProvider('engineProvider')]
    public function test_public_verification_not_found_and_token_hash_lookup(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $calcService = app(GradebookCalculationService::class);
        $certService = app(CertificateIssuanceService::class);

        $calcService->publishEnrollmentGrade($fx['enrollmentA'], $fx['admin'], 'idemp_hash_test', 'Published grade');
        $cert = $certService->getCertificateForEnrollment($fx['enrollmentA']->fresh());

        // Lookup by token hash
        $respHash = $this->get('/certificates/verify/'.$cert->verification_token_hash);
        $respHash->assertStatus(200);
        $respHash->assertSee('VALID CREDENTIAL');

        // Lookup by unknown token
        $respUnknown = $this->get('/certificates/verify/unknown-random-token-12345');
        $respUnknown->assertStatus(200);
        $respUnknown->assertSee('NOT FOUND');
        $respUnknown->assertSee('Certificate Not Found');

        // Alternate route /verify/certificate/{code} works identically
        $respAlt = $this->get('/verify/certificate/'.$cert->verification_code);
        $respAlt->assertStatus(200);
        $respAlt->assertSee('VALID CREDENTIAL');
    }
}
