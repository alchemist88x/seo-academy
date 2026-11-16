<?php

namespace Tests\Feature\Courses;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseTrainer;
use App\Domain\Courses\Services\CourseStructureService;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Platform\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CourseStructureTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $createdPrivatePaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPrivatePaths as $path) {
            Storage::disk('private_media')->delete($path);
        }

        parent::tearDown();
    }

    private function createAcademyUser(Academy $academy, string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $user;
    }

    public function test_module_crud_reordering_and_audit_log_verification(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');
        $course = Course::factory()->create(['academy_id' => $academy->id]);

        /** @var CourseStructureService $service */
        $service = app(CourseStructureService::class);

        $modA = $service->createModule($course, [
            'title' => 'Module Alpha',
            'description' => 'First module',
            'completion_rule' => 'all_lessons',
        ], $admin);

        $modB = $service->createModule($course, [
            'title' => 'Module Beta',
            'completion_rule' => 'required_count',
            'required_lessons_count' => 2,
        ], $admin);

        $this->assertSame(1, $modA->display_order);
        $this->assertSame(2, $modB->display_order);
        $this->assertSame('required_count', $modB->completion_rule);
        $this->assertSame(2, $modB->required_lessons_count);

        $updated = $service->updateModule($modA, [
            'title' => 'Module Alpha Updated',
            'description' => 'Updated description',
            'status' => 'Active',
        ], $admin);
        $this->assertSame('Module Alpha Updated', $updated->title);

        $service->reorderModules($course, [$modB->id, $modA->id], $admin);
        $this->assertSame(1, $modB->fresh()->display_order);
        $this->assertSame(2, $modA->fresh()->display_order);

        $service->deleteModule($modB, $admin);
        $this->assertDatabaseMissing('modules', ['id' => $modB->id]);

        $this->assertTrue(AuditLog::where('action', 'module.created')->where('subject_id', $modA->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'module.updated')->where('subject_id', $modA->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'module.reordered')->where('subject_id', $course->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'module.deleted')->where('subject_id', $modB->id)->exists());
    }

    public function test_module_safe_delete_guard_blocks_when_lesson_progress_exists_until_confirmed(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');
        $student = $this->createAcademyUser($academy, 'student');
        $course = Course::factory()->create(['academy_id' => $academy->id]);

        /** @var CourseStructureService $service */
        $service = app(CourseStructureService::class);
        $module = $service->createModule($course, ['title' => 'Module With Progress'], $admin);
        $lesson = $service->createLesson($module, ['title' => 'Tracked Lesson', 'type' => 'video'], $admin);

        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => 'in_progress',
            'progress_percent' => 50,
            'last_accessed_at' => now(),
        ]);

        try {
            $service->deleteModule($module, $admin, false);
            $this->fail('Expected ValidationException when deleting module with learner progress without confirmation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('module', $e->errors());
        }

        $this->assertDatabaseHas('modules', ['id' => $module->id]);

        $service->deleteModule($module, $admin, true);
        $this->assertDatabaseMissing('modules', ['id' => $module->id]);
    }

    public function test_lesson_crud_for_all_six_types_reordering_and_prerequisite_validation(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');
        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $otherCourse = Course::factory()->create(['academy_id' => $academy->id]);

        /** @var CourseStructureService $service */
        $service = app(CourseStructureService::class);
        $module = $service->createModule($course, ['title' => 'Core Curriculum'], $admin);
        $otherModule = $service->createModule($otherCourse, ['title' => 'Other Course Module'], $admin);
        $otherCourseLesson = $service->createLesson($otherModule, ['title' => 'External Course Lesson', 'type' => 'text'], $admin);

        $createdLessons = [];
        foreach (Lesson::TYPES as $index => $type) {
            $createdLessons[$type] = $service->createLesson($module, [
                'title' => "Lesson {$type}",
                'type' => $type,
                'content' => $type === 'text' ? 'Rich text body' : null,
                'resource_provider' => $type === 'external' ? 'external_url' : 'internal',
                'resource_reference' => $type === 'external' ? 'https://example.com/resource' : null,
                'duration_seconds' => ($index + 1) * 60,
                'visibility' => $index === 0 ? 'preview' : 'enrolled',
            ], $admin);

            $this->assertSame($type, $createdLessons[$type]->type);
        }

        $this->assertCount(6, $createdLessons);

        // Update lesson
        $updatedText = $service->updateLesson($createdLessons['text'], [
            'title' => 'Updated Text Lesson',
            'type' => 'text',
            'content' => 'Updated markdown body',
        ], $admin);
        $this->assertSame('Updated Text Lesson', $updatedText->title);

        // Reorder lessons
        $reversedIds = array_reverse(array_map(static fn (Lesson $l): string => $l->id, $createdLessons));
        $service->reorderLessons($module, $reversedIds, $admin);
        $this->assertSame(1, $createdLessons['assignment']->fresh()->display_order);
        $this->assertSame(6, $createdLessons['video']->fresh()->display_order);

        // Prerequisite validation: self-reference rejected
        try {
            $service->syncLessonPrerequisites($createdLessons['text'], [$createdLessons['text']->id], $admin);
            $this->fail('Expected self-prerequisite ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prerequisite_ids', $e->errors());
        }

        // Cross-course prerequisite rejected
        try {
            $service->syncLessonPrerequisites($createdLessons['text'], [$otherCourseLesson->id], $admin);
            $this->fail('Expected cross-course prerequisite ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prerequisite_ids', $e->errors());
        }

        // Valid prerequisite A -> B, then cycle B -> A rejected
        $service->syncLessonPrerequisites($createdLessons['text'], [$createdLessons['video']->id], $admin);
        $this->assertDatabaseHas('lesson_prerequisites', [
            'lesson_id' => $createdLessons['text']->id,
            'prerequisite_lesson_id' => $createdLessons['video']->id,
        ]);

        try {
            $service->syncLessonPrerequisites($createdLessons['video'], [$createdLessons['text']->id], $admin);
            $this->fail('Expected circular prerequisite ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prerequisite_ids', $e->errors());
        }

        // Delete lesson
        $service->deleteLesson($createdLessons['live_class'], $admin);
        $this->assertDatabaseMissing('lessons', ['id' => $createdLessons['live_class']->id]);
    }

    public function test_private_file_upload_and_delivery_security(): void
    {
        $academyA = Academy::factory()->create();

        $adminA = $this->createAcademyUser($academyA, 'admin');
        $studentA = $this->createAcademyUser($academyA, 'student');
        $suspendedStudent = $this->createAcademyUser($academyA, 'student');
        $suspendedStudent->update(['is_active' => false]);

        $courseA = Course::factory()->create([
            'academy_id' => $academyA->id,
            'status' => 'Published',
            'visibility' => 'public',
        ]);

        /** @var CourseStructureService $service */
        $service = app(CourseStructureService::class);
        $moduleA = $service->createModule($courseA, ['title' => 'Media Module'], $adminA);

        $mp4File = UploadedFile::fake()->create('intro-video.mp4', 100, 'video/mp4');
        $lesson = $service->createLesson($moduleA, [
            'title' => 'Private Video Lesson',
            'type' => 'video',
            'visibility' => 'preview',
            'is_free_preview' => true,
        ], $adminA, $mp4File);

        $lessonMedia = $lesson->media;
        $this->assertNotNull($lessonMedia);
        $this->assertSame('private_media', $lessonMedia->disk);
        $this->createdPrivatePaths[] = $lessonMedia->path;

        $this->assertTrue(Storage::disk('private_media')->exists($lessonMedia->path));
        $this->assertFalse(Storage::disk('public')->exists($lessonMedia->path));

        // Attach private PDF material
        $pdfFile = UploadedFile::fake()->create('checklist.pdf', 50, 'application/pdf');
        $material = $service->attachLessonMaterial($lesson, 'SEO Checklist', 'Downloadable PDF', $pdfFile, $adminA);
        $materialMedia = $material->media;
        $this->assertNotNull($materialMedia);
        $this->assertSame('private_media', $materialMedia->disk);
        $this->createdPrivatePaths[] = $materialMedia->path;
        $this->assertTrue(Storage::disk('private_media')->exists($materialMedia->path));

        // Invalid MIME rejected
        $exeFile = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');
        try {
            $service->attachLessonMaterial($lesson, 'Bad File', null, $exeFile, $adminA);
            $this->fail('Expected ValidationException for invalid MIME type.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }

        // Authorized student in same academy can stream free-preview lesson media & material
        $this->actingAs($studentA)
            ->get(route('media.show', $lessonMedia))
            ->assertOk();

        $this->actingAs($studentA)
            ->get(route('media.show', $materialMedia))
            ->assertOk();

        // Suspended user is denied (403)
        $this->actingAs($suspendedStudent)
            ->get(route('media.show', $lessonMedia))
            ->assertForbidden();

        // Non-preview lesson media is denied (403) for unenrolled student in same academy
        $lockedLesson = $service->createLesson($moduleA, [
            'title' => 'Locked Video Lesson',
            'type' => 'video',
            'visibility' => 'enrolled',
            'is_free_preview' => false,
        ], $adminA, UploadedFile::fake()->create('locked.mp4', 50, 'video/mp4'));
        $this->createdPrivatePaths[] = $lockedLesson->media->path;

        $this->actingAs($studentA)
            ->get(route('media.show', $lockedLesson->media))
            ->assertForbidden();
    }

    public function test_trainer_structure_route_authorization(): void
    {
        $academy = Academy::factory()->create();
        $assignedTrainer = $this->createAcademyUser($academy, 'trainer');
        $unassignedTrainer = $this->createAcademyUser($academy, 'trainer');

        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'title' => 'Trainer Structure Course',
        ]);

        CourseTrainer::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'user_id' => $assignedTrainer->id,
            'is_primary' => true,
            'created_at' => now(),
        ]);

        $this->actingAs($assignedTrainer)
            ->get(route('trainer.courses.structure', $course))
            ->assertOk()
            ->assertSee('Trainer Structure Course');

        $this->actingAs($unassignedTrainer)
            ->get(route('trainer.courses.structure', $course))
            ->assertForbidden();
    }

    public function test_student_lesson_preview_rules(): void
    {
        $academyA = Academy::factory()->create();

        $adminA = $this->createAcademyUser($academyA, 'admin');
        $studentA = $this->createAcademyUser($academyA, 'student');
        $suspendedStudent = $this->createAcademyUser($academyA, 'student');
        $suspendedStudent->update(['is_active' => false]);

        $publishedCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'status' => 'Published',
            'visibility' => 'public',
        ]);
        $draftCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'status' => 'Draft',
            'visibility' => 'public',
        ]);

        /** @var CourseStructureService $service */
        $service = app(CourseStructureService::class);
        $pubModule = $service->createModule($publishedCourse, ['title' => 'Published Module'], $adminA);
        $draftModule = $service->createModule($draftCourse, ['title' => 'Draft Module'], $adminA);

        $freePreviewLesson = $service->createLesson($pubModule, [
            'title' => 'Free Preview Lesson',
            'type' => 'text',
            'content' => 'Welcome to SEO Academy!',
            'visibility' => 'preview',
            'is_free_preview' => true,
        ], $adminA);

        $hiddenLesson = $service->createLesson($pubModule, [
            'title' => 'Hidden Lesson',
            'type' => 'text',
            'visibility' => 'hidden',
            'is_free_preview' => false,
        ], $adminA);

        $enrolledOnlyLesson = $service->createLesson($pubModule, [
            'title' => 'Enrolled Only Lesson',
            'type' => 'text',
            'visibility' => 'enrolled',
            'is_free_preview' => false,
        ], $adminA);

        $draftCoursePreviewLesson = $service->createLesson($draftModule, [
            'title' => 'Draft Course Lesson',
            'type' => 'text',
            'visibility' => 'preview',
            'is_free_preview' => true,
        ], $adminA);

        // Allowed: student in same academy on Published visible course + is_free_preview
        $this->actingAs($studentA)
            ->get(route('lessons.preview', ['course' => $publishedCourse, 'lesson' => $freePreviewLesson]))
            ->assertOk()
            ->assertSee('Free Preview Lesson');

        // Denied (403): hidden lesson
        $this->actingAs($studentA)
            ->get(route('lessons.preview', ['course' => $publishedCourse, 'lesson' => $hiddenLesson]))
            ->assertForbidden();

        // Denied (403): non-preview lesson
        $this->actingAs($studentA)
            ->get(route('lessons.preview', ['course' => $publishedCourse, 'lesson' => $enrolledOnlyLesson]))
            ->assertForbidden();

        // Denied (403): Draft course
        $this->actingAs($studentA)
            ->get(route('lessons.preview', ['course' => $draftCourse, 'lesson' => $draftCoursePreviewLesson]))
            ->assertForbidden();

        // Denied: suspended student
        $this->actingAs($suspendedStudent)
            ->get(route('lessons.preview', ['course' => $publishedCourse, 'lesson' => $freePreviewLesson]))
            ->assertForbidden();
    }
}
