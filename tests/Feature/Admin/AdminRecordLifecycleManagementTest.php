<?php

namespace Tests\Feature\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseTrainer;
use App\Domain\Courses\Services\CourseManagementService;
use App\Domain\Identity\Services\MembershipManagementService;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Services\StudentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Domain\Trainers\Services\TrainerManagementService;
use App\Livewire\Admin\CertificatesIndex;
use App\Livewire\Admin\CoursesIndex;
use App\Livewire\Admin\StudentsIndex;
use App\Livewire\Admin\TrainersIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRecordLifecycleManagementTest extends TestCase
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

    // ─────────────────────────────────────────────
    // 1. STUDENTS LIFECYCLE TESTS
    // ─────────────────────────────────────────────

    public function test_admin_can_soft_delete_student_and_revoke_auth_while_preserving_records(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $studentUser = $this->createAcademyMember($academy, 'student');

        $profile = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'academy_id' => $academy->id,
            'student_code' => 'SEOA-TEST-001',
            'active' => true,
        ]);

        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $enrollment = Enrollment::factory()->create([
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => 'Active',
        ]);

        $service = app(StudentManagementService::class);

        // Verify impact summary
        $impact = $service->getImpactSummary($profile);
        $this->assertSame(1, $impact['enrollments_count']);
        $this->assertSame(1, $impact['active_enrollments_count']);

        // Delete student via service
        $service->delete($profile, $admin);

        // Verify profile is soft-deleted
        $this->assertSoftDeleted('student_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('student_profiles', ['id' => $profile->id]);

        // Verify authentication is revoked
        $studentUser->refresh();
        $this->assertFalse($studentUser->is_active);
        $this->assertNotNull($studentUser->suspended_at);

        // Verify student cannot login
        $this->actingAs($studentUser);
        $this->assertFalse(auth()->user()->is_active);

        // Verify historical enrollments and records are preserved
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'student_profile_id' => $profile->id]);

        // Verify student is excluded from active student list
        $activeList = $service->listForAcademy($academy, ['status' => 'active']);
        $this->assertFalse($activeList->getCollection()->contains('id', $profile->id));

        // Verify student appears in deleted filter
        $deletedList = $service->listForAcademy($academy, ['status' => 'deleted']);
        $this->assertTrue($deletedList->getCollection()->contains('id', $profile->id));

        // Restore student
        $restored = $service->restore($profile->id, $admin);
        $this->assertFalse($restored->trashed());

        $studentUser->refresh();
        $this->assertTrue($studentUser->is_active);
        $this->assertNull($studentUser->suspended_at);
    }

    public function test_admin_cannot_delete_their_own_account_as_student(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        $profile = StudentProfile::factory()->create([
            'user_id' => $admin->id,
            'academy_id' => $academy->id,
            'student_code' => 'SEOA-ADMIN-STUDENT',
        ]);

        $service = app(StudentManagementService::class);

        $this->expectException(ValidationException::class);
        $service->delete($profile, $admin);
    }

    public function test_cannot_delete_student_if_last_active_administrator(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        $profile = StudentProfile::factory()->create([
            'user_id' => $admin->id,
            'academy_id' => $academy->id,
            'student_code' => 'SEOA-LAST-ADMIN-STU',
        ]);

        $service = app(StudentManagementService::class);

        // Actor cannot delete their own account or the last active admin
        $this->expectException(ValidationException::class);
        $service->delete($profile, $admin);
    }

    public function test_previously_suspended_student_delete_and_restore_preserves_suspended_state(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $suspendedAt = now()->subDays(5)->startOfSecond();

        $studentUser = $this->createAcademyMember($academy, 'student', [
            'is_active' => false,
            'suspended_at' => $suspendedAt,
        ]);

        AcademyUser::query()
            ->where('academy_id', $academy->id)
            ->where('user_id', $studentUser->id)
            ->update([
                'status' => 'suspended',
                'suspended_at' => $suspendedAt,
            ]);

        $profile = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'academy_id' => $academy->id,
            'student_code' => 'SEOA-SUSP-001',
            'active' => false,
        ]);

        $service = app(StudentManagementService::class);

        // Delete previously suspended student
        $service->delete($profile, $admin);

        $this->assertSoftDeleted('student_profiles', ['id' => $profile->id]);
        $studentUser->refresh();
        $this->assertFalse($studentUser->is_active);
        $this->assertEquals($suspendedAt->format('Y-m-d H:i:s'), $studentUser->suspended_at?->format('Y-m-d H:i:s'));

        // Restore previously suspended student
        $restored = $service->restore($profile->id, $admin);
        $this->assertFalse($restored->trashed());
        $this->assertFalse($restored->active);

        // Crucial verification: User MUST REMAIN INACTIVE/SUSPENDED!
        $studentUser->refresh();
        $this->assertFalse($studentUser->is_active, 'Previously suspended user must NOT be reactivated on restoration.');
        $this->assertEquals($suspendedAt->format('Y-m-d H:i:s'), $studentUser->suspended_at?->format('Y-m-d H:i:s'));

        // Academy membership must remain suspended
        $membership = AcademyUser::query()
            ->where('academy_id', $academy->id)
            ->where('user_id', $studentUser->id)
            ->first();
        $this->assertSame('suspended', $membership->status);
        $this->assertEquals($suspendedAt->format('Y-m-d H:i:s'), $membership->suspended_at?->format('Y-m-d H:i:s'));
    }

    public function test_multiple_role_account_preservation_when_student_profile_deleted(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        // Multi-role user: holds trainer role with active TrainerProfile, AND a StudentProfile
        $multiUser = $this->createAcademyMember($academy, 'trainer');
        $multiUser->assignRole('student');

        $trainerProfile = TrainerProfile::create([
            'user_id' => $multiUser->id,
            'academy_id' => $academy->id,
            'specialty' => 'Technical SEO Specialist',
            'active' => true,
        ]);

        $studentProfile = StudentProfile::factory()->create([
            'user_id' => $multiUser->id,
            'academy_id' => $academy->id,
            'student_code' => 'SEOA-MULTI-001',
            'active' => true,
        ]);

        $studentService = app(StudentManagementService::class);

        // Delete the student profile
        $studentService->delete($studentProfile, $admin);

        // Student profile is deleted
        $this->assertSoftDeleted('student_profiles', ['id' => $studentProfile->id]);

        // CRUCIAL: Multi-role user must STILL BE ACTIVE because they are an active trainer!
        $multiUser->refresh();
        $this->assertTrue($multiUser->is_active, 'User must remain active for trainer duties despite student profile deletion.');
        $this->assertNull($multiUser->suspended_at);

        // Trainer profile must remain completely active
        $this->assertTrue($trainerProfile->fresh()->active);
        $this->assertFalse($trainerProfile->fresh()->trashed());

        // Academy membership must remain active
        $membership = AcademyUser::query()
            ->where('academy_id', $academy->id)
            ->where('user_id', $multiUser->id)
            ->first();
        $this->assertSame('active', $membership->status);

        // Restore student profile
        $restoredStudent = $studentService->restore($studentProfile->id, $admin);
        $this->assertFalse($restoredStudent->trashed());
        $this->assertTrue($restoredStudent->active);

        // User and trainer profile remain active
        $multiUser->refresh();
        $this->assertTrue($multiUser->is_active);
        $this->assertTrue($trainerProfile->fresh()->active);
    }

    public function test_repeated_student_deletion_and_restoration_is_idempotent(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $studentUser = $this->createAcademyMember($academy, 'student');

        $profile = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'academy_id' => $academy->id,
            'student_code' => 'SEOA-IDEM-001',
            'active' => true,
        ]);

        $service = app(StudentManagementService::class);

        // First deletion
        $service->delete($profile, $admin);
        $this->assertSoftDeleted('student_profiles', ['id' => $profile->id]);
        $firstSnapshot = $profile->fresh()->pre_deletion_state;
        $this->assertNotNull($firstSnapshot);

        // Second deletion (repeated) - must not throw and must not overwrite snapshot
        $service->delete($profile->fresh(), $admin);
        $this->assertSoftDeleted('student_profiles', ['id' => $profile->id]);
        $secondSnapshot = $profile->fresh()->pre_deletion_state;
        $this->assertSame($firstSnapshot, $secondSnapshot, 'Pre-deletion state snapshot must be immutable across repeated deletions.');

        // First restoration
        $restored = $service->restore($profile->id, $admin);
        $this->assertFalse($restored->trashed());
        $studentUser->refresh();
        $this->assertTrue($studentUser->is_active);

        // Second restoration (repeated) - must not throw and must return restored profile
        $restoredAgain = $service->restore($profile->id, $admin);
        $this->assertFalse($restoredAgain->trashed());
        $this->assertSame($restored->id, $restoredAgain->id);
    }

    public function test_students_livewire_delete_and_restore_workflow(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $studentUser = $this->createAcademyMember($academy, 'student');

        $profile = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'academy_id' => $academy->id,
            'student_code' => 'SEOA-LW-001',
        ]);

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->call('openDeleteModal', $profile->id)
            ->assertSet('deletingStudentId', $profile->id)
            ->call('confirmDeleteStudent')
            ->assertDispatched('toast');

        $this->assertSoftDeleted('student_profiles', ['id' => $profile->id]);

        Livewire::actingAs($admin)
            ->test(StudentsIndex::class)
            ->set('status', 'deleted')
            ->call('restoreStudent', $profile->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('student_profiles', ['id' => $profile->id, 'deleted_at' => null]);
    }

    // ─────────────────────────────────────────────
    // 2. TRAINERS LIFECYCLE TESTS
    // ─────────────────────────────────────────────

    public function test_admin_can_soft_delete_trainer_preserving_course_assignments(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $trainerUser = $this->createAcademyMember($academy, 'trainer');

        $profile = TrainerProfile::create([
            'user_id' => $trainerUser->id,
            'academy_id' => $academy->id,
            'specialty' => 'Technical SEO',
            'active' => true,
        ]);

        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $courseAssignment = CourseTrainer::create([
            'course_id' => $course->id,
            'user_id' => $trainerUser->id,
            'academy_id' => $academy->id,
            'is_primary' => true,
        ]);

        $service = app(TrainerManagementService::class);

        // Verify impact summary
        $impact = $service->getImpactSummary($profile);
        $this->assertSame(1, $impact['assigned_courses_count']);
        $this->assertSame($course->title, $impact['assigned_courses'][0]['title']);

        // Delete trainer via service
        $service->delete($profile, $admin);

        // Verify trainer is soft-deleted
        $this->assertSoftDeleted('trainer_profiles', ['id' => $profile->id]);

        // Verify authentication is revoked
        $trainerUser->refresh();
        $this->assertFalse($trainerUser->is_active);

        // CRITICAL: Verify assigned courses are PRESERVED and not silently deleted or reassigned
        $this->assertDatabaseHas('course_trainers', [
            'id' => $courseAssignment->id,
            'course_id' => $course->id,
            'user_id' => $trainerUser->id,
        ]);

        // Restore trainer
        $service->restore($profile->id, $admin);
        $this->assertDatabaseHas('trainer_profiles', ['id' => $profile->id, 'deleted_at' => null]);

        $trainerUser->refresh();
        $this->assertTrue($trainerUser->is_active);
    }

    public function test_cannot_delete_trainer_if_last_active_administrator(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        // Admin also has trainer profile
        $profile = TrainerProfile::create([
            'user_id' => $admin->id,
            'academy_id' => $academy->id,
            'specialty' => 'SEO Director',
            'active' => true,
        ]);

        $service = app(TrainerManagementService::class);

        // Attempting to delete the only admin will throw ValidationException (either self-delete or last admin)
        $this->expectException(ValidationException::class);
        $service->delete($profile, $admin);
    }

    public function test_previously_suspended_trainer_delete_and_restore_preserves_suspended_state(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $suspendedAt = now()->subDays(5)->startOfSecond();

        $trainerUser = $this->createAcademyMember($academy, 'trainer', [
            'is_active' => false,
            'suspended_at' => $suspendedAt,
        ]);

        AcademyUser::query()
            ->where('academy_id', $academy->id)
            ->where('user_id', $trainerUser->id)
            ->update([
                'status' => 'suspended',
                'suspended_at' => $suspendedAt,
            ]);

        $profile = TrainerProfile::create([
            'user_id' => $trainerUser->id,
            'academy_id' => $academy->id,
            'specialty' => 'On-Page SEO',
            'active' => false,
        ]);

        $service = app(TrainerManagementService::class);

        // Delete suspended trainer
        $service->delete($profile, $admin);

        $this->assertSoftDeleted('trainer_profiles', ['id' => $profile->id]);
        $trainerUser->refresh();
        $this->assertFalse($trainerUser->is_active);
        $this->assertEquals($suspendedAt->format('Y-m-d H:i:s'), $trainerUser->suspended_at?->format('Y-m-d H:i:s'));

        // Restore suspended trainer
        $restored = $service->restore($profile->id, $admin);
        $this->assertFalse($restored->trashed());
        $this->assertFalse($restored->active);

        // Crucial: User must remain suspended!
        $trainerUser->refresh();
        $this->assertFalse($trainerUser->is_active, 'Previously suspended trainer must NOT be reactivated on restoration.');
        $this->assertEquals($suspendedAt->format('Y-m-d H:i:s'), $trainerUser->suspended_at?->format('Y-m-d H:i:s'));

        $membership = AcademyUser::query()
            ->where('academy_id', $academy->id)
            ->where('user_id', $trainerUser->id)
            ->first();
        $this->assertSame('suspended', $membership->status);
        $this->assertEquals($suspendedAt->format('Y-m-d H:i:s'), $membership->suspended_at?->format('Y-m-d H:i:s'));
    }

    public function test_multiple_role_account_preservation_when_trainer_profile_deleted(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        // Multi-role user: holds student role with active StudentProfile, AND a TrainerProfile
        $multiUser = $this->createAcademyMember($academy, 'student');
        $multiUser->assignRole('trainer');

        $studentProfile = StudentProfile::factory()->create([
            'user_id' => $multiUser->id,
            'academy_id' => $academy->id,
            'student_code' => 'SEOA-MULTI-TRAINER-STU',
            'active' => true,
        ]);

        $trainerProfile = TrainerProfile::create([
            'user_id' => $multiUser->id,
            'academy_id' => $academy->id,
            'specialty' => 'Analytics Trainer',
            'active' => true,
        ]);

        $trainerService = app(TrainerManagementService::class);

        // Delete trainer profile
        $trainerService->delete($trainerProfile, $admin);

        $this->assertSoftDeleted('trainer_profiles', ['id' => $trainerProfile->id]);

        // User must remain active because student profile is still active
        $multiUser->refresh();
        $this->assertTrue($multiUser->is_active, 'User must remain active for student activities despite trainer deletion.');
        $this->assertNull($multiUser->suspended_at);

        // Student profile remains intact
        $this->assertTrue($studentProfile->fresh()->active);
        $this->assertFalse($studentProfile->fresh()->trashed());

        // Restore trainer profile
        $restoredTrainer = $trainerService->restore($trainerProfile->id, $admin);
        $this->assertFalse($restoredTrainer->trashed());
        $this->assertTrue($restoredTrainer->active);

        $multiUser->refresh();
        $this->assertTrue($multiUser->is_active);
    }

    public function test_repeated_trainer_deletion_and_restoration_is_idempotent(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $trainerUser = $this->createAcademyMember($academy, 'trainer');

        $profile = TrainerProfile::create([
            'user_id' => $trainerUser->id,
            'academy_id' => $academy->id,
            'specialty' => 'SEO Auditing',
            'active' => true,
        ]);

        $service = app(TrainerManagementService::class);

        // First deletion
        $service->delete($profile, $admin);
        $this->assertSoftDeleted('trainer_profiles', ['id' => $profile->id]);
        $firstSnapshot = $profile->fresh()->pre_deletion_state;
        $this->assertNotNull($firstSnapshot);

        // Second deletion (repeated)
        $service->delete($profile->fresh(), $admin);
        $secondSnapshot = $profile->fresh()->pre_deletion_state;
        $this->assertSame($firstSnapshot, $secondSnapshot);

        // First restoration
        $restored = $service->restore($profile->id, $admin);
        $this->assertFalse($restored->trashed());
        $trainerUser->refresh();
        $this->assertTrue($trainerUser->is_active);

        // Second restoration (repeated)
        $restoredAgain = $service->restore($profile->id, $admin);
        $this->assertFalse($restoredAgain->trashed());
        $this->assertSame($restored->id, $restoredAgain->id);
    }

    public function test_trainers_livewire_delete_and_restore_workflow(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $trainerUser = $this->createAcademyMember($academy, 'trainer');

        $profile = TrainerProfile::create([
            'user_id' => $trainerUser->id,
            'academy_id' => $academy->id,
            'specialty' => 'Link Building',
            'active' => true,
        ]);

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        Livewire::actingAs($admin)
            ->test(TrainersIndex::class)
            ->call('openDeleteModal', $profile->id)
            ->assertSet('deletingTrainerId', $profile->id)
            ->call('confirmDeleteTrainer')
            ->assertDispatched('toast');

        $this->assertSoftDeleted('trainer_profiles', ['id' => $profile->id]);

        Livewire::actingAs($admin)
            ->test(TrainersIndex::class)
            ->set('status', 'deleted')
            ->call('restoreTrainer', $profile->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('trainer_profiles', ['id' => $profile->id, 'deleted_at' => null]);
    }

    // ─────────────────────────────────────────────
    // 3. COURSES LIFECYCLE TESTS
    // ─────────────────────────────────────────────

    public function test_admin_can_archive_and_restore_course(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'status' => 'Published',
        ]);

        $service = app(CourseManagementService::class);

        // Archive course
        $service->archive($course, $admin);
        $course->refresh();
        $this->assertSame('Archived', $course->status);
        $this->assertTrue($course->isArchived());

        // Restore course
        $service->restoreArchived($course, $admin, 'Published');
        $course->refresh();
        $this->assertSame('Published', $course->status);
    }

    public function test_unsafe_course_deletion_blocked_when_enrollments_exist(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $studentUser = $this->createAcademyMember($academy, 'student');

        $profile = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'academy_id' => $academy->id,
        ]);

        $course = Course::factory()->create(['academy_id' => $academy->id]);
        Enrollment::factory()->create([
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => 'Active',
        ]);

        $service = app(CourseManagementService::class);
        $impact = $service->getImpactSummary($course);

        $this->assertFalse($impact['can_delete_permanently']);
        $this->assertTrue($impact['has_academic_history']);
        $this->assertNotEmpty($impact['blocking_reasons']);

        // Attempt deletion should throw validation exception
        $this->expectException(ValidationException::class);
        $service->delete($course, $admin);
    }

    public function test_safe_course_deletion_permitted_when_no_academic_history_exists(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'status' => 'Draft',
        ]);

        $service = app(CourseManagementService::class);
        $impact = $service->getImpactSummary($course);

        $this->assertTrue($impact['can_delete_permanently']);
        $this->assertFalse($impact['has_academic_history']);

        $service->delete($course, $admin);
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_courses_livewire_archive_and_restore_workflow(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'status' => 'Published',
        ]);

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        Livewire::actingAs($admin)
            ->test(CoursesIndex::class)
            ->call('openArchiveModal', $course->id)
            ->assertSet('archivingCourseId', $course->id)
            ->call('confirmArchiveCourse')
            ->assertDispatched('toast');

        $this->assertSame('Archived', $course->fresh()->status);

        Livewire::actingAs($admin)
            ->test(CoursesIndex::class)
            ->call('restoreCourse', $course->id)
            ->assertDispatched('toast');

        $this->assertSame('Published', $course->fresh()->status);
    }

    // ─────────────────────────────────────────────
    // 4. CERTIFICATES LIFECYCLE TESTS
    // ─────────────────────────────────────────────

    public function test_admin_can_revoke_and_reinstate_certificate_with_audit_events(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $studentUser = $this->createAcademyMember($academy, 'student');

        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $profile = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'academy_id' => $academy->id,
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => 'completed',
        ]);

        $service = app(CertificateIssuanceService::class);
        $certificate = $service->issueCertificate($enrollment, 'classic');

        $this->assertSame('Issued', $certificate->status);
        $this->assertTrue($certificate->isIssued());

        // Revoke certificate with mandatory reason
        $revocationReason = 'Formal administrative audit: suspected plagiarism';
        $revokedCert = $service->revokeCertificate($certificate, $admin, $revocationReason);

        $this->assertSame(Certificate::STATUS_REVOKED, $revokedCert->status);
        $this->assertTrue($revokedCert->isRevoked());
        $this->assertSame($revocationReason, $revokedCert->revocation_reason);
        $this->assertNotNull($revokedCert->revoked_at);

        // Verify immutable audit event created
        $this->assertDatabaseHas('certificate_audit_events', [
            'certificate_id' => $certificate->id,
            'event_type' => 'revoked',
            'actor_id' => $admin->id,
            'reason' => $revocationReason,
        ]);

        // Public verification page test
        $verifyUrl = route('certificates.verify', $certificate->verification_code ?: $certificate->certificate_number);
        $response = $this->get($verifyUrl);
        $response->assertStatus(200);
        $response->assertSee('CERTIFICATE REVOKED');
        $response->assertDontSee($certificate->id); // Internal UUID must never be exposed publicly

        // Reinstate certificate
        $reinstatementReason = 'Administrative review cleared student of all suspicion';
        $reinstatedCert = $service->reinstateCertificate($revokedCert, $admin, $reinstatementReason);

        $this->assertSame(Certificate::STATUS_REINSTATED, $reinstatedCert->status);
        $this->assertTrue($reinstatedCert->isReinstated());
        $this->assertNull($reinstatedCert->revoked_at);
        $this->assertSame($reinstatementReason, $reinstatedCert->reinstatement_reason);

        // Verify reinstatement audit event created
        $this->assertDatabaseHas('certificate_audit_events', [
            'certificate_id' => $certificate->id,
            'event_type' => 'reinstated',
            'actor_id' => $admin->id,
            'reason' => $reinstatementReason,
        ]);

        // Destructive deletion must be blocked
        $this->expectException(DomainException::class);
        $service->deleteCertificate($reinstatedCert, $admin);
    }

    public function test_certificates_livewire_revoke_and_reinstate_workflow(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $studentUser = $this->createAcademyMember($academy, 'student');

        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $profile = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'academy_id' => $academy->id,
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => 'completed',
        ]);

        $service = app(CertificateIssuanceService::class);
        $certificate = $service->issueCertificate($enrollment, 'classic');

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        // Revoke via Livewire
        Livewire::actingAs($admin)
            ->test(CertificatesIndex::class)
            ->call('promptRevoke', $certificate->id)
            ->assertSet('showRevokeModal', true)
            ->set('revocationReason', 'Test Revocation Reason')
            ->call('confirmRevoke')
            ->assertSet('showRevokeModal', false);

        $this->assertSame(Certificate::STATUS_REVOKED, $certificate->fresh()->status);

        // Reinstate via Livewire
        Livewire::actingAs($admin)
            ->test(CertificatesIndex::class)
            ->call('promptReinstate', $certificate->id)
            ->assertSet('showReinstateModal', true)
            ->set('reinstatementReason', 'Test Reinstatement Reason')
            ->call('confirmReinstate')
            ->assertSet('showReinstateModal', false);

        $this->assertSame(Certificate::STATUS_REINSTATED, $certificate->fresh()->status);
    }

    // ─────────────────────────────────────────────
    // 5. AUTHORIZATION & FOUR ROLES INTEGRITY
    // ─────────────────────────────────────────────

    public function test_exactly_four_roles_exist(): void
    {
        $roles = Role::pluck('name')->sort()->values()->all();
        $expected = ['admin', 'student', 'support_staff', 'trainer'];

        $this->assertSame($expected, $roles);
    }

    public function test_non_admin_cannot_perform_lifecycle_operations(): void
    {
        $academy = Academy::factory()->create();
        $trainer = $this->createAcademyMember($academy, 'trainer');
        $student = $this->createAcademyMember($academy, 'student');

        $studentProfile = StudentProfile::factory()->create([
            'user_id' => $student->id,
            'academy_id' => $academy->id,
        ]);

        $studentService = app(StudentManagementService::class);

        // Trainer cannot delete student
        $this->expectException(AuthorizationException::class);
        $studentService->delete($studentProfile, $trainer);
    }

    public function test_last_active_administrator_protection(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        $membership = $admin->academies()->first()->pivot;
        $membershipModel = AcademyUser::find($membership->id);

        $membershipService = app(MembershipManagementService::class);

        // Revoking the only admin must throw ValidationException
        $this->expectException(ValidationException::class);
        $membershipService->revoke($membershipModel, $admin);
    }
}
