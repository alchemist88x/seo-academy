<?php

namespace Tests\Feature\Certificates;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CertificateVerificationSecurityTest extends TestCase
{
    private static bool $mysqlMigrated = false;

    private bool $inTransaction = false;

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
        if ($this->inTransaction) {
            try {
                DB::rollBack();
            } catch (\Throwable) {
            }
            $this->inTransaction = false;
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
            Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
            $this->seed(RoleSeeder::class);
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

            if (! self::$mysqlMigrated) {
                Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
                $this->seed(RoleSeeder::class);
                self::$mysqlMigrated = true;
            }

            DB::connection('mysql')->beginTransaction();
            $this->inTransaction = true;
        }

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

    private function createStudentWithEnrollment(Academy $academy, string $enrollmentStatus = 'active', string $studentName = 'Alice Student'): array
    {
        $studentUser = User::factory()->create([
            'name' => $studentName,
            'email' => 'student.'.uniqid().'@example.com',
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
    public function test_1_student_name_is_masked_by_default(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment(
            $academy,
            'completed',
            'Johnathan Doe'
        );

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $response = $this->get(route('certificates.verify', $certificate->verification_code));

        $response->assertStatus(200);
        $response->assertSee('J**** D**');
        $response->assertDontSee('Johnathan Doe');
        $response->assertDontSee($student->email);
    }

    #[DataProvider('engineProvider')]
    public function test_2_internal_revocation_reason_is_never_exposed_publicly(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $internalReason = 'CONFIDENTIAL: Suspected academic dishonesty on final assessment audit #9914';
        $issuanceService->revokeCertificate($certificate, $admin, $internalReason);

        $response = $this->get(route('certificates.verify', $certificate->verification_code));

        $response->assertStatus(200);
        $response->assertSee('This Certificate Has Been Revoked');
        $response->assertSee('CERTIFICATE REVOKED');
        $response->assertDontSee($internalReason);
        $response->assertDontSee('Suspected academic dishonesty');
        $response->assertDontSee('CONFIDENTIAL');
    }

    #[DataProvider('engineProvider')]
    public function test_3_internal_audit_notes_and_ids_are_never_exposed_publicly(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $response = $this->get(route('certificates.verify', $certificate->verification_code));

        $response->assertStatus(200);
        // Ensure raw database UUIDs of user and enrollment are never leaked to the public DOM
        $response->assertDontSee('user_id');
        $response->assertDontSee('enrollment_id');
        $response->assertDontSee($student->id);
        $response->assertDontSee($enrollment->id);
    }

    #[DataProvider('engineProvider')]
    public function test_4_under_audit_certificate_does_not_appear_as_valid(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        // Put certificate under audit
        $certificate->update(['status' => Certificate::STATUS_UNDER_AUDIT]);

        $response = $this->get(route('certificates.verify', $certificate->verification_code));

        $response->assertStatus(200);
        $response->assertSee('UNDER ACADEMIC REVIEW');
        $response->assertSee('Certificate Under Academic Review');
        $response->assertDontSee('VALID CREDENTIAL');
        $response->assertDontSee('Verified Certificate of Completion');
    }

    #[DataProvider('engineProvider')]
    public function test_5_revoked_certificate_displays_standardized_revocation_notice(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);
        $issuanceService->revokeCertificate($certificate, $admin, 'Withdrawn due to course restructuring');

        $response = $this->get(route('certificates.verify', $certificate->verification_code));

        $response->assertStatus(200);
        $response->assertSee('CERTIFICATE REVOKED');
        $response->assertSee('This Certificate Has Been Revoked');
        $response->assertDontSee('VALID CREDENTIAL');
        $response->assertDontSee('Verified Certificate of Completion');
        $response->assertDontSee('Withdrawn due to course restructuring');
    }

    #[DataProvider('engineProvider')]
    public function test_6_reinstated_certificate_displays_reinstated_status_and_date(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);
        $issuanceService->revokeCertificate($certificate, $admin, 'Temporary revocation during academic inquiry');

        // Reinstate certificate
        $issuanceService->reinstateCertificate($certificate, $admin, 'Inquiry completed; all requirements verified');

        $response = $this->get(route('certificates.verify', $certificate->verification_code));

        $response->assertStatus(200);
        $response->assertSee('VALID (REINSTATED)');
        $response->assertSee('Verified Reinstated Certificate');
        $response->assertSee('This certificate was formally reviewed and reinstated');
        $response->assertDontSee('Temporary revocation during academic inquiry');
        $response->assertDontSee('Inquiry completed; all requirements verified');
    }

    #[DataProvider('engineProvider')]
    public function test_7_unknown_verification_code_displays_not_found(string $engine): void
    {
        $this->useEngine($engine);

        $response = $this->get(route('certificates.verify', 'NON-EXISTENT-SECURITY-CODE-XYZ'));

        $response->assertStatus(200);
        $response->assertSee('NOT FOUND');
        $response->assertSee('Certificate Not Found');
        $response->assertDontSee('VALID CREDENTIAL');
    }

    public function test_8_rate_limiting_is_configured_to_30_per_minute(): void
    {
        $route = Route::getRoutes()->getByName('certificates.verify');
        $this->assertNotNull($route, 'Route certificates.verify should exist');

        $middlewares = $route->gatherMiddleware();
        $hasThrottle30 = false;
        foreach ($middlewares as $mw) {
            if ($mw === 'throttle:30,1' || str_starts_with($mw, 'throttle:30')) {
                $hasThrottle30 = true;
                break;
            }
        }

        $this->assertTrue($hasThrottle30, 'Route must have throttle:30,1 middleware configured');
    }

    public function test_9_name_masking_handles_various_formats(): void
    {
        // 2-word standard name
        $this->assertEquals('J**** D**', Certificate::maskName('Johnathan Doe'));
        $this->assertEquals('J*** D**', Certificate::maskName('John Doe'));

        // Single word name
        $this->assertEquals('A****', Certificate::maskName('Alice'));

        // Short 2-letter word
        $this->assertEquals('A*', Certificate::maskName('Al'));

        // Single letter word
        $this->assertEquals('A***', Certificate::maskName('A'));

        // Multi-part names
        $this->assertEquals('M*** J*** W****', Certificate::maskName('Mary Jane Watson'));

        // Empty and null names
        $this->assertEquals('Verified Student', Certificate::maskName(''));
        $this->assertEquals('Verified Student', Certificate::maskName('   '));
        $this->assertEquals('Verified Student', Certificate::maskName(null));
    }

    #[DataProvider('engineProvider')]
    public function test_10_under_audit_certificate_cannot_be_downloaded_via_download_endpoint(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$student, $profile, $course, $module, $lesson, $enrollment] = $this->createStudentWithEnrollment($academy, 'completed');

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        // Under audit
        $certificate->update(['status' => Certificate::STATUS_UNDER_AUDIT]);

        $response = $this->actingAs($student)->get(route('certificates.download', $certificate));

        $response->assertStatus(403);
    }
}
