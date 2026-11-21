<?php

namespace Tests\Feature\Certificates;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Certificates\Services\CertificatePdfService;
use App\Domain\Certificates\Services\CertificateTemplateService;
use App\Domain\Certificates\Services\QrCodeService;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\Certificates\CertificateDesigner;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CertificateDesignerTest extends TestCase
{
    private static bool $mysqlMigrated = false;

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
            try {
                DB::connection('mysql')->getPdo();
            } catch (\Throwable $e) {
                $this->markTestSkipped('MySQL connection not available: '.$e->getMessage());
            }

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
        Storage::fake('local');
        Storage::fake('public');
    }

    private function createAcademy(): Academy
    {
        $academy = Academy::create([
            'name' => 'SEO Mastery Academy',
            'slug' => 'seo-mastery-'.uniqid(),
            'status' => 'active',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($academy);
        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        return $academy;
    }

    private function createAdmin(Academy $academy): User
    {
        $admin = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
        $admin->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $admin;
    }

    private function createStudentWithCompletedEnrollment(Academy $academy): array
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
            'student_code' => 'STU-'.strtoupper(uniqid()),
        ]);

        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'title' => 'Technical SEO Mastery',
            'slug' => 'technical-seo-mastery-'.uniqid(),
            'status' => 'published',
            'duration_months' => 3,
        ]);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'course_id' => $course->id,
            'student_profile_id' => $profile->id,
            'status' => 'completed',
            'completed_at' => Carbon::now()->subDay(),
            'enrolled_at' => Carbon::now()->subMonths(2),
        ]);

        return [$studentUser, $profile, $course, $enrollment];
    }

    #[DataProvider('engineProvider')]
    public function test_01_template_creation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);

        $template = $templateService->createTemplate($academy, [
            'name' => 'Executive Distinction Certificate',
            'description' => 'For executive leadership programs',
            'is_default' => true,
        ], $admin);

        $this->assertDatabaseHas('certificate_templates', [
            'id' => $template->id,
            'name' => 'Executive Distinction Certificate',
            'is_default' => 1,
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('certificate_template_versions', [
            'template_id' => $template->id,
            'version_number' => 1,
            'status' => 'published',
        ]);

        $this->assertNotNull($template->current_version_id);
    }

    #[DataProvider('engineProvider')]
    public function test_02_template_editing(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Original Name'], $admin);
        $design = $template->getActiveDesignData();
        $design['canvas']['backgroundColor'] = '#f0f9ff';
        $design['canvas']['borderWidth'] = 12;

        $template->name = 'Updated Name';
        $template->design_data = $design;
        $template->save();

        $this->assertEquals('Updated Name', $template->fresh()->name);
        $this->assertEquals('#f0f9ff', $template->fresh()->getActiveDesignData()['canvas']['backgroundColor']);
        $this->assertEquals(12, $template->fresh()->getActiveDesignData()['canvas']['borderWidth']);
    }

    #[DataProvider('engineProvider')]
    public function test_03_template_duplication(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Original Template'], $admin);
        $copy = $templateService->duplicateTemplate($template, $admin);

        $this->assertNotEquals($template->id, $copy->id);
        $this->assertEquals('Original Template (Copy)', $copy->name);
        $this->assertEquals(1, $copy->versions()->count());
        $this->assertEquals($template->getActiveDesignData(), $copy->getActiveDesignData());
    }

    #[DataProvider('engineProvider')]
    public function test_04_template_publication(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Versioned Template'], $admin);
        $this->assertEquals(1, $template->versions()->count());

        $design = $template->getActiveDesignData();
        $design['canvas']['borderStyle'] = 'double';

        // Save Draft: creates draft version 2 without changing current published version
        $draftVersion = $templateService->saveDraft($template, $design, $admin, 'Draft changes');
        $this->assertEquals('draft', $draftVersion->status);
        $this->assertEquals(2, $draftVersion->version_number);
        $this->assertNotEquals($draftVersion->id, $template->fresh()->current_version_id);

        // Publish: creates published version 3 and updates current version
        $pubVersion = $templateService->publishVersion($template, $design, $admin, 'Published v3');
        $this->assertEquals('published', $pubVersion->status);
        $this->assertEquals(3, $pubVersion->version_number);
        $this->assertEquals($pubVersion->id, $template->fresh()->current_version_id);
    }

    #[DataProvider('engineProvider')]
    public function test_05_template_version_restoration(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Restorable Template'], $admin);
        $v1 = $template->currentVersion;

        // Make and publish v2 with purple background
        $designV2 = $template->getActiveDesignData();
        $designV2['canvas']['backgroundColor'] = '#581c87';
        $v2 = $templateService->publishVersion($template, $designV2, $admin, 'V2 changes');
        $this->assertEquals('#581c87', $template->fresh()->getActiveDesignData()['canvas']['backgroundColor']);

        // Restore v1
        $restored = $templateService->restoreVersion($template, $v1, $admin);
        $this->assertEquals($v1->design_data['canvas']['backgroundColor'], $restored->getActiveDesignData()['canvas']['backgroundColor']);
    }

    #[DataProvider('engineProvider')]
    public function test_06_element_addition(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        $this->actingAs($admin);
        $component = Livewire::test(CertificateDesigner::class);

        $initialCount = count($component->get('designData')['elements']);
        $component->call('addElement', 'heading');
        $component->call('addElement', 'line');
        $component->call('addElement', 'qr');

        $elements = $component->get('designData')['elements'];
        $this->assertCount($initialCount + 3, $elements);
        $this->assertEquals('text', $elements[$initialCount]['type']);
        $this->assertEquals('line', $elements[$initialCount + 1]['type']);
        $this->assertEquals('qr', $elements[$initialCount + 2]['type']);
    }

    #[DataProvider('engineProvider')]
    public function test_07_element_movement(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        $this->actingAs($admin);
        $component = Livewire::test(CertificateDesigner::class);
        $component->call('addElement', 'text', ['x' => 100, 'y' => 150]);

        $elId = $component->get('selectedElementId');
        $component->call('updateElementProperty', $elId, 'x', 240);
        $component->call('updateElementProperty', $elId, 'y', 360);

        $el = collect($component->get('designData')['elements'])->firstWhere('id', $elId);
        $this->assertEquals(240, $el['x']);
        $this->assertEquals(360, $el['y']);
    }

    #[DataProvider('engineProvider')]
    public function test_08_element_resizing(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        $this->actingAs($admin);
        $component = Livewire::test(CertificateDesigner::class);
        $component->call('addElement', 'rectangle', ['width' => 120, 'height' => 80]);

        $elId = $component->get('selectedElementId');
        $component->call('updateElementProperty', $elId, 'width', 300);
        $component->call('updateElementProperty', $elId, 'height', 200);

        $el = collect($component->get('designData')['elements'])->firstWhere('id', $elId);
        $this->assertEquals(300, $el['width']);
        $this->assertEquals(200, $el['height']);
    }

    #[DataProvider('engineProvider')]
    public function test_09_element_deletion(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        $this->actingAs($admin);
        $component = Livewire::test(CertificateDesigner::class);
        $component->call('addElement', 'text', ['content' => 'To be deleted']);

        $elId = $component->get('selectedElementId');
        $countBefore = count($component->get('designData')['elements']);

        $component->call('deleteElement', $elId);
        $countAfter = count($component->get('designData')['elements']);

        $this->assertEquals($countBefore - 1, $countAfter);
        $this->assertNull(collect($component->get('designData')['elements'])->firstWhere('id', $elId));
    }

    #[DataProvider('engineProvider')]
    public function test_10_text_formatting(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        $this->actingAs($admin);
        $component = Livewire::test(CertificateDesigner::class);
        $component->call('addElement', 'text', ['content' => 'Test Typography']);

        $elId = $component->get('selectedElementId');
        $component->call('updateElementProperty', $elId, 'fontFamily', 'Georgia');
        $component->call('updateElementProperty', $elId, 'fontSize', 32);
        $component->call('updateElementProperty', $elId, 'fontWeight', 'bold');
        $component->call('updateElementProperty', $elId, 'color', '#b45309');
        $component->call('updateElementProperty', $elId, 'textAlign', 'center');

        $el = collect($component->get('designData')['elements'])->firstWhere('id', $elId);
        $this->assertEquals('Georgia', $el['fontFamily']);
        $this->assertEquals(32, $el['fontSize']);
        $this->assertEquals('bold', $el['fontWeight']);
        $this->assertEquals('#b45309', $el['color']);
        $this->assertEquals('center', $el['textAlign']);
    }

    #[DataProvider('engineProvider')]
    public function test_11_image_uploads(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        $this->actingAs($admin);
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $component = Livewire::test(CertificateDesigner::class)
            ->set('assetType', 'logo')
            ->set('uploadedAsset', $file)
            ->call('uploadAsset');

        $elements = $component->get('designData')['elements'];
        $logoEl = collect($elements)->firstWhere('type', 'logo');

        $this->assertNotNull($logoEl);
        $this->assertNotNull($logoEl['src']);
        $this->assertStringContainsString('certificates/assets', $logoEl['src']);
    }

    #[DataProvider('engineProvider')]
    public function test_12_dynamic_field_binding(string $engine): void
    {
        $this->useEngine($engine);
        $templateService = app(CertificateTemplateService::class);

        $rawText = 'Certificate awarded to {{student_name}} for successfully completing {{course_title}} at {{academy_name}} on {{completion_date}}. Certificate ID: {{certificate_number}}.';

        $data = [
            'student_name' => 'Maria Gonzalez',
            'course_title' => 'Advanced Technical SEO',
            'academy_name' => 'Search Engine Academy',
            'completion_date' => 'November 20, 2026',
            'certificate_number' => 'CERT-9988-ABC',
        ];

        $resolved = $templateService->replacePlaceholders($rawText, $data);

        $this->assertStringNotContainsString('{{student_name}}', $resolved);
        $this->assertStringContainsString('Maria Gonzalez', $resolved);
        $this->assertStringContainsString('Advanced Technical SEO', $resolved);
        $this->assertStringContainsString('Search Engine Academy', $resolved);
        $this->assertStringContainsString('November 20, 2026', $resolved);
        $this->assertStringContainsString('CERT-9988-ABC', $resolved);
    }

    #[DataProvider('engineProvider')]
    public function test_13_missing_optional_field_handling(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $templateService = app(CertificateTemplateService::class);
        $pdfService = app(CertificatePdfService::class);

        $design = $templateService->getPresetDesign('classic', $academy);
        // Add element with optional fields that might be null or missing
        $design['elements'][] = [
            'id' => 'optional-el',
            'type' => 'text',
            'content' => 'Duration: {{course_duration}} | Score: {{final_score}} | Honors: {{honors}}',
            'x' => 100,
            'y' => 400,
            'width' => 500,
            'height' => 30,
        ];

        // Sample data without final_score or honors
        $sampleData = [
            'student_name' => 'John Doe',
            'course_title' => 'Intro to SEO',
            'course_duration' => '4 Weeks',
        ];

        $pdfBinary = $pdfService->renderDesignerPdf($design, $sampleData, $academy);
        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF', $pdfBinary);
    }

    #[DataProvider('engineProvider')]
    public function test_14_required_verification_field_protection(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $templateService = app(CertificateTemplateService::class);

        // Design missing certificate_number and verification qr
        $emptyDesign = [
            'canvas' => ['width' => 842, 'height' => 595],
            'elements' => [
                ['id' => '1', 'type' => 'text', 'content' => 'Hello World'],
            ],
        ];

        $result = $templateService->validateVerificationBindings($emptyDesign);
        $warnings = $result['warnings'];
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('certificate_number', implode(' ', $warnings));
        $this->assertStringContainsString('verification', implode(' ', $warnings));

        // Design WITH required verification fields
        $validDesign = [
            'canvas' => ['width' => 842, 'height' => 595],
            'elements' => [
                ['id' => '1', 'type' => 'text', 'content' => 'Number: {{certificate_number}}'],
                ['id' => '2', 'type' => 'qr', 'content' => '{{verification_qr}}'],
            ],
        ];

        $validResult = $templateService->validateVerificationBindings($validDesign);
        $this->assertEmpty($validResult['warnings']);
    }

    #[DataProvider('engineProvider')]
    public function test_15_preview_generation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Preview Test'], $admin);

        $this->actingAs($admin);
        $response = $this->get(route('admin.certificates.designer.preview-pdf', $template->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    #[DataProvider('engineProvider')]
    public function test_16_preview_without_certificate_issuance(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Safe Preview'], $admin);

        $initialCertCount = Certificate::count();

        $this->actingAs($admin);
        $this->get(route('admin.certificates.designer.preview-pdf', $template->id));
        $this->get(route('admin.certificates.designer.download-pdf', $template->id));

        $this->assertEquals($initialCertCount, Certificate::count());
    }

    #[DataProvider('engineProvider')]
    public function test_17_template_selection_during_new_issuance(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);
        $issuanceService = app(CertificateIssuanceService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Gold Standard'], $admin);
        [$studentUser, $profile, $course, $enrollment] = $this->createStudentWithCompletedEnrollment($academy);

        $certificate = $issuanceService->issueCertificate($enrollment, (string) $template->id);

        $this->assertNotNull($certificate);
        $this->assertEquals($template->id, $certificate->certificate_template_id);
        $this->assertEquals($template->current_version_id, $certificate->certificate_template_version_id);
        $this->assertNotNull($certificate->issuance_snapshot['template_design']);
    }

    #[DataProvider('engineProvider')]
    public function test_18_historical_certificate_snapshot_preservation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);
        $issuanceService = app(CertificateIssuanceService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Snapshot Template'], $admin);
        [$studentUser, $profile, $course, $enrollment] = $this->createStudentWithCompletedEnrollment($academy);

        $cert = $issuanceService->issueCertificate($enrollment, (string) $template->id);
        $originalSnapshot = $cert->issuance_snapshot;

        // Modify the template and publish v2 with completely different colors and background
        $designV2 = $template->getActiveDesignData();
        $designV2['canvas']['backgroundColor'] = '#ff0000';
        $designV2['canvas']['borderColor'] = '#00ff00';
        $templateService->publishVersion($template, $designV2, $admin, 'Breaking v2 update');

        $certFresh = $cert->fresh();
        $this->assertEquals($originalSnapshot, $certFresh->issuance_snapshot);
        $this->assertNotEquals('#ff0000', $certFresh->issuance_snapshot['template_design']['canvas']['backgroundColor']);
    }

    #[DataProvider('engineProvider')]
    public function test_19_unauthorized_template_modification(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Protected Template'], $admin);

        // Student attempt
        $student = User::factory()->create(['is_active' => true]);
        $student->assignRole('student');
        $student->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $this->actingAs($student);

        $response = $this->get(route('admin.certificates.designer', $template->id));
        $this->assertTrue(in_array($response->status(), [403, 302]));

        $previewResponse = $this->get(route('admin.certificates.designer.preview-pdf', $template->id));
        $this->assertEquals(403, $previewResponse->status());

        // Trainer attempt
        $trainer = User::factory()->create(['is_active' => true]);
        $trainer->assignRole('trainer');
        $trainer->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $this->actingAs($trainer);

        $trainerResponse = $this->get(route('admin.certificates.designer', $template->id));
        $this->assertTrue(in_array($trainerResponse->status(), [403, 302]));
    }

    #[DataProvider('engineProvider')]
    public function test_20_pdf_coordinate_and_rendering_accuracy(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $pdfService = app(CertificatePdfService::class);

        $customDesign = [
            'canvas' => [
                'width' => 842,
                'height' => 595,
                'orientation' => 'landscape',
                'pageSize' => 'A4',
                'backgroundColor' => '#fafafa',
                'borderWidth' => 8,
                'borderColor' => '#1e3a8a',
                'borderStyle' => 'solid',
                'borderInset' => 25,
            ],
            'elements' => [
                [
                    'id' => 'exact-el-1',
                    'type' => 'text',
                    'x' => 150,
                    'y' => 220,
                    'width' => 542,
                    'height' => 60,
                    'fontSize' => 24,
                    'color' => '#0f172a',
                    'content' => 'Precision Coordinate Test',
                ],
            ],
        ];

        $html = view('certificates.templates.designer', [
            'canvas' => $customDesign['canvas'],
            'elements' => $customDesign['elements'],
            'data' => ['course_title' => 'Test'],
            'academy' => $academy,
            'isPdf' => true,
        ])->render();

        $this->assertStringContainsString('left: 150pt', $html);
        $this->assertStringContainsString('top: 220pt', $html);
        $this->assertStringContainsString('width: 542pt', $html);
        $this->assertStringContainsString('height: 60pt', $html);
        $this->assertStringContainsString('border-width: 8pt', $html);

        $pdfBinary = $pdfService->renderDesignerPdf($customDesign, ['course_title' => 'Test'], $academy);
        $this->assertStringStartsWith('%PDF', $pdfBinary);
    }

    #[DataProvider('engineProvider')]
    public function test_21_qr_code_generation(string $engine): void
    {
        $this->useEngine($engine);
        $qrService = app(QrCodeService::class);

        $url = 'https://seo-academy.local/verify/CERT-12345';

        $svg = $qrService->generateSvg($url, 150);
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);

        $pngDataUri = $qrService->generatePngDataUri($url, 150);
        $this->assertStringStartsWith('data:image/png;base64,', $pngDataUri);

        $base64 = substr($pngDataUri, strlen('data:image/png;base64,'));
        $rawPng = base64_decode($base64);
        $this->assertNotEmpty($rawPng);
        // PNG magic bytes
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $rawPng);
    }

    #[DataProvider('engineProvider')]
    public function test_22_certificate_verification(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);
        $issuanceService = app(CertificateIssuanceService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Verifiable Design'], $admin);
        [$studentUser, $profile, $course, $enrollment] = $this->createStudentWithCompletedEnrollment($academy);

        $certificate = $issuanceService->issueCertificate($enrollment, (string) $template->id);

        $verifyCode = $certificate->getDecryptedVerificationCode() ?: ($certificate->verification_code ?: $certificate->certificate_number);
        $response = $this->get(route('certificates.verify', ['code' => $verifyCode]));
        $response->assertStatus(200);
        $response->assertSee($course->title);
    }

    #[DataProvider('engineProvider')]
    public function test_23_revocation_and_reissue_regression(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $templateService = app(CertificateTemplateService::class);
        $issuanceService = app(CertificateIssuanceService::class);

        $template = $templateService->createTemplate($academy, ['name' => 'Lifecycle Template'], $admin);
        [$studentUser, $profile, $course, $enrollment] = $this->createStudentWithCompletedEnrollment($academy);

        $cert = $issuanceService->issueCertificate($enrollment, (string) $template->id);
        $this->assertEquals(Certificate::STATUS_ISSUED, $cert->status);

        // Revoke
        $revokedCert = $issuanceService->revokeCertificate($cert, $admin, 'Academic dispute');
        $this->assertEquals(Certificate::STATUS_REVOKED, $revokedCert->status);

        // Reissue
        $newCert = $issuanceService->reissueCertificate($revokedCert, $admin, 'Dispute resolved in student favor', (string) $template->id);

        $this->assertEquals(Certificate::STATUS_ISSUED, $newCert->status);
        $this->assertEquals(Certificate::STATUS_SUPERSEDED, $revokedCert->fresh()->status);
        $this->assertEquals($template->id, $newCert->certificate_template_id);
        $this->assertEquals(2, $newCert->version);
    }
}
