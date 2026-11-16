<?php

namespace Tests\Feature\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Services\StudentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\StudentsIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createAcademyMember(Academy $academy, string $role, array $userOverrides = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $userOverrides));
        $user->assignRole($role);
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_admin_can_list_search_filter_create_update_and_toggle_students(): void
    {
        $academy = Academy::factory()->create(['name' => 'Alpha SEO', 'slug' => 'alpha-seo']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $this->actingAs($admin)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.students.index'))
            ->assertStatus(200)
            ->assertSee('Students');

        app(TenantContext::class)->setAcademy($academy);

        // Create student
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('name', 'Alice Learner')
            ->set('email', 'alice@example.com')
            ->set('phone', '+15550101')
            ->set('batch', 'Cohort 2026-A')
            ->set('formLevel', 'Intermediate')
            ->set('notes', 'Top performer')
            ->call('saveStudent')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $aliceUser = User::where('email', 'alice@example.com')->firstOrFail();
        $aliceProfile = StudentProfile::where('academy_id', $academy->id)
            ->where('user_id', $aliceUser->id)
            ->firstOrFail();

        $this->assertTrue($aliceProfile->active);
        $this->assertSame('Intermediate', $aliceProfile->level);
        $this->assertSame('Cohort 2026-A', $aliceProfile->batch);

        // Create second student for search/filter checks
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('name', 'Bob Beginner')
            ->set('email', 'bob@example.com')
            ->set('formLevel', 'Beginner')
            ->call('saveStudent')
            ->assertHasNoErrors();

        // Update Alice
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('openEditModal', $aliceProfile->id)
            ->set('name', 'Alice Updated')
            ->set('formProficiency', 'expert')
            ->set('batch', 'Cohort 2026-B')
            ->call('saveStudent')
            ->assertHasNoErrors();

        $aliceProfile->refresh();
        $aliceUser->refresh();
        $this->assertSame('Alice Updated', $aliceUser->name);
        $this->assertSame('expert', $aliceProfile->proficiency);
        $this->assertSame('Cohort 2026-B', $aliceProfile->batch);

        // Deactivate Alice
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('toggleActive', $aliceProfile->id)
            ->assertDispatched('toast');

        $this->assertFalse($aliceProfile->fresh()->active);

        // Search & filter
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('search', 'Alice')
            ->assertSee('Alice Updated')
            ->assertDontSee('Bob Beginner');

        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('level', 'Beginner')
            ->assertSee('Bob Beginner')
            ->assertDontSee('Alice Updated');

        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('status', 'inactive')
            ->assertSee('Alice Updated')
            ->assertDontSee('Bob Beginner');

        // Re-activate Alice
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('toggleActive', $aliceProfile->id);

        $this->assertTrue($aliceProfile->fresh()->active);
    }

    public function test_creating_student_with_existing_user_email_reuses_user_in_single_academy(): void
    {
        $academy = Academy::factory()->create(['slug' => 'single-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $existingUser = User::factory()->create([
            'name' => 'Shared Student',
            'email' => 'shared.student@example.com',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($academy);
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('name', 'Shared Student')
            ->set('email', 'shared.student@example.com')
            ->set('formLevel', 'Beginner')
            ->call('saveStudent')
            ->assertHasNoErrors();

        $this->assertSame(1, User::where('email', 'shared.student@example.com')->count());
        $this->assertDatabaseHas('academy_users', [
            'academy_id' => $academy->id,
            'user_id' => $existingUser->id,
            'status' => 'active',
        ]);
        $this->assertSame(1, StudentProfile::where('user_id', $existingUser->id)->count());
    }

    public function test_csv_template_download_and_formula_injection_escaping(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        $response = $this->actingAs($admin)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.students.csv-template'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->getContent();
        $this->assertTrue(
            str_contains($content, 'name,email,phone,student_code,batch,proficiency,enrolled_at,notes') ||
            str_contains($content, 'name,email,phone,student_code,batch,level,enrolled_at,notes')
        );

        $service = app(StudentManagementService::class);
        $this->assertSame("'=cmd|'/C calc'!A0", $service->sanitizeCsvCell("=cmd|'/C calc'!A0"));
        $this->assertSame("'+SUM(1,2)", $service->sanitizeCsvCell('+SUM(1,2)'));
        $this->assertSame("'-1+1", $service->sanitizeCsvCell('-1+1'));
        $this->assertSame("'@SUM(A1:A2)", $service->sanitizeCsvCell('@SUM(A1:A2)'));
    }

    public function test_csv_preview_detects_errors_and_confirm_import_is_transactional(): void
    {
        $academy = Academy::factory()->create(['slug' => 'csv-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        // Pre-existing student to trigger duplicate-in-academy error
        $existingUser = $this->createAcademyMember($academy, 'student', ['email' => 'existing@example.com']);
        StudentProfile::create([
            'user_id' => $existingUser->id,
            'academy_id' => $academy->id,
            'student_code' => 'EXIST-001',
            'level' => 'Beginner',
            'active' => true,
        ]);

        app(TenantContext::class)->setAcademy($academy);

        $csvContent = implode("\n", [
            'name,email,phone,student_code,batch,level,enrolled_at,notes',
            'Valid One,valid1@example.com,+15550101,CSV-001,Batch A,Beginner,2026-01-15,Good row',
            'Bad Email,not-an-email,+15550102,CSV-002,Batch A,Beginner,2026-01-15,Invalid email',
            'Dup In Csv,valid1@example.com,+15550103,CSV-003,Batch A,Intermediate,2026-01-15,Duplicate email in file',
            'Dup In Academy,existing@example.com,+15550104,CSV-004,Batch A,Beginner,2026-01-15,Already in academy',
            'Valid Two,valid2@example.com,+15550105,CSV-005,Batch B,Advanced,2026-01-16,Second valid row',
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $component = Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('csvFile', $file)
            ->call('previewCsvImport')
            ->assertHasNoErrors();

        $preview = $component->get('importPreview');
        $this->assertSame(5, $preview['summary']['total']);
        $this->assertSame(2, $preview['summary']['valid']);
        $this->assertSame(3, $preview['summary']['errors']);
        $this->assertSame(3, $preview['error_rows'][0]['row_number']);
        $this->assertSame(4, $preview['error_rows'][1]['row_number']);
        $this->assertSame(5, $preview['error_rows'][2]['row_number']);

        $component->call('confirmCsvImport')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame(2, $component->get('importResult')['imported']);
        $this->assertDatabaseHas('users', ['email' => 'valid1@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'valid2@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'not-an-email']);
    }

    public function test_suspended_user_and_role_restrictions_return_403(): void
    {
        $academy = Academy::factory()->create(['slug' => 'single-tenant']);

        $suspendedAdmin = $this->createAcademyMember($academy, 'admin', ['is_active' => false]);
        $studentUser = $this->createAcademyMember($academy, 'student');
        $studentProfile = StudentProfile::create([
            'user_id' => $studentUser->id,
            'academy_id' => $academy->id,
            'student_code' => 'TENA-001',
            'level' => 'Beginner',
            'active' => true,
        ]);
        $trainer = $this->createAcademyMember($academy, 'trainer');
        $support = $this->createAcademyMember($academy, 'support_staff');

        // Suspended admin is blocked with 403
        $this->actingAs($suspendedAdmin)
            ->get(route('admin.students.index'))
            ->assertStatus(403);

        // Support staff and student cannot update student profile via policy
        $this->assertFalse($support->can('update', $studentProfile));
        $this->assertFalse($studentUser->can('update', $studentProfile));

        // Trainer, support_staff, student get 403 on /admin/students and csv-template
        foreach ([$trainer, $support, $studentUser] as $user) {
            $this->actingAs($user)
                ->get(route('admin.students.index'))
                ->assertStatus(403);

            $this->actingAs($user)
                ->get(route('admin.students.csv-template'))
                ->assertStatus(403);
        }
    }

    public function test_student_email_update_and_duplicate_protection(): void
    {
        $academy = Academy::factory()->create(['slug' => 'email-test-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $user1 = $this->createAcademyMember($academy, 'student', ['email' => 'student1@example.com']);
        $profile1 = StudentProfile::create([
            'user_id' => $user1->id,
            'academy_id' => $academy->id,
            'student_code' => 'STU-100',
            'level' => 'Beginner',
            'active' => true,
        ]);

        $user2 = $this->createAcademyMember($academy, 'student', ['email' => 'student2@example.com']);
        $profile2 = StudentProfile::create([
            'user_id' => $user2->id,
            'academy_id' => $academy->id,
            'student_code' => 'STU-200',
            'level' => 'Beginner',
            'active' => true,
        ]);

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);
        app(TenantContext::class)->setAcademy($academy);

        // 1. Changing to an existing user's email fails validation
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('openEditModal', $profile1->id)
            ->set('email', 'student2@example.com')
            ->call('saveStudent')
            ->assertHasErrors(['email']);

        $user1->refresh();
        $this->assertSame('student1@example.com', $user1->email);

        // 2. Changing to a new unique email succeeds
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('openEditModal', $profile1->id)
            ->set('email', 'student1.updated@example.com')
            ->call('saveStudent')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $user1->refresh();
        $this->assertSame('student1.updated@example.com', $user1->email);
    }

    public function test_partial_update_preserves_unmodified_fields(): void
    {
        $academy = Academy::factory()->create(['slug' => 'partial-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $user = $this->createAcademyMember($academy, 'student', [
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'phone' => '+15554321',
        ]);
        $originalPassword = $user->password;

        $profile = StudentProfile::create([
            'user_id' => $user->id,
            'academy_id' => $academy->id,
            'student_code' => 'PART-001',
            'batch' => 'Original Batch',
            'level' => 'Beginner',
            'active' => true,
            'notes' => 'Original Notes',
        ]);

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);
        app(TenantContext::class)->setAcademy($academy);

        // Edit only the first/last name
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('openEditModal', $profile->id)
            ->set('first_name', 'Renamed')
            ->set('last_name', 'Student')
            ->call('saveStudent')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $user->refresh();
        $profile->refresh();

        $this->assertSame('Renamed Student', $user->name);
        $this->assertSame('+15554321', $user->phone); // Phone preserved!
        $this->assertSame($originalPassword, $user->password); // Password not overwritten!
        $this->assertSame('Original Batch', $profile->batch);
        $this->assertSame('Original Notes', $profile->notes);
    }

    public function test_student_account_status_can_be_updated_in_edit_form(): void
    {
        $academy = Academy::factory()->create(['slug' => 'status-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $user = $this->createAcademyMember($academy, 'student', ['is_active' => true]);
        $profile = StudentProfile::create([
            'user_id' => $user->id,
            'academy_id' => $academy->id,
            'student_code' => 'STAT-001',
            'level' => 'Beginner',
            'active' => true,
        ]);

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);
        app(TenantContext::class)->setAcademy($academy);

        // Suspend via edit modal
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('openEditModal', $profile->id)
            ->set('account_status', 'suspended')
            ->call('saveStudent')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $user->refresh();
        $profile->refresh();
        $this->assertFalse($profile->active);
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->suspended_at);

        // Reactivate via edit modal
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('openEditModal', $profile->id)
            ->set('account_status', 'active')
            ->call('saveStudent')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $user->refresh();
        $profile->refresh();
        $this->assertTrue($profile->active);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->suspended_at);
    }

    public function test_student_proficiency_levels_beginner_intermediate_expert_and_normalization(): void
    {
        $academy = Academy::factory()->create(['slug' => 'prof-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);
        app(TenantContext::class)->setAcademy($academy);

        // 1. Create with Beginner
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('name', 'Beginner Sam')
            ->set('email', 'sam.beginner@example.com')
            ->set('formProficiency', StudentProfile::PROFICIENCY_BEGINNER)
            ->call('saveStudent')
            ->assertHasNoErrors();

        $sam = User::where('email', 'sam.beginner@example.com')->firstOrFail();
        $samProfile = StudentProfile::where('user_id', $sam->id)->firstOrFail();
        $this->assertSame('beginner', $samProfile->proficiency);
        $this->assertSame('Beginner', $samProfile->proficiency_label);

        // 2. Update to Intermediate
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('openEditModal', $samProfile->id)
            ->set('formProficiency', StudentProfile::PROFICIENCY_INTERMEDIATE)
            ->call('saveStudent')
            ->assertHasNoErrors();

        $samProfile->refresh();
        $this->assertSame('intermediate', $samProfile->proficiency);
        $this->assertSame('Intermediate', $samProfile->proficiency_label);

        // 3. Update to Expert
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('openEditModal', $samProfile->id)
            ->set('formProficiency', StudentProfile::PROFICIENCY_EXPERT)
            ->call('saveStudent')
            ->assertHasNoErrors();

        $samProfile->refresh();
        $this->assertSame('expert', $samProfile->proficiency);
        $this->assertSame('Expert', $samProfile->proficiency_label);

        // 4. Test invalid proficiency rejection
        $service = app(StudentManagementService::class);
        $this->expectException(ValidationException::class);
        $service->createOrAttach($academy, [
            'name' => 'Invalid Guy',
            'email' => 'invalid@example.com',
            'proficiency' => 'grandmaster',
        ], $admin);
    }

    public function test_student_search_filtering_and_pagination(): void
    {
        $academy = Academy::factory()->create(['slug' => 'filter-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);
        app(TenantContext::class)->setAcademy($academy);

        // Create 20 students across various cohorts and proficiencies
        for ($i = 1; $i <= 20; $i++) {
            $user = User::factory()->create([
                'name' => "Student $i",
                'email' => "student{$i}@example.com",
                'phone' => "+155500{$i}",
                'is_active' => true,
            ]);
            $user->assignRole('student');
            $user->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'student', 'status' => 'active']);

            $prof = ($i % 3 === 0) ? 'expert' : (($i % 2 === 0) ? 'intermediate' : 'beginner');
            $batch = ($i <= 10) ? 'Cohort-A' : 'Cohort-B';

            StudentProfile::create([
                'user_id' => $user->id,
                'academy_id' => $academy->id,
                'student_code' => sprintf('STU-%03d', $i),
                'batch' => $batch,
                'proficiency' => $prof,
                'level' => ucfirst($prof),
                'active' => true,
            ]);
        }

        // Test filter by proficiency
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('proficiency', 'expert')
            ->assertSee('STU-003')
            ->assertSee('STU-006')
            ->assertDontSee('STU-001');

        // Test filter by batch
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('batch', 'Cohort-B')
            ->assertSee('STU-011')
            ->assertDontSee('STU-002');

        // Test pagination limit
        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('perPage', 15)
            ->assertSee('STU-001')
            ->assertSee('STU-015');
    }

    public function test_csv_export_endpoint_and_streamed_download(): void
    {
        $academy = Academy::factory()->create(['slug' => 'export-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $user = User::factory()->create([
            'name' => 'Export Candidate',
            'email' => 'export.candidate@example.com',
            'phone' => '+15559988',
            'is_active' => true,
        ]);
        $user->assignRole('student');
        $user->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'student', 'status' => 'active']);

        StudentProfile::create([
            'user_id' => $user->id,
            'academy_id' => $academy->id,
            'student_code' => 'EXP-001',
            'batch' => 'Cohort 2026-X',
            'proficiency' => 'expert',
            'level' => 'Expert',
            'active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.students.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $streamedContent = $response->streamedContent();
        $this->assertStringContainsString('EXP-001', $streamedContent);
        $this->assertStringContainsString('export.candidate@example.com', $streamedContent);
        $this->assertStringContainsString('Expert', $streamedContent);
        $this->assertStringContainsString('Cohort 2026-X', $streamedContent);
    }

    public function test_student_view_modal_loads_profile_and_details(): void
    {
        $academy = Academy::factory()->create(['slug' => 'view-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $user = User::factory()->create([
            'name' => 'Viewable Student',
            'email' => 'viewable@example.com',
            'phone' => '+15553322',
            'is_active' => true,
        ]);
        $user->assignRole('student');
        $user->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'student', 'status' => 'active']);

        $profile = StudentProfile::create([
            'user_id' => $user->id,
            'academy_id' => $academy->id,
            'student_code' => 'VIEW-001',
            'batch' => 'Spring 2026',
            'proficiency' => 'intermediate',
            'level' => 'Intermediate',
            'notes' => 'Looking to upskill in technical SEO',
            'active' => true,
        ]);

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);
        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('openViewModal', $profile->id)
            ->assertSet('viewingStudent.id', $profile->id)
            ->assertDispatched('open-modal', 'student-view-modal');
    }

    public function test_student_suspension_blocks_protected_learning_access(): void
    {
        $academy = Academy::factory()->create(['slug' => 'susp-acad']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('student');
        $user->academies()->attach($academy->id, ['id' => (string) Str::uuid(), 'role' => 'student', 'status' => 'active']);

        $profile = StudentProfile::create([
            'user_id' => $user->id,
            'academy_id' => $academy->id,
            'student_code' => 'SUSP-001',
            'proficiency' => 'beginner',
            'level' => 'Beginner',
            'active' => true,
        ]);

        // Active student can access student dashboard
        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get('/student/dashboard')
            ->assertStatus(200);

        // Suspend student
        $service = app(StudentManagementService::class);
        $service->toggleActive($profile);

        $user->refresh();
        $profile->refresh();
        $this->assertFalse($profile->active);
        $this->assertFalse($user->is_active);

        // Suspended student is blocked with HTTP 403 on protected route
        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get('/student/dashboard')
            ->assertStatus(403);
    }

    public function test_csv_import_idempotency_lock_and_rejected_rows_export(): void
    {
        $academy = Academy::factory()->create(['slug' => 'idemp-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);
        app(TenantContext::class)->setAcademy($academy);

        $csvContent = implode("\n", [
            'name,email,phone,student_code,batch,proficiency,enrolled_at,notes',
            'Valid Unique,unique.import@example.com,+15551122,UNIQ-001,Cohort 2026,beginner,2026-02-01,Valid',
            'Malformed Email,invalid-email-address,+15551133,UNIQ-002,Cohort 2026,intermediate,2026-02-01,Error',
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $component = Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('csvFile', $file)
            ->call('previewCsvImport')
            ->assertHasNoErrors();

        // 1. Download rejected rows CSV
        $rejectedResponse = $component->call('downloadRejectedRows');
        $this->assertNotNull($rejectedResponse);

        // 2. Confirm import first time
        $component->call('confirmCsvImport')
            ->assertHasNoErrors();

        $this->assertSame(1, User::where('email', 'unique.import@example.com')->count());

        // 3. Confirm import second time with same token (idempotency prevents re-processing)
        $component->call('confirmCsvImport');
        $this->assertSame(1, User::where('email', 'unique.import@example.com')->count());
    }
}
