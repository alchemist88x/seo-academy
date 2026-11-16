<?php

namespace Tests\Feature\Learning;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\LearningProgressService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Livewire\Student\ClassroomPlayer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LearningProgressEngineTest extends TestCase
{
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
        if ($engine === 'sqlite') {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.foreign_key_constraints' => true,
            ]);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');
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
        }

        Artisan::call('migrate:fresh', ['--database' => $engine, '--force' => true]);
        $this->seed(RoleSeeder::class);
    }

    private function createHarness(Academy $academy): array
    {
        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $module = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Test Module',
            'display_order' => 1,
            'status' => 'Active',
        ]);
        $lesson1 = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'Lesson 1',
            'type' => 'video',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);
        $lesson2 = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'Lesson 2',
            'type' => 'text',
            'display_order' => 2,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);

        $studentUser = User::factory()->create(['is_active' => true]);
        $studentUser->assignRole('student');
        $studentUser->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $profile = StudentProfile::factory()->create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
        ]);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        return [$course, $module, $lesson1, $lesson2, $studentUser, $profile, $enrollment];
    }

    #[DataProvider('engineProvider')]
    public function test_1_record_lesson_activity_creates_in_progress_record_and_updates_timestamps(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $service = app(LearningProgressService::class);

        $progress = $service->recordHeartbeat($enrollment, $lesson1, $studentUser, 42.5);

        $this->assertSame($enrollment->id, $progress->enrollment_id);
        $this->assertSame($lesson1->id, $progress->lesson_id);
        $this->assertSame($studentUser->id, $progress->user_id);
        $this->assertSame('in_progress', $progress->status);
        $this->assertEquals(42.5, (float) $progress->last_position_seconds);
        $this->assertNotNull($progress->last_accessed_at);
        $this->assertNull($progress->completed_at);

        // Verify enrollment resume location updated
        $enrollment->refresh();
        $this->assertSame($lesson1->id, $enrollment->last_active_lesson_id);
        $this->assertNotNull($enrollment->last_active_at);
    }

    #[DataProvider('engineProvider')]
    public function test_2_record_activity_updates_existing_progress_without_creating_duplicates(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $service = app(LearningProgressService::class);

        $first = $service->recordHeartbeat($enrollment, $lesson1, $studentUser, 10.0);
        $second = $service->recordHeartbeat($enrollment, $lesson1, $studentUser, 25.0);

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(25.0, (float) $second->last_position_seconds);

        $count = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson1->id)
            ->count();

        $this->assertSame(1, $count);
    }

    #[DataProvider('engineProvider')]
    public function test_3_rejects_unauthorized_enrollment_access(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $otherUser = User::factory()->create(['is_active' => true]);
        $otherUser->assignRole('student');
        $otherUser->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);
        StudentProfile::factory()->create([
            'academy_id' => $academy->id,
            'user_id' => $otherUser->id,
        ]);

        $service = app(LearningProgressService::class);

        $this->expectException(AuthorizationException::class);
        $service->recordHeartbeat($enrollment, $lesson1, $otherUser, 10.0);
    }

    #[DataProvider('engineProvider')]
    public function test_3b_rejects_inactive_or_suspended_enrollment_access(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $enrollment->update(['status' => 'suspended']);

        $service = app(LearningProgressService::class);

        $this->expectException(AuthorizationException::class);
        $service->completeLesson($enrollment, $lesson1, $studentUser);
    }

    #[DataProvider('engineProvider')]
    public function test_4_rejects_lessons_outside_enrolled_course(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $otherCourse = Course::factory()->create(['academy_id' => $academy->id]);
        $otherModule = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $otherCourse->id,
            'title' => 'Other Module',
            'display_order' => 1,
            'status' => 'Active',
        ]);
        $foreignLesson = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $otherModule->id,
            'title' => 'Foreign Lesson',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);

        $service = app(LearningProgressService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->recordHeartbeat($enrollment, $foreignLesson, $studentUser, 5.0);
    }

    #[DataProvider('engineProvider')]
    public function test_5_explicit_lesson_completion(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $service = app(LearningProgressService::class);

        $progress = $service->completeLesson($enrollment, $lesson1, $studentUser);

        $this->assertSame('completed', $progress->status);
        $this->assertSame(100, $progress->progress_percent);
        $this->assertNotNull($progress->completed_at);

        $enrollment->refresh();
        $this->assertSame($lesson1->id, $enrollment->last_active_lesson_id);
    }

    #[DataProvider('engineProvider')]
    public function test_6_idempotent_completion_requests(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $service = app(LearningProgressService::class);

        $first = $service->completeLesson($enrollment, $lesson1, $studentUser);
        $originalCompletedAt = $first->completed_at;

        Carbon::setTestNow(now()->addMinutes(15));

        $second = $service->completeLesson($enrollment, $lesson1, $studentUser);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('completed', $second->status);
        $this->assertSame($originalCompletedAt->toIso8601String(), $second->completed_at->toIso8601String());

        Carbon::setTestNow();
    }

    #[DataProvider('engineProvider')]
    public function test_7_opening_lesson_updates_resume_location_without_completing_it(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $service = app(LearningProgressService::class);

        $service->updateResumeLocation($enrollment, $lesson1, $studentUser);

        $enrollment->refresh();
        $this->assertSame($lesson1->id, $enrollment->last_active_lesson_id);
        $this->assertNotNull($enrollment->last_active_at);

        $progress = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson1->id)
            ->first();

        $this->assertNotNull($progress);
        $this->assertSame('in_progress', $progress->status);
        $this->assertSame(0, $progress->progress_percent);
        $this->assertNull($progress->completed_at);
    }

    #[DataProvider('engineProvider')]
    public function test_8_enrollment_specific_progress_isolation(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course1, $module1, $lesson1, , $studentUser, $profile, $enrollment1] = $this->createHarness($academy);

        $course2 = Course::factory()->create(['academy_id' => $academy->id]);
        $module2 = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course2->id,
            'title' => 'Course 2 Module',
            'display_order' => 1,
            'status' => 'Active',
        ]);
        $course2Lesson = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module2->id,
            'title' => 'Course 2 Lesson',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);
        $enrollment2 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course2->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        $service = app(LearningProgressService::class);

        $service->completeLesson($enrollment1, $lesson1, $studentUser);

        $this->assertTrue($service->isLessonCompleted($enrollment1, $lesson1));
        $this->assertFalse($service->isLessonCompleted($enrollment2, $course2Lesson));

        $enrollment1Progress = $service->getProgressForEnrollment($enrollment1);
        $enrollment2Progress = $service->getProgressForEnrollment($enrollment2);

        $this->assertCount(1, $enrollment1Progress);
        $this->assertCount(0, $enrollment2Progress);
    }

    #[DataProvider('engineProvider')]
    public function test_9_resume_location_persistence(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $service = app(LearningProgressService::class);

        $service->updateResumeLocation($enrollment, $lesson2, $studentUser);

        $resumeLesson = $service->getResumeLesson($enrollment, $studentUser);

        $this->assertNotNull($resumeLesson);
        $this->assertSame($lesson2->id, $resumeLesson->id);
    }

    #[DataProvider('engineProvider')]
    public function test_10_resume_fallback_when_stored_lesson_is_unavailable_or_deleted(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $service = app(LearningProgressService::class);

        $service->updateResumeLocation($enrollment, $lesson2, $studentUser);

        // Delete lesson2 to trigger fallback
        $lesson2->delete();

        $resumeLesson = $service->getResumeLesson($enrollment, $studentUser);

        $this->assertNotNull($resumeLesson);
        $this->assertSame($lesson1->id, $resumeLesson->id);
    }

    #[DataProvider('engineProvider')]
    public function test_11_course_progress_calculations(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $service = app(LearningProgressService::class);

        // Initial state: 0/2 completed
        $summary0 = $service->getCourseProgressSummary($enrollment);
        $this->assertSame(2, $summary0['total_lessons']);
        $this->assertSame(0, $summary0['completed_lessons']);
        $this->assertSame(2, $summary0['incomplete_lessons']);
        $this->assertSame(0, $summary0['completion_percentage']);
        $this->assertSame($lesson1->id, $summary0['next_incomplete_lesson']->id);

        // Complete 1 lesson: 1/2 completed (50%)
        $service->completeLesson($enrollment, $lesson1, $studentUser);
        $summary1 = $service->getCourseProgressSummary($enrollment);
        $this->assertSame(1, $summary1['completed_lessons']);
        $this->assertSame(1, $summary1['incomplete_lessons']);
        $this->assertSame(50, $summary1['completion_percentage']);
        $this->assertSame($lesson2->id, $summary1['next_incomplete_lesson']->id);

        // Complete second lesson: 2/2 completed (100%)
        $service->completeLesson($enrollment, $lesson2, $studentUser);
        $summary2 = $service->getCourseProgressSummary($enrollment);
        $this->assertSame(2, $summary2['completed_lessons']);
        $this->assertSame(0, $summary2['incomplete_lessons']);
        $this->assertSame(100, $summary2['completion_percentage']);
        $this->assertNull($summary2['next_incomplete_lesson']);
    }

    #[DataProvider('engineProvider')]
    public function test_12_multiple_enrollments_for_the_same_student_have_distinct_summaries(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course1, $module1, $lesson1, $lesson2, $studentUser, $profile, $enrollment1] = $this->createHarness($academy);

        $course2 = Course::factory()->create(['academy_id' => $academy->id]);
        $module2 = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course2->id,
            'title' => 'Module C2',
            'display_order' => 1,
            'status' => 'Active',
        ]);
        $c2Lesson1 = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module2->id,
            'title' => 'C2 L1',
            'type' => 'video',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);
        $enrollment2 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course2->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        $service = app(LearningProgressService::class);

        $service->completeLesson($enrollment1, $lesson1, $studentUser);

        $summary1 = $service->getCourseProgressSummary($enrollment1);
        $summary2 = $service->getCourseProgressSummary($enrollment2);

        $this->assertSame(50, $summary1['completion_percentage']);
        $this->assertSame(0, $summary2['completion_percentage']);
    }

    #[DataProvider('engineProvider')]
    public function test_13_concurrent_or_repeated_activity_requests_safely_handled(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $service = app(LearningProgressService::class);

        for ($i = 1; $i <= 5; $i++) {
            $service->recordHeartbeat($enrollment, $lesson1, $studentUser, (float) ($i * 10));
        }

        $records = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson1->id)
            ->get();

        $this->assertCount(1, $records);
        $this->assertEquals(50.0, (float) $records->first()->last_position_seconds);
    }

    #[DataProvider('engineProvider')]
    public function test_14_legacy_progress_compatibility(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        // Insert an unassociated legacy progress record
        $legacyId = (string) Str::uuid();
        DB::table('lesson_progress')->insert([
            'id' => $legacyId,
            'academy_id' => $academy->id,
            'enrollment_id' => null,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson1->id,
            'status' => 'in_progress',
            'last_position_seconds' => 77.0,
            'progress_percent' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(LearningProgressService::class);

        // Recording activity must claim the legacy record rather than failing on unique constraint
        $progress = $service->recordHeartbeat($enrollment, $lesson1, $studentUser, 90.0);

        $this->assertSame($legacyId, $progress->id);
        $this->assertSame($enrollment->id, $progress->enrollment_id);
        $this->assertEquals(90.0, (float) $progress->last_position_seconds);
    }

    #[DataProvider('engineProvider')]
    public function test_15_correct_handling_of_deleted_lessons_and_enrollments(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $service = app(LearningProgressService::class);

        $service->completeLesson($enrollment, $lesson1, $studentUser);

        // Delete the lesson
        $lesson1->delete();

        $enrollment->refresh();
        $this->assertNull($enrollment->last_active_lesson_id);

        // Delete the enrollment via DB table
        DB::table('enrollments')->where('id', $enrollment->id)->delete();

        $remainingProgress = LessonProgress::query()->where('enrollment_id', $enrollment->id)->count();
        $this->assertSame(0, $remainingProgress);
    }

    #[DataProvider('engineProvider')]
    public function test_16_http_endpoints_activity_complete_progress_resume(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $this->actingAs($studentUser);

        // 1. Post Activity (Heartbeat)
        $response = $this->postJson(route('student.courses.lessons.activity', [$course, $lesson1]), [
            'position_seconds' => 33.5,
        ]);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('progress.status', 'in_progress')
            ->assertJsonPath('progress.last_position_seconds', 33.5);

        // 2. Complete Lesson
        $response = $this->postJson(route('student.courses.lessons.complete', [$course, $lesson1]));
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('progress.status', 'completed')
            ->assertJsonPath('summary.completed_lessons', 1);

        // 3. Get Course Progress Summary
        $response = $this->getJson(route('student.courses.progress', $course));
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.total_lessons', 2)
            ->assertJsonPath('summary.completed_lessons', 1)
            ->assertJsonPath('summary.completion_percentage', 50);

        // 4. Get Resume Lesson
        $response = $this->getJson(route('student.courses.resume', $course));
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('resume_lesson.id', $lesson1->id);
    }

    #[DataProvider('engineProvider')]
    public function test_17_classroom_player_livewire_component_complete_and_heartbeat(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        $this->actingAs($studentUser);

        Livewire::test(ClassroomPlayer::class, ['course' => $course, 'lesson' => $lesson1])
            ->assertSet('isLessonCompleted', false)
            ->call('recordHeartbeat', 20.0)
            ->call('completeLesson')
            ->assertSet('isLessonCompleted', true);

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
        ]);
    }
}
