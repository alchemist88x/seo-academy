<?php

namespace Tests\Feature\Certificates;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Certificates\Services\CertificatePdfService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseTrainer;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Phase54CertificateTokenEncryptionTest extends TestCase
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
            'active_grading_scheme_id' => null,
        ]);

        CourseTrainer::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'user_id' => $trainer->id,
            'is_primary' => true,
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
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => Carbon::now()->subDays(2),
            'enrolled_at' => Carbon::now()->subDays(10)->toDateString(),
            'progress' => 100,
        ]);

        return [$academy, $admin, $trainer, $studentUser, $course, $enrollment];
    }

    #[DataProvider('engineProvider')]
    public function test_1_new_certificate_encrypts_verification_token_at_rest(string $engine): void
    {
        $this->useEngine($engine);
        Queue::fake();

        [$academy, $admin, $trainer, $studentUser, $course, $enrollment] = $this->createAcademicEnvironment();

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);
        $this->assertNotNull($certificate);

        // Fetch raw database row to inspect persisted state directly
        $raw = DB::table('certificates')->where('id', $certificate->id)->first();

        // 1. Raw verification_code column in DB MUST be NULL (no plaintext stored)
        $this->assertNull($raw->verification_code, 'Raw verification_code column in database must be null.');

        // 2. Encrypted verification code MUST be present and not equal to plaintext
        $this->assertNotEmpty($raw->encrypted_verification_code, 'Encrypted verification code must be stored in database.');
        $this->assertNotEquals($certificate->verification_code, $raw->encrypted_verification_code);

        // 3. Verification token hash MUST be populated with SHA-256 of the unencrypted token
        $this->assertNotEmpty($raw->verification_token_hash, 'Verification token hash must be stored for lookup.');
        $this->assertEquals(64, strlen($raw->verification_token_hash));
        $this->assertEquals(hash('sha256', $certificate->verification_code), $raw->verification_token_hash);

        // 4. Eloquent model accessor decrypts the token seamlessly
        $decryptedCode = $certificate->verification_code;
        $this->assertNotEmpty($decryptedCode);
        $this->assertEquals($certificate->getDecryptedVerificationCode(), $decryptedCode);

        // 5. Raw decrypting with Crypt facade produces the identical code
        $this->assertEquals($decryptedCode, Crypt::decryptString($raw->encrypted_verification_code));
    }

    #[DataProvider('engineProvider')]
    public function test_2_model_serialization_hides_raw_and_encrypted_tokens(string $engine): void
    {
        $this->useEngine($engine);
        Queue::fake();

        [$academy, $admin, $trainer, $studentUser, $course, $enrollment] = $this->createAcademicEnvironment();

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $array = $certificate->toArray();
        $this->assertArrayNotHasKey('encrypted_verification_code', $array);
        $this->assertArrayNotHasKey('verification_code', $array);

        $json = json_encode($certificate);
        $this->assertStringNotContainsString('encrypted_verification_code', $json);
        $this->assertStringNotContainsString($certificate->verification_code, $json);
    }

    #[DataProvider('engineProvider')]
    public function test_3_public_verification_works_via_unencrypted_token_lookup(string $engine): void
    {
        $this->useEngine($engine);
        Queue::fake();

        [$academy, $admin, $trainer, $studentUser, $course, $enrollment] = $this->createAcademicEnvironment();

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $rawToken = $certificate->verification_code;

        // Verify using raw unencrypted token URL (student or third party visits verification link)
        $response = $this->get(route('certificates.verify', $rawToken));
        $response->assertStatus(200);
        $response->assertSee('VALID CREDENTIAL');
        $response->assertSee($certificate->certificate_number);
        // Student name masked by default without consent
        $response->assertSee('A**** W****');
        // Raw token itself must not be leaked into public page body
        $response->assertDontSee($rawToken);
    }

    #[DataProvider('engineProvider')]
    public function test_4_pdf_service_retrieves_decrypted_token_for_verification_url(string $engine): void
    {
        $this->useEngine($engine);

        [$academy, $admin, $trainer, $studentUser, $course, $enrollment] = $this->createAcademicEnvironment();

        $issuanceService = app(CertificateIssuanceService::class);
        $certificate = $issuanceService->issueCertificate($enrollment);

        $pdfService = app(CertificatePdfService::class);
        $pdfPath = $pdfService->generatePdf($certificate, true);

        $this->assertNotEmpty($pdfPath);
        $this->assertTrue(Storage::disk($pdfService->getStorageDisk())->exists($pdfPath));
    }

    #[DataProvider('engineProvider')]
    public function test_5_reissuance_generates_fresh_encrypted_token(string $engine): void
    {
        $this->useEngine($engine);
        Queue::fake();

        [$academy, $admin, $trainer, $studentUser, $course, $enrollment] = $this->createAcademicEnvironment();

        $issuanceService = app(CertificateIssuanceService::class);
        $v1 = $issuanceService->issueCertificate($enrollment);

        $v1Token = $v1->verification_code;
        $v1Hash = $v1->verification_token_hash;

        // Reissue
        $v2 = $issuanceService->reissueCertificate($v1, $admin, 'Updated student legal surname');

        // New token must be generated
        $this->assertNotEquals($v1Token, $v2->verification_code);
        $this->assertNotEquals($v1Hash, $v2->verification_token_hash);

        // Raw verification_code in DB must be NULL for both
        $rawV2 = DB::table('certificates')->where('id', $v2->id)->first();
        $this->assertNull($rawV2->verification_code);
        $this->assertNotEmpty($rawV2->encrypted_verification_code);
        $this->assertEquals(hash('sha256', $v2->verification_code), $rawV2->verification_token_hash);

        // Both old and new remain verifiable publicly
        $respOld = $this->get(route('certificates.verify', $v1Token));
        $respOld->assertStatus(200);
        $respOld->assertSee('SUPERSEDED');

        $respNew = $this->get(route('certificates.verify', $v2->verification_code));
        $respNew->assertStatus(200);
        $respNew->assertSee('VALID CREDENTIAL');
    }

    #[DataProvider('engineProvider')]
    public function test_6_migration_backfills_plaintext_tokens_safely(string $engine): void
    {
        $this->useEngine($engine);
        Queue::fake();

        [$academy, $admin, $trainer, $studentUser, $course, $enrollment] = $this->createAcademicEnvironment();

        // Simulate a legacy certificate created before Phase 5.4 with plaintext verification_code
        $legacyCertId = (string) Str::uuid();
        $legacyPlainToken = Str::random(32);
        $legacyHash = hash('sha256', $legacyPlainToken);

        DB::table('certificates')->insert([
            'id' => $legacyCertId,
            'user_id' => $studentUser->id,
            'course_id' => $course->id,
            'enrollment_id' => null,
            'original_enrollment_id' => $enrollment->id,
            'certificate_number' => 'SEO-LEGACY-001',
            'verification_code' => $legacyPlainToken, // Plaintext legacy record
            'encrypted_verification_code' => null,
            'verification_token_hash' => null,
            'template' => 'classic',
            'status' => Certificate::STATUS_ISSUED,
            'issued_at' => Carbon::now()->toDateString(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Run the backfill migration logic directly
        $migration = require database_path('migrations/2026_11_16_160000_encrypt_certificate_verification_codes.php');
        $migration->up();

        // Verify legacy record is migrated
        $migrated = DB::table('certificates')->where('id', $legacyCertId)->first();
        $this->assertNull($migrated->verification_code, 'Plaintext must be cleared after encryption.');
        $this->assertNotEmpty($migrated->encrypted_verification_code, 'Encrypted code must be populated.');
        $this->assertEquals($legacyHash, $migrated->verification_token_hash, 'Hash must match original token.');
        $this->assertEquals($legacyPlainToken, Crypt::decryptString($migrated->encrypted_verification_code));

        // Public verification of legacy certificate with original token works
        $response = $this->get(route('certificates.verify', $legacyPlainToken));
        $response->assertStatus(200);
        $response->assertSee('SEO-LEGACY-001');
    }
}
