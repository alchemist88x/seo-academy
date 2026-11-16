<?php

namespace Tests\Feature\Learning;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Events\CourseCompleted;
use App\Domain\Learning\Events\LessonCompleted;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Learning\Services\CourseNavigationService;
use App\Domain\Learning\Services\LearningProgressService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Events\EnrollmentCompleted;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Livewire\Student\ClassroomPlayer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseCompletionEngineTest extends TestCase
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
            'title' => 'Required Module',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
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
    public function test_1_course_completion_when_all_required_lessons_are_completed(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $progressService = app(LearningProgressService::class);

        $this->assertFalse($enrollment->isCompleted());
        $this->assertNull($enrollment->completed_at);

        // Complete lesson 1: course still incomplete
        $progressService->completeLesson($enrollment, $lesson1, $studentUser);
        $enrollment->refresh();
        $this->assertFalse($enrollment->isCompleted());

        // Complete lesson 2: all lessons complete, enrollment transitions to completed
        $progressService->completeLesson($enrollment, $lesson2, $studentUser);
        $enrollment->refresh();

        $this->assertTrue($enrollment->isCompleted());
        $this->assertSame(Enrollment::STATUS_COMPLETED, $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);
    }

    #[DataProvider('engineProvider')]
    public function test_2_course_remains_incomplete_when_required_lessons_are_outstanding(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $progressService = app(LearningProgressService::class);
        $completionService = app(CourseCompletionService::class);

        $progressService->completeLesson($enrollment, $lesson1, $studentUser);

        $enrollment->refresh();
        $this->assertFalse($completionService->evaluateCourseCompletion($enrollment));
        $this->assertFalse($enrollment->isCompleted());
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->status);
    }

    #[DataProvider('engineProvider')]
    public function test_3_optional_lessons_do_not_incorrectly_block_completion(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module1, $lesson1, $lesson2, $studentUser, $profile, $enrollment] = $this->createHarness($academy);

        // Create an optional module with completion_rule = 'none'
        $optionalModule = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Bonus Optional Module',
            'display_order' => 2,
            'status' => 'Active',
            'completion_rule' => 'none',
        ]);
        $bonusLesson = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $optionalModule->id,
            'title' => 'Bonus Resource',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);

        $progressService = app(LearningProgressService::class);

        // Complete only the required module's lessons
        $progressService->completeLesson($enrollment, $lesson1, $studentUser);
        $progressService->completeLesson($enrollment, $lesson2, $studentUser);

        $enrollment->refresh();

        // Must be completed even though bonusLesson in optional module is NOT completed
        $this->assertTrue($enrollment->isCompleted());
        $this->assertSame(Enrollment::STATUS_COMPLETED, $enrollment->status);
    }

    #[DataProvider('engineProvider')]
    public function test_3b_module_required_count_satisfaction(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        // Add a third lesson to the module, but configure module to require only 2 lessons
        $lesson3 = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'Lesson 3',
            'type' => 'text',
            'display_order' => 3,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);

        $module->update([
            'completion_rule' => 'required_count',
            'required_lessons_count' => 2,
        ]);

        $progressService = app(LearningProgressService::class);

        $progressService->completeLesson($enrollment, $lesson1, $studentUser);
        $progressService->completeLesson($enrollment, $lesson2, $studentUser);

        $enrollment->refresh();

        // 2 of 3 completed, satisfies required_count = 2
        $this->assertTrue($enrollment->isCompleted());
    }

    #[DataProvider('engineProvider')]
    public function test_4_heartbeats_never_trigger_course_completion(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $progressService = app(LearningProgressService::class);

        // Send heartbeats for all lessons
        $progressService->recordHeartbeat($enrollment, $lesson1, $studentUser, 100.0);
        $progressService->recordHeartbeat($enrollment, $lesson2, $studentUser, 200.0);

        $enrollment->refresh();
        $this->assertFalse($enrollment->isCompleted());
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->status);
        $this->assertNull($enrollment->completed_at);
    }

    #[DataProvider('engineProvider')]
    public function test_5_opening_lessons_never_triggers_course_completion(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $progressService = app(LearningProgressService::class);

        $progressService->updateResumeLocation($enrollment, $lesson1, $studentUser);
        $progressService->updateResumeLocation($enrollment, $lesson2, $studentUser);

        $enrollment->refresh();
        $this->assertFalse($enrollment->isCompleted());
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->status);
        $this->assertNull($enrollment->completed_at);
    }

    #[DataProvider('engineProvider')]
    public function test_6_duplicate_completion_requests_are_idempotent(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $progressService = app(LearningProgressService::class);
        $completionService = app(CourseCompletionService::class);

        $progressService->completeLesson($enrollment, $lesson1, $studentUser);
        $progressService->completeLesson($enrollment, $lesson2, $studentUser);

        $enrollment->refresh();
        $this->assertTrue($enrollment->isCompleted());

        // Repeated completeCourse calls return true without error
        $this->assertTrue($completionService->completeCourse($enrollment, $studentUser));
        $this->assertTrue($completionService->completeCourse($enrollment, $studentUser));

        $count = Enrollment::where('id', $enrollment->id)->count();
        $this->assertSame(1, $count);
    }

    #[DataProvider('engineProvider')]
    public function test_7_enrollment_completion_timestamps_remain_stable(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $progressService = app(LearningProgressService::class);

        $progressService->completeLesson($enrollment, $lesson1, $studentUser);
        $progressService->completeLesson($enrollment, $lesson2, $studentUser);

        $enrollment->refresh();
        $originalCompletedAt = $enrollment->completed_at;
        $this->assertNotNull($originalCompletedAt);

        // Fast forward 2 days and revisit/re-complete
        Carbon::setTestNow(now()->addDays(2));

        $progressService->completeLesson($enrollment, $lesson1, $studentUser);
        $enrollment->refresh();

        $this->assertSame($originalCompletedAt->toIso8601String(), $enrollment->completed_at->toIso8601String());

        Carbon::setTestNow();
    }

    #[DataProvider('engineProvider')]
    public function test_8_suspended_cancelled_and_expired_enrollments_cannot_complete(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $completionService = app(CourseCompletionService::class);

        // Mark lessons as completed via DB
        LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);
        LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson2->id,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);

        // 1. Suspended
        $enrollment->update(['status' => Enrollment::STATUS_SUSPENDED]);
        $this->assertFalse($completionService->completeCourse($enrollment));
        $this->assertSame(Enrollment::STATUS_SUSPENDED, $enrollment->fresh()->status);

        // 2. Cancelled
        $enrollment->update(['status' => Enrollment::STATUS_CANCELLED]);
        $this->assertFalse($completionService->completeCourse($enrollment));
        $this->assertSame(Enrollment::STATUS_CANCELLED, $enrollment->fresh()->status);

        // 3. Expired
        $enrollment->update(['status' => Enrollment::STATUS_EXPIRED]);
        $this->assertFalse($completionService->completeCourse($enrollment));
        $this->assertSame(Enrollment::STATUS_EXPIRED, $enrollment->fresh()->status);
    }

    #[DataProvider('engineProvider')]
    public function test_9_completed_enrollment_retains_read_only_access(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $progressService = app(LearningProgressService::class);
        $navigationService = app(CourseNavigationService::class);

        $progressService->completeLesson($enrollment, $lesson1, $studentUser);
        $progressService->completeLesson($enrollment, $lesson2, $studentUser);

        $enrollment->refresh();
        $this->assertTrue($enrollment->isCompleted());

        // Allows course access permanently for read-only review
        $this->assertTrue($enrollment->allowsCourseAccess());
        $this->assertTrue($navigationService->isLessonUnlocked($lesson1, $studentUser, $course));
        $this->assertTrue($navigationService->isLessonUnlocked($lesson2, $studentUser, $course));
    }

    #[DataProvider('engineProvider')]
    public function test_10_multiple_enrollment_isolation(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course1, $module1, $c1L1, $c1L2, $studentUser, $profile, $enrollment1] = $this->createHarness($academy);

        $course2 = Course::factory()->create(['academy_id' => $academy->id]);
        $module2 = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course2->id,
            'title' => 'Course 2 Module',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);
        $c2L1 = Lesson::create([
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

        $progressService = app(LearningProgressService::class);

        // Complete Course 1
        $progressService->completeLesson($enrollment1, $c1L1, $studentUser);
        $progressService->completeLesson($enrollment1, $c1L2, $studentUser);

        $enrollment1->refresh();
        $enrollment2->refresh();

        $this->assertTrue($enrollment1->isCompleted());
        $this->assertFalse($enrollment2->isCompleted());
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment2->status);

        // Certificate issued only for Course 1
        $this->assertDatabaseHas('certificates', [
            'user_id' => $studentUser->id,
            'course_id' => $course1->id,
        ]);
        $this->assertDatabaseMissing('certificates', [
            'user_id' => $studentUser->id,
            'course_id' => $course2->id,
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_11_concurrent_completion_attempts_safely_handled(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $completionService = app(CourseCompletionService::class);

        // Complete lessons via DB
        LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);
        LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson2->id,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);

        // Simulate 3 concurrent/rapid completion executions
        $r1 = $completionService->completeCourse($enrollment, $studentUser);
        $r2 = $completionService->completeCourse($enrollment, $studentUser);
        $r3 = $completionService->completeCourse($enrollment, $studentUser);

        $this->assertTrue($r1);
        $this->assertTrue($r2);
        $this->assertTrue($r3);

        $enrollment->refresh();
        $this->assertTrue($enrollment->isCompleted());

        // Verify only 1 certificate created
        $certCount = Certificate::where('user_id', $studentUser->id)->where('course_id', $course->id)->count();
        $this->assertSame(1, $certCount);
    }

    #[DataProvider('engineProvider')]
    public function test_12_event_dispatch_after_successful_transactions(string $engine): void
    {
        $this->useEngine($engine);

        Event::fake([
            LessonCompleted::class,
            CourseCompleted::class,
            EnrollmentCompleted::class,
        ]);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $progressService = app(LearningProgressService::class);

        // Lesson 1 completed: dispatches LessonCompleted, not CourseCompleted
        $progressService->completeLesson($enrollment, $lesson1, $studentUser);
        Event::assertDispatched(LessonCompleted::class, 1);
        Event::assertNotDispatched(CourseCompleted::class);
        Event::assertNotDispatched(EnrollmentCompleted::class);

        // Lesson 2 completed: dispatches LessonCompleted, CourseCompleted, and EnrollmentCompleted
        $progressService->completeLesson($enrollment, $lesson2, $studentUser);
        Event::assertDispatched(LessonCompleted::class, 2);
        Event::assertDispatched(CourseCompleted::class, 1);
        Event::assertDispatched(EnrollmentCompleted::class, 1);
    }

    #[DataProvider('engineProvider')]
    public function test_13_certificate_issued_on_completion_and_rejected_when_incomplete(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $certService = app(CertificateIssuanceService::class);

        // Direct issue attempt on incomplete enrollment must throw
        $this->expectException(InvalidArgumentException::class);
        $certService->issueCertificate($enrollment);
    }

    #[DataProvider('engineProvider')]
    public function test_14_api_and_classroom_player_integration(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, , $enrollment] = $this->createHarness($academy);

        $this->actingAs($studentUser);

        // Complete lesson 1 and lesson 2 via API
        $this->postJson(route('student.courses.lessons.complete', [$course, $lesson1]))
            ->assertOk()
            ->assertJsonPath('course_completion.is_completed', false);

        $res2 = $this->postJson(route('student.courses.lessons.complete', [$course, $lesson2]))
            ->assertOk()
            ->assertJsonPath('course_completion.is_completed', true);

        $this->assertNotNull($res2->json('course_completion.certificate_number'));

        // Check progress summary endpoint
        $progressRes = $this->getJson(route('student.courses.progress', $course))
            ->assertOk()
            ->assertJsonPath('completion.is_completed', true)
            ->assertJsonPath('completion.is_eligible', true);

        $this->assertNotNull($progressRes->json('completion.certificate_number'));

        // Livewire ClassroomPlayer test: displays completion banner and read-only indicator
        Livewire::test(ClassroomPlayer::class, ['course' => $course, 'lesson' => $lesson1])
            ->assertSet('isCompletedEnrollment', true)
            ->assertSet('isLessonCompleted', true)
            ->assertSee('Congratulations! Course Completed')
            ->assertSee('Certificate:');
    }
}
