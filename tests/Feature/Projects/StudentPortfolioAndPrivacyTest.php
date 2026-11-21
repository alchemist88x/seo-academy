<?php

namespace Tests\Feature\Projects;

use App\Domain\Academies\Models\Academy;
use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\ProjectEvaluation;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Projects\Services\PracticalProjectService;
use App\Domain\Projects\Services\ProjectAnalyticsService;
use App\Domain\Projects\Services\ProjectEvaluationService;
use App\Domain\Projects\Services\ProjectSubmissionService;
use App\Domain\Projects\Services\StudentPortfolioService;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentPortfolioAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected User $adminUser;

    protected User $studentA;

    protected StudentProfile $profileA;

    protected User $studentB;

    protected StudentProfile $profileB;

    protected PracticalProject $project;

    protected ProjectSubmission $approvedSubmissionA;

    protected ProjectSubmission $unapprovedSubmissionA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create([
            'name' => 'SEO Portfolio Academy',
            'subdomain' => 'seo-portfolio',
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

        // Student A
        $this->studentA = User::factory()->create(['is_active' => true, 'name' => 'Alice SEO Specialist']);
        $this->studentA->assignRole('student');
        $this->studentA->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $this->profileA = StudentProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->studentA->id,
            'academy_id' => $this->academy->id,
            'student_code' => 'STU-PORT-01',
            'active' => true,
        ]);

        // Student B
        $this->studentB = User::factory()->create(['is_active' => true, 'name' => 'Bob Junior']);
        $this->studentB->assignRole('student');
        $this->studentB->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $this->profileB = StudentProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->studentB->id,
            'academy_id' => $this->academy->id,
            'student_code' => 'STU-PORT-02',
            'active' => true,
        ]);

        // Project
        $projectService = app(PracticalProjectService::class);
        $this->project = $projectService->createProject($this->academy, $this->adminUser, [
            'title' => 'Technical Crawl & Log File Analysis',
            'category' => PracticalProject::CATEGORY_TECHNICAL_SEO,
            'difficulty' => PracticalProject::DIFFICULTY_EXPERT,
            'status' => PracticalProject::STATUS_PUBLISHED,
        ]);

        // Approved Submission for Student A
        $subService = app(ProjectSubmissionService::class);
        $evalService = app(ProjectEvaluationService::class);

        $this->approvedSubmissionA = $subService->submit($this->project, $this->studentA, [
            'website_url' => 'https://alice-portfolio-target.com',
            'content' => 'Comprehensive server log audit identifying search bot crawl waste.',
        ]);

        $evalService->evaluate($this->approvedSubmissionA, $this->adminUser, [
            'decision' => ProjectEvaluation::STATUS_APPROVED,
            'points_awarded' => 95.0,
            'trainer_feedback' => 'Flawless server log analysis.',
        ]);
        $this->approvedSubmissionA->refresh();

        // Unapproved Submission
        $project2 = $projectService->createProject($this->academy, $this->adminUser, [
            'title' => 'Unfinished Keyword Research Project',
            'category' => PracticalProject::CATEGORY_KEYWORD_RESEARCH,
            'status' => PracticalProject::STATUS_PUBLISHED,
        ]);
        $this->unapprovedSubmissionA = $subService->submit($project2, $this->studentA, [
            'content' => 'Rough draft keyword list.',
        ]);
    }

    public function test_portfolio_is_private_by_default(): void
    {
        $portfolioService = app(StudentPortfolioService::class);
        $portfolio = $portfolioService->getOrCreatePortfolio($this->profileA);

        $this->assertFalse($portfolio->is_public);
        $this->assertNull($portfolio->public_token);
        $this->assertFalse($portfolio->public_identity_consent);

        // Guest attempting to access random token gets 404
        $this->get('/portfolio/nonexistent-token-123456789012345678901234567890123456789012345678901234')
            ->assertNotFound();
    }

    public function test_public_portfolio_token_generation_and_access(): void
    {
        $portfolioService = app(StudentPortfolioService::class);
        $portfolio = $portfolioService->getOrCreatePortfolio($this->profileA);

        // Add approved project
        $portfolioService->addProjectToPortfolio($portfolio, $this->approvedSubmissionA, [
            'title' => 'Enterprise Server Log Audit Case Study',
            'case_study' => 'Saved 42% of crawl budget across 50,000 indexed pages.',
            'skills_demonstrated' => ['Log Analysis', 'Crawl Budget', 'Python'],
        ]);

        // Enable public sharing with explicit consent
        $token = $portfolioService->enablePublicSharing($portfolio, true);
        $portfolio->refresh();

        $this->assertTrue($portfolio->is_public);
        $this->assertEquals(64, strlen($token));
        $this->assertTrue($portfolio->public_identity_consent);

        // Public guest can now view verified portfolio
        $response = $this->get(route('portfolio.public', $token));
        $response->assertOk();
        $response->assertSee('Alice SEO Specialist');
        $response->assertSee('Enterprise Server Log Audit Case Study');
        $response->assertSee('Crawl Budget');

        // Does NOT expose internal profile IDs
        $response->assertDontSee($this->profileA->id);
    }

    public function test_revoking_public_portfolio_immediately_denies_access(): void
    {
        $portfolioService = app(StudentPortfolioService::class);
        $portfolio = $portfolioService->getOrCreatePortfolio($this->profileA);
        $token = $portfolioService->enablePublicSharing($portfolio, true);

        // Active
        $this->get(route('portfolio.public', $token))->assertOk();

        // Revoke
        $portfolioService->revokePublicSharing($portfolio);
        $portfolio->refresh();

        $this->assertFalse($portfolio->is_public);
        $this->assertNull($portfolio->public_token);

        // Immediate 404 on subsequent requests
        $this->get(route('portfolio.public', $token))->assertNotFound();
    }

    public function test_unauthorized_portfolio_inclusion_prevented(): void
    {
        $portfolioService = app(StudentPortfolioService::class);
        $portfolioA = $portfolioService->getOrCreatePortfolio($this->profileA);

        // 1. Cannot add unapproved project
        $this->expectException(DomainException::class);
        $portfolioService->addProjectToPortfolio($portfolioA, $this->unapprovedSubmissionA);
    }

    public function test_student_cannot_add_another_students_project_to_portfolio(): void
    {
        $portfolioService = app(StudentPortfolioService::class);
        $portfolioB = $portfolioService->getOrCreatePortfolio($this->profileB);

        // Student B tries to add Student A's approved submission
        $this->expectException(DomainException::class);
        $portfolioService->addProjectToPortfolio($portfolioB, $this->approvedSubmissionA);
    }

    public function test_portfolio_pdf_export_authorized_for_owner(): void
    {
        $portfolioService = app(StudentPortfolioService::class);
        $portfolio = $portfolioService->getOrCreatePortfolio($this->profileA);
        $portfolioService->addProjectToPortfolio($portfolio, $this->approvedSubmissionA);

        // Student A can export their portfolio as PDF
        $response = $this->actingAs($this->studentA)
            ->get(route('student.portfolio.export.pdf'));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_project_analytics_calculations(): void
    {
        $analyticsService = app(ProjectAnalyticsService::class);
        $kpis = $analyticsService->getAcademyProjectKpis($this->academy);

        $this->assertGreaterThanOrEqual(1, $kpis['total_projects']);
        $this->assertGreaterThanOrEqual(1, $kpis['total_submissions']);
        $this->assertGreaterThanOrEqual(1, $kpis['approved_submissions']);
        $this->assertGreaterThanOrEqual(1, $kpis['total_achievements_issued']);
        $this->assertIsFloat($kpis['completion_rate_percent']);
        $this->assertIsFloat($kpis['average_score_percent']);
    }
}
