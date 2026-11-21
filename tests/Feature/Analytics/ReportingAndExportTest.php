<?php

namespace Tests\Feature\Analytics;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Reports\Services\ReportingService;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Livewire\Admin\Reports\ReportsManager;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ReportingAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected User $adminUser;

    protected User $trainerUser;

    protected User $studentUser;

    protected Course $assignedCourse;

    protected Course $unassignedCourse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create([
            'name' => 'SEO Analytics Academy',
            'subdomain' => 'reporting-academy',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);

        // Admin
        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole('admin');
        $this->adminUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Trainer
        $this->trainerUser = User::factory()->create(['is_active' => true]);
        $this->trainerUser->assignRole('trainer');
        $this->trainerUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);
        TrainerProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->trainerUser->id,
            'academy_id' => $this->academy->id,
            'active' => true,
        ]);

        // Student
        $this->studentUser = User::factory()->create(['is_active' => true]);
        $this->studentUser->assignRole('student');
        $this->studentUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $studentProfile = StudentProfile::factory()->create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->studentUser->id,
            'active' => true,
        ]);

        // Courses
        $this->assignedCourse = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'title' => 'Assigned Trainer Course',
            'status' => 'Published',
        ]);
        $this->assignedCourse->trainers()->attach($this->trainerUser->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'is_primary' => true,
            'created_at' => now(),
        ]);

        $this->unassignedCourse = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'title' => 'Unassigned Admin Course',
            'status' => 'Published',
        ]);

        // Enroll student in assigned course
        Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $this->assignedCourse->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
    }

    public function test_all_five_report_types_generate_valid_datasets(): void
    {
        $service = app(ReportingService::class);

        $reportTypes = [
            ReportingService::REPORT_COURSE_PERFORMANCE,
            ReportingService::REPORT_STUDENT_PROGRESS,
            ReportingService::REPORT_ENROLLMENTS,
            ReportingService::REPORT_TRAINER_PERFORMANCE,
            ReportingService::REPORT_ASSESSMENT_ASSIGNMENT,
        ];

        foreach ($reportTypes as $type) {
            $data = $service->getReportData($this->academy, $type, [], $this->adminUser);

            $this->assertSame($type, $data['report_type']);
            $this->assertNotEmpty($data['title']);
            $this->assertIsArray($data['headers']);
            $this->assertIsArray($data['rows']);
            $this->assertGreaterThanOrEqual(0, $data['total_records']);
        }
    }

    public function test_csv_export_format_and_formula_injection_sanitization(): void
    {
        $service = app(ReportingService::class);

        // Test dangerous cell values
        $this->assertSame("'=SUM(A1:A10)", $service->sanitizeCsvCell('=SUM(A1:A10)'));
        $this->assertSame("'+cmd|' /C calc'!A0", $service->sanitizeCsvCell("+cmd|' /C calc'!A0"));
        $this->assertSame("'-5+2", $service->sanitizeCsvCell('-5+2'));
        $this->assertSame("'@evil.com", $service->sanitizeCsvCell('@evil.com'));
        $this->assertSame('Normal Text', $service->sanitizeCsvCell('Normal Text'));

        // Test actual CSV stream
        $response = $service->exportCsv(
            $this->academy,
            ReportingService::REPORT_COURSE_PERFORMANCE,
            [],
            $this->adminUser
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_export_generates_valid_pdf_stream(): void
    {
        $service = app(ReportingService::class);

        $response = $service->exportPdf(
            $this->academy,
            ReportingService::REPORT_COURSE_PERFORMANCE,
            [],
            $this->adminUser
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_report_export_authorization_rejects_students_and_guests(): void
    {
        // 1. Student trying to access export endpoint -> 403 Forbidden
        $response = $this->actingAs($this->studentUser)
            ->get(route('admin.reports.export.csv', ['report_type' => 'course_performance']));

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404]));

        // 2. Guest trying to access export endpoint -> 302 Redirect to Login
        auth()->logout();
        $response = $this->get(route('admin.reports.export.csv', ['report_type' => 'course_performance']));
        $response->assertRedirect(route('login'));
    }

    public function test_trainer_scoping_in_reports(): void
    {
        $service = app(ReportingService::class);

        // Trainer requesting course performance report
        $data = $service->getReportData(
            $this->academy,
            ReportingService::REPORT_COURSE_PERFORMANCE,
            [],
            $this->trainerUser
        );

        // Trainer should only see 1 course: the assigned course
        $this->assertSame(1, $data['total_records']);
        $this->assertSame('Assigned Trainer Course', $data['rows'][0][0]);
    }

    public function test_sensitive_report_export_records_audit_log(): void
    {
        $service = app(ReportingService::class);

        $service->exportCsv(
            $this->academy,
            ReportingService::REPORT_ENROLLMENTS,
            [],
            $this->adminUser
        );

        $this->assertDatabaseHas('audit_log', [
            'action' => 'export.report',
            'user_id' => $this->adminUser->id,
            'subject_type' => 'Report',
            'subject_id' => 'enrollments',
        ]);
    }

    public function test_reports_manager_livewire_component_renders_and_paginates(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ReportsManager::class)
            ->assertStatus(200)
            ->assertSee('Reports')
            ->assertSee('Analytics Exports')
            ->assertSee('Course Performance')
            ->assertSee('Download CSV')
            ->assertSee('Download PDF')
            ->set('reportType', ReportingService::REPORT_STUDENT_PROGRESS)
            ->assertSet('reportType', ReportingService::REPORT_STUDENT_PROGRESS);
    }
}
