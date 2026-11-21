<?php

namespace Tests\Feature\Students;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Models\ProficiencyLevel;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Services\ProficiencyLevelService;
use App\Domain\Students\Services\StudentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\Settings\ProficiencyLevelsManager;
use App\Livewire\Admin\StudentsIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DynamicProficiencyLevelManagementTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::create([
            'name' => 'SEO Academy Dynamic Proficiency Test',
            'slug' => 'seo-academy-dynamic-prof-'.Str::random(6),
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Manager',
            'email' => 'admin.manager@example.com',
            'is_active' => true,
        ]);
        $this->admin->assignRole('admin');
        $this->admin->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->withSession([TenantContext::SESSION_KEY => $this->academy->id]);
        app(TenantContext::class)->setAcademy($this->academy);
    }

    /**
     * Requirement 1: Create a custom proficiency level.
     */
    public function test_1_create_custom_proficiency_level(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProficiencyLevelsManager::class)
            ->call('openCreateModal')
            ->set('name', 'Master Strategist')
            ->set('slug', 'master_strategist')
            ->set('description', 'High-level campaign strategist and technical architect.')
            ->set('display_order', 4)
            ->set('color', 'emerald')
            ->set('is_active', true)
            ->call('saveLevel')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $level = ProficiencyLevel::where('academy_id', $this->academy->id)
            ->where('slug', 'master_strategist')
            ->firstOrFail();

        $this->assertSame('Master Strategist', $level->name);
        $this->assertSame('High-level campaign strategist and technical architect.', $level->description);
        $this->assertSame(4, $level->display_order);
        $this->assertSame('emerald', $level->color);
        $this->assertTrue($level->is_active);
    }

    /**
     * Requirement 2: Edit a proficiency level.
     */
    public function test_2_edit_proficiency_level(): void
    {
        $service = app(ProficiencyLevelService::class);
        $level = $service->create($this->academy, [
            'name' => 'Apprentice',
            'slug' => 'apprentice',
            'description' => 'Initial trainee.',
            'display_order' => 10,
            'color' => 'blue',
        ], $this->admin);

        Livewire::actingAs($this->admin)
            ->test(ProficiencyLevelsManager::class)
            ->call('openEditModal', $level->id)
            ->assertSet('name', 'Apprentice')
            ->set('name', 'Senior Apprentice')
            ->set('description', 'Experienced trainee with campaign work.')
            ->set('color', 'cyan')
            ->call('saveLevel')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $level->refresh();
        $this->assertSame('Senior Apprentice', $level->name);
        $this->assertSame('Experienced trainee with campaign work.', $level->description);
        $this->assertSame('cyan', $level->color);
    }

    /**
     * Requirement 3: Change display order.
     */
    public function test_3_change_display_order(): void
    {
        $service = app(ProficiencyLevelService::class);
        $service->ensureDefaultLevels($this->academy);

        $levels = ProficiencyLevel::where('academy_id', $this->academy->id)->ordered()->get();
        $first = $levels[0];
        $second = $levels[1];

        // Move first level down
        Livewire::actingAs($this->admin)
            ->test(ProficiencyLevelsManager::class)
            ->call('moveDown', $first->id)
            ->assertDispatched('toast');

        $reordered = ProficiencyLevel::where('academy_id', $this->academy->id)->ordered()->get();
        $this->assertSame($second->id, $reordered[0]->id);
        $this->assertSame($first->id, $reordered[1]->id);
    }

    /**
     * Requirement 4: Activate and deactivate levels.
     */
    public function test_4_activate_and_deactivate_levels(): void
    {
        $service = app(ProficiencyLevelService::class);
        $level = $service->create($this->academy, [
            'name' => 'Temporary Tier',
            'slug' => 'temp_tier',
            'display_order' => 20,
        ], $this->admin);

        $this->assertTrue($level->is_active);

        // Request deactivation
        Livewire::actingAs($this->admin)
            ->test(ProficiencyLevelsManager::class)
            ->call('requestToggleActive', $level->id)
            ->assertSet('confirmingDeactivateId', $level->id)
            ->call('confirmDeactivation')
            ->assertDispatched('toast');

        $level->refresh();
        $this->assertFalse($level->is_active);

        // Deactivated level is not available in active options
        $activeOptions = $service->getActiveOptions($this->academy);
        $this->assertArrayNotHasKey('temp_tier', $activeOptions);

        // Toggle back to active
        Livewire::actingAs($this->admin)
            ->test(ProficiencyLevelsManager::class)
            ->call('requestToggleActive', $level->id)
            ->assertDispatched('toast');

        $level->refresh();
        $this->assertTrue($level->is_active);
    }

    /**
     * Requirement 5: Prevent deletion of assigned levels.
     */
    public function test_5_prevent_deletion_of_assigned_levels(): void
    {
        $profService = app(ProficiencyLevelService::class);
        $studentService = app(StudentManagementService::class);

        $level = $profService->create($this->academy, [
            'name' => 'Assigned Tier',
            'slug' => 'assigned_tier',
        ], $this->admin);

        // Assign to a student
        $student = $studentService->createOrAttach($this->academy, [
            'name' => 'Assigned Student',
            'email' => 'assigned.student@example.com',
            'proficiency' => 'assigned_tier',
        ], $this->admin);

        $this->assertSame($level->id, $student->proficiency_level_id);
        $this->assertFalse($level->canBeDeleted());
        $this->assertSame(1, $level->assigned_students_count);

        // Attempt deletion via service -> ValidationException
        $this->expectException(ValidationException::class);
        $profService->delete($level, $this->admin);
    }

    /**
     * Requirement 5b: Deletion succeeds for unassigned levels.
     */
    public function test_5b_deletion_succeeds_for_unassigned_level(): void
    {
        $profService = app(ProficiencyLevelService::class);
        $level = $profService->create($this->academy, [
            'name' => 'Orphan Tier',
            'slug' => 'orphan_tier',
        ], $this->admin);

        $this->assertTrue($level->canBeDeleted());

        Livewire::actingAs($this->admin)
            ->test(ProficiencyLevelsManager::class)
            ->call('confirmDeleteLevel', $level->id)
            ->assertSet('confirmingDeleteId', $level->id)
            ->call('deleteLevel')
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('proficiency_levels', ['id' => $level->id]);
    }

    /**
     * Requirement 6: Create a student without proficiency.
     */
    public function test_6_create_student_without_proficiency(): void
    {
        $studentService = app(StudentManagementService::class);
        $student = $studentService->createOrAttach($this->academy, [
            'name' => 'Unassessed User',
            'email' => 'unassessed@example.com',
            'proficiency' => null,
        ], $this->admin);

        $this->assertNull($student->proficiency);
        $this->assertNull($student->proficiency_level_id);
        $this->assertNull($student->level);
        $this->assertSame('Not Assessed', $student->proficiency_label);
        $this->assertSame('gray', $student->proficiency_badge_color);
    }

    /**
     * Requirement 7: Assign a custom proficiency to a student.
     */
    public function test_7_assign_custom_proficiency_to_student(): void
    {
        $profService = app(ProficiencyLevelService::class);
        $studentService = app(StudentManagementService::class);

        $customLevel = $profService->create($this->academy, [
            'name' => 'Senior SEO Specialist',
            'slug' => 'senior_seo_specialist',
            'color' => 'purple',
        ], $this->admin);

        $student = $studentService->createOrAttach($this->academy, [
            'name' => 'Senior SEO',
            'email' => 'senior.seo@example.com',
            'proficiency' => 'senior_seo_specialist',
        ], $this->admin);

        $this->assertSame('senior_seo_specialist', $student->proficiency);
        $this->assertSame($customLevel->id, $student->proficiency_level_id);
        $this->assertSame('Senior SEO Specialist', $student->proficiency_label);
        $this->assertSame('purple', $student->proficiency_badge_color);
    }

    /**
     * Requirement 8: Change a student's proficiency dynamically.
     */
    public function test_8_change_student_proficiency(): void
    {
        $profService = app(ProficiencyLevelService::class);
        $studentService = app(StudentManagementService::class);

        $tierA = $profService->create($this->academy, ['name' => 'Tier A', 'slug' => 'tier_a', 'color' => 'blue'], $this->admin);
        $tierB = $profService->create($this->academy, ['name' => 'Tier B', 'slug' => 'tier_b', 'color' => 'amber'], $this->admin);

        $student = $studentService->createOrAttach($this->academy, [
            'name' => 'Flex Student',
            'email' => 'flex.student@example.com',
            'proficiency' => 'tier_a',
        ], $this->admin);

        $this->assertSame($tierA->id, $student->proficiency_level_id);

        // Update to Tier B
        $studentService->updateProficiency($student, 'tier_b', $this->admin);
        $student->refresh();
        $this->assertSame($tierB->id, $student->proficiency_level_id);
        $this->assertSame('tier_b', $student->proficiency);
        $this->assertSame('Tier B', $student->proficiency_label);

        // Reset to NULL
        $studentService->updateProficiency($student, null, $this->admin);
        $student->refresh();
        $this->assertNull($student->proficiency_level_id);
        $this->assertNull($student->proficiency);
        $this->assertSame('Not Assessed', $student->proficiency_label);
    }

    /**
     * Requirement 9: Filter students by custom proficiency.
     */
    public function test_9_filter_students_by_custom_proficiency(): void
    {
        $profService = app(ProficiencyLevelService::class);
        $studentService = app(StudentManagementService::class);

        $profService->create($this->academy, ['name' => 'Technical Auditor', 'slug' => 'tech_auditor'], $this->admin);
        $profService->create($this->academy, ['name' => 'Content Strategist', 'slug' => 'content_strategist'], $this->admin);

        $studentService->createOrAttach($this->academy, ['name' => 'Tech 1', 'email' => 'tech1@example.com', 'proficiency' => 'tech_auditor'], $this->admin);
        $studentService->createOrAttach($this->academy, ['name' => 'Tech 2', 'email' => 'tech2@example.com', 'proficiency' => 'tech_auditor'], $this->admin);
        $studentService->createOrAttach($this->academy, ['name' => 'Content 1', 'email' => 'content1@example.com', 'proficiency' => 'content_strategist'], $this->admin);

        // Filter by tech_auditor
        $results = $studentService->listForAcademy($this->academy, ['proficiency' => 'tech_auditor']);
        $this->assertSame(2, $results->total());
        foreach ($results->items() as $item) {
            $this->assertSame('tech_auditor', $item->proficiency);
        }

        // Livewire StudentsIndex filter
        Livewire::actingAs($this->admin)
            ->test(StudentsIndex::class)
            ->set('proficiency', 'tech_auditor')
            ->assertSee('Tech 1')
            ->assertSee('Tech 2')
            ->assertDontSee('Content 1');
    }

    /**
     * Requirement 10: Verify analytics and exports with custom proficiency.
     */
    public function test_10_verify_analytics_and_exports(): void
    {
        $profService = app(ProficiencyLevelService::class);
        $studentService = app(StudentManagementService::class);

        $custom = $profService->create($this->academy, [
            'name' => 'Export Specialist',
            'slug' => 'export_specialist',
        ], $this->admin);

        $student = $studentService->createOrAttach($this->academy, [
            'name' => 'Export Student',
            'email' => 'export.student@example.com',
            'proficiency' => 'export_specialist',
        ], $this->admin);

        // Aggregation query test
        $counts = StudentProfile::query()
            ->forAcademy($this->academy)
            ->where('proficiency', 'export_specialist')
            ->count();
        $this->assertSame(1, $counts);

        // CSV export includes custom proficiency label
        $response = $studentService->exportCsv($this->academy, ['proficiency' => 'export_specialist'], $this->admin);
        $this->assertSame(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('export.student@example.com', $csvContent);
        $this->assertStringContainsString('Export Specialist', $csvContent);
    }

    /**
     * Requirement 11: Verify existing student records remain unchanged.
     */
    public function test_11_verify_existing_student_records_remain_unchanged(): void
    {
        $profService = app(ProficiencyLevelService::class);
        $studentService = app(StudentManagementService::class);

        $profService->ensureDefaultLevels($this->academy);

        $beg = $studentService->createOrAttach($this->academy, ['name' => 'Old Beginner', 'email' => 'beg@example.com', 'proficiency' => 'beginner'], $this->admin);
        $inter = $studentService->createOrAttach($this->academy, ['name' => 'Old Inter', 'email' => 'inter@example.com', 'proficiency' => 'intermediate'], $this->admin);
        $exp = $studentService->createOrAttach($this->academy, ['name' => 'Old Exp', 'email' => 'exp@example.com', 'proficiency' => 'expert'], $this->admin);

        $this->assertSame('beginner', $beg->fresh()->proficiency);
        $this->assertSame('Beginner', $beg->fresh()->proficiency_label);
        $this->assertSame('intermediate', $inter->fresh()->proficiency);
        $this->assertSame('Intermediate', $inter->fresh()->proficiency_label);
        $this->assertSame('expert', $exp->fresh()->proficiency);
        $this->assertSame('Expert', $exp->fresh()->proficiency_label);
    }
}
