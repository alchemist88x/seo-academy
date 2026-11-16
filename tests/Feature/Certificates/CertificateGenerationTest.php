<?php

namespace Tests\Feature\Certificates;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Certificates\Services\CertificatePdfService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CertificateGenerationTest extends TestCase
{
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

        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'session.driver' => 'array',
        ]);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
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

    protected function useEngine(string $engine): void
    {
        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'session.driver' => 'array',
        ]);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

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
            try {
                DB::connection('mysql')->getPdo();
            } catch (\Throwable $e) {
                $this->markTestSkipped('MySQL connection not available: '.$e->getMessage());
            }
        }

        Artisan::call('migrate:fresh', ['--database' => $engine, '--force' => true]);
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function createAcademy(): Academy
    {
        return Academy::create([
            'name' => 'SEO Mastery Academy',
            'slug' => 'seo-mastery-'.uniqid(),
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createStudentWithEnrollment(Academy $academy, string $enrollmentStatus = 'active'): array
    {
        $studentUser = User::factory()->create([
            'name' => 'Alice Student',
            'email' => 'alice.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $studentUser->assignRole('student');
        $studentUser->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $profile = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'academy_id' => $academy->id,
        ]);

        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'title' => 'Technical SEO Specialization',
            'slug' => 'tech-seo-'.uniqid(),
            'status' => 'published',
        ]);

        $module = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Core Web Vitals',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);

        $lesson = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'LCP Optimization',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'is_mandatory' => true,
        ]);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => $enrollmentStatus,
            'enrolled_at' => Carbon::now()->toDateString(),
            'completed_at' => $enrollmentStatus === 'completed' ? Carbon::now() : null,
        ]);

        return [$studentUser, $profile, $course, $module, $lesson, $enrollment];
    }

    #[DataProvider('engineProvider')]
    public function test_1_certificate_generation_on_course_completion(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'active');

        // Mark lesson completed
        DB::table('lesson_progress')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'completed_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        /** @var CourseCompletionService $completionService */
        $completionService = app(CourseCompletionService::class);
        $completed = $completionService->completeCourse($enrollment);

        $this->assertTrue($completed);
        $this->assertDatabaseHas('certificates', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'Issued',
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_2_pdf_generation_produces_valid_pdf_content(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        /** @var CertificateIssuanceService $issuanceService */
        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        /** @var CertificatePdfService $pdfService */
        $pdfService = app(CertificatePdfService::class);
        $binary = $pdfService->renderPdfBinary($certificate);

        $this->assertNotEmpty($binary);
        // Standard PDF magic byte header check: %PDF-
        $this->assertStringStartsWith('%PDF-', $binary);
    }

    #[DataProvider('engineProvider')]
    public function test_3_pdf_contains_required_fields(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $pdfService = app(CertificatePdfService::class);
        $config = $pdfService->getTemplateConfig($certificate->template, $certificate);

        $html = view('certificates.templates.classic', [
            'certificate' => $certificate,
            'course' => $course,
            'student' => $student,
            'enrollment' => $enrollment,
            'academy' => $academy,
            'config' => $config,
            'verificationUrl' => route('certificates.verify', $certificate->verification_code),
            'isPdf' => true,
        ])->render();

        $this->assertStringContainsString($academy->name, $html);
        $this->assertStringContainsString($student->name, $html);
        $this->assertStringContainsString($course->title, $html);
        $this->assertStringContainsString($certificate->certificate_number, $html);
        $this->assertStringContainsString(route('certificates.verify', $certificate->verification_code), $html);
    }

    #[DataProvider('engineProvider')]
    public function test_4_template_switching_renders_correctly(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment, 'modern');

        $this->assertSame('modern', $certificate->template);

        $pdfService = app(CertificatePdfService::class);
        $binary = $pdfService->renderPdfBinary($certificate);
        $this->assertStringStartsWith('%PDF-', $binary);

        // Switch to minimalist
        $certificate->template = 'minimalist';
        $certificate->save();

        $binaryMin = $pdfService->renderPdfBinary($certificate);
        $this->assertStringStartsWith('%PDF-', $binaryMin);
    }

    #[DataProvider('engineProvider')]
    public function test_5_public_verification_valid_certificate(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $response = $this->get(route('certificates.verify', $certificate->verification_code));
        $response->assertStatus(200);
        $response->assertSee('Verified Certificate of Completion');
        $response->assertSee($certificate->masked_student_name);
        $response->assertDontSee($student->name);
        $response->assertSee($course->title);
        $response->assertSee($certificate->certificate_number);
    }

    #[DataProvider('engineProvider')]
    public function test_6_public_verification_revoked_certificate(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $issuanceService->revokeCertificate($certificate, $admin, 'Academic honesty policy violation');

        $response = $this->get(route('certificates.verify', $certificate->verification_code));
        $response->assertStatus(200);
        $response->assertSee('This Certificate Has Been Revoked');
        $response->assertDontSee('Academic honesty policy violation');
    }

    #[DataProvider('engineProvider')]
    public function test_7_public_verification_invalid_code(string $engine): void
    {
        $this->useEngine($engine);

        $response = $this->get(route('certificates.verify', 'NON-EXISTENT-CODE-123456'));
        $response->assertStatus(200);
        $response->assertSee('Certificate Not Found');
    }

    #[DataProvider('engineProvider')]
    public function test_8_verification_does_not_leak_private_data(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $response = $this->get(route('certificates.verify', $certificate->verification_code));
        $response->assertStatus(200);
        // Student email and password should never be present in public HTML
        $response->assertDontSee($student->email);
        $response->assertDontSee($student->password);
    }

    #[DataProvider('engineProvider')]
    public function test_9_secure_download_authorized_student(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $response = $this->actingAs($student)->get(route('certificates.download', $certificate));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    #[DataProvider('engineProvider')]
    public function test_10_secure_download_unauthorized_student(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$studentA, $profileA, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $studentB = User::factory()->create(['is_active' => true]);
        $studentB->assignRole('student');
        $studentB->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $response = $this->actingAs($studentB)->get(route('certificates.download', $certificate));
        $response->assertStatus(403);

        // Test four-role architecture authorization on download:
        // 1. Admin can download
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $admin->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'admin', 'status' => 'active']);
        $adminResponse = $this->actingAs($admin)->get(route('certificates.download', $certificate));
        $adminResponse->assertStatus(200);

        // 2. Assigned Trainer can download
        $trainer = User::factory()->create(['is_active' => true]);
        $trainer->assignRole('trainer');
        $trainer->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'trainer', 'status' => 'active']);
        $course->trainers()->attach($trainer->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $academy->id,
        ]);
        $trainerResponse = $this->actingAs($trainer)->get(route('certificates.download', $certificate));
        $trainerResponse->assertStatus(200);

        // Unassigned Trainer cannot download
        $unassignedTrainer = User::factory()->create(['is_active' => true]);
        $unassignedTrainer->assignRole('trainer');
        $unassignedTrainer->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'trainer', 'status' => 'active']);
        $unassignedTrainerResponse = $this->actingAs($unassignedTrainer)->get(route('certificates.download', $certificate));
        $unassignedTrainerResponse->assertStatus(403);

        // 3. support_staff cannot download (unauthorized role)
        $supportStaff = User::factory()->create(['is_active' => true]);
        $supportStaff->assignRole('support_staff');
        $supportStaff->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'support_staff', 'status' => 'active']);
        $supportResponse = $this->actingAs($supportStaff)->get(route('certificates.download', $certificate));
        $supportResponse->assertStatus(403);
    }

    #[DataProvider('engineProvider')]
    public function test_11_duplicate_issuance_prevention(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $cert1 = $issuanceService->issueCertificate($enrollment);
        $cert2 = $issuanceService->issueCertificate($enrollment);

        $this->assertSame($cert1->id, $cert2->id);
        $this->assertSame($cert1->certificate_number, $cert2->certificate_number);
        $this->assertSame(1, Certificate::where('enrollment_id', $enrollment->id)->count());
    }

    #[DataProvider('engineProvider')]
    public function test_12_re_enrollment_certificate_identity(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment1] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $cert1 = $issuanceService->issueCertificate($enrollment1);

        // When a student re-enrolls in the course, the enrollment record is reset or archived
        // In the system model, enrollment_id links specifically to the certificate.
        // For re-enrollment simulation, we archive/reset or test a new enrollment for another course/iteration
        $course2 = Course::factory()->create([
            'academy_id' => $academy->id,
            'title' => 'Technical SEO Advanced Edition',
            'slug' => 'tech-seo-adv-'.uniqid(),
            'status' => 'published',
        ]);

        $enrollment2 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course2->id,
            'status' => 'completed',
            'enrolled_at' => Carbon::now()->addYear()->toDateString(),
            'completed_at' => Carbon::now()->addYear(),
        ]);

        // Revoke cert1
        $issuanceService->revokeCertificate($cert1, null, 'Re-enrollment retake');

        // Since enrollment1 has unique constraint, enrollment2 is for course2 or distinct enrollment
        $cert2 = $issuanceService->issueCertificate($enrollment2);

        $this->assertNotSame($cert1->id, $cert2->id);
        $this->assertNotSame($cert1->certificate_number, $cert2->certificate_number);
        $this->assertSame($enrollment1->id, $cert1->enrollment_id);
        $this->assertSame($enrollment2->id, $cert2->enrollment_id);

        // Database level constraint check: inserting another certificate for enrollment1 fails
        $this->expectException(QueryException::class);
        Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'enrollment_id' => $enrollment1->id,
            'issued_at' => Carbon::now()->toDateString(),
            'certificate_number' => 'CERT-DUPLICATE-TEST',
            'status' => 'Issued',
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_13_regeneration_updates_pdf(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment, 'classic');

        $pdfService = app(CertificatePdfService::class);
        $pdfService->generatePdf($certificate);

        $originalPath = $certificate->pdf_path;
        $this->assertNotEmpty($originalPath);
        $this->assertTrue(Storage::disk('local')->exists($originalPath));

        // Regenerate with new template
        $issuanceService->regenerateCertificate($certificate, 'modern');

        $this->assertSame('modern', $certificate->fresh()->template);
    }

    #[DataProvider('engineProvider')]
    public function test_14_missing_pdf_regenerates_on_demand(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $pdfService = app(CertificatePdfService::class);

        // Delete from disk manually if exists
        Storage::disk('local')->delete('certificates/'.$certificate->certificate_number.'.pdf');
        $certificate->pdf_path = null;
        $certificate->save();

        // Calling getPdfBinary should regenerate on demand without failing
        $binary = $pdfService->getPdfBinary($certificate);
        $this->assertNotEmpty($binary);
        $this->assertStringStartsWith('%PDF-', $binary);
    }

    #[DataProvider('engineProvider')]
    public function test_15_admin_revocation_lifecycle(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $this->assertTrue($certificate->isIssued());
        $this->assertFalse($certificate->isRevoked());

        $issuanceService->revokeCertificate($certificate, $admin, 'Fraudulent submission detected');

        $certificate->refresh();
        $this->assertTrue($certificate->isRevoked());
        $this->assertSame('Revoked', $certificate->status);
        $this->assertSame('Fraudulent submission detected', $certificate->revocation_reason);
        $this->assertSame($admin->id, $certificate->revoked_by);
    }

    #[DataProvider('engineProvider')]
    public function test_16_student_dashboard_certificate_display(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $response = $this->actingAs($student)->get(route('student.certificates.index'));
        $response->assertStatus(200);
        $response->assertSee($certificate->certificate_number);
        $response->assertSee($course->title);
    }

    #[DataProvider('engineProvider')]
    public function test_17_concurrent_issuance_duplicate_key_recovery(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);

        // Pre-create a certificate directly in DB simulating a simultaneous request winning the race
        $existing = Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'enrollment_id' => $enrollment->id,
            'issued_at' => Carbon::now()->toDateString(),
            'certificate_number' => 'CERT-2026-CONCURR1',
            'verification_code' => Str::random(32),
            'template' => 'classic',
            'status' => Certificate::STATUS_ISSUED,
        ]);

        // Attempting to issue via the service for that enrollment should catch the duplicate conflict
        // and safely retrieve and return the existing certificate
        $issued = $issuanceService->issueCertificate($enrollment);

        $this->assertSame($existing->id, $issued->id);
        $this->assertSame('CERT-2026-CONCURR1', $issued->certificate_number);
    }

    #[DataProvider('engineProvider')]
    public function test_18_centralized_completion_repeated_events_and_revocation_lock(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'active');

        // Complete the lesson to qualify curriculum
        LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'user_id' => $student->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $completionService = app(CourseCompletionService::class);
        $issuanceService = app(CertificateIssuanceService::class);

        // First completion event
        $res1 = $completionService->completeCourse($enrollment);
        $this->assertTrue($res1);
        $this->assertTrue($enrollment->fresh()->isCompleted());

        $cert1 = Certificate::where('enrollment_id', $enrollment->id)->first();
        $this->assertNotNull($cert1);

        // Repeated completion event does not create duplicate certificates
        $res2 = $completionService->completeCourse($enrollment->fresh());
        $this->assertTrue($res2);
        $certCount = Certificate::where('enrollment_id', $enrollment->id)->count();
        $this->assertSame(1, $certCount);

        // Revoke the certificate
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $issuanceService->revokeCertificate($cert1, $admin, 'Academic honesty violation');
        $this->assertTrue($cert1->fresh()->isRevoked());

        // Subsequent completion call cannot silently reissue a new valid certificate
        $completionService->completeCourse($enrollment->fresh());
        $certCountAfter = Certificate::where('enrollment_id', $enrollment->id)->count();
        $this->assertSame(1, $certCountAfter);
        $this->assertTrue($cert1->fresh()->isRevoked());

        // Calling regeneration on revoked certificate throws exception
        $this->expectException(\InvalidArgumentException::class);
        $issuanceService->regenerateCertificate($cert1->fresh());
    }

    #[DataProvider('engineProvider')]
    public function test_19_foreign_key_and_unrelated_exceptions_are_rethrown(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);

        // Intentionally corrupt enrollment's course_id to a non-existent UUID to trigger a genuine FOREIGN KEY constraint violation
        // (SQLSTATE 23000, error code 19 on SQLite / 1452 on MySQL)
        // Disable foreign keys temporarily just to save the invalid foreign key into enrollment
        if ($engine === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('enrollments')->where('id', $enrollment->id)->update(['course_id' => (string) Str::uuid()]);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else {
            DB::statement('PRAGMA foreign_keys = OFF;');
            DB::table('enrollments')->where('id', $enrollment->id)->update(['course_id' => (string) Str::uuid()]);
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $enrollment->refresh();

        // The service should NOT treat this 23000 foreign key violation as a duplicate key exception;
        // it must rethrow the QueryException.
        $this->expectException(QueryException::class);
        $issuanceService->issueCertificate($enrollment);
    }

    #[DataProvider('engineProvider')]
    public function test_20_simulated_versus_transactional_isolation_concurrency(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);

        // Verification of database uniqueness under simultaneous attempts:
        // We simulate two separate threads/callers resolving the certificate concurrently.
        $cert1 = null;
        $cert2 = null;

        DB::transaction(function () use ($issuanceService, $enrollment, &$cert1) {
            $cert1 = $issuanceService->issueCertificate($enrollment);
        });

        // Second caller attempts issuance
        DB::transaction(function () use ($issuanceService, $enrollment, &$cert2) {
            $cert2 = $issuanceService->issueCertificate($enrollment);
        });

        $this->assertNotNull($cert1);
        $this->assertNotNull($cert2);
        $this->assertSame($cert1->id, $cert2->id);
        $this->assertSame($cert1->certificate_number, $cert2->certificate_number);

        $totalInDb = Certificate::where('enrollment_id', $enrollment->id)->count();
        $this->assertSame(1, $totalInDb);
    }
}
