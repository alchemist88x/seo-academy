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
}
