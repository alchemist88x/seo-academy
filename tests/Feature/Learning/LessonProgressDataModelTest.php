<?php

namespace Tests\Feature\Learning;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LessonProgressDataModelTest extends TestCase
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
    public function test_enrollment_can_have_multiple_lesson_progress_records(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, $profile, $enrollment] = $this->createHarness($academy);

        $prog1 = LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        $prog2 = LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson2->id,
            'status' => 'in_progress',
            'last_position_seconds' => 120.50,
            'progress_percent' => 40,
        ]);

        $this->assertCount(2, $enrollment->lessonProgress);
        $this->assertTrue($enrollment->lessonProgress->contains('id', $prog1->id));
        $this->assertTrue($enrollment->lessonProgress->contains('id', $prog2->id));

        $this->assertSame($enrollment->id, $prog1->enrollment->id);
        $this->assertSame($enrollment->id, $prog2->enrollment->id);
        $this->assertSame($studentUser->id, $prog1->user->id);
        $this->assertSame($lesson1->id, $prog1->lesson->id);
    }

    #[DataProvider('engineProvider')]
    public function test_last_active_lesson_tracking_and_set_null_on_delete(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, $profile, $enrollment] = $this->createHarness($academy);

        $now = now()->startOfSecond();
        $enrollment->update([
            'last_active_lesson_id' => $lesson1->id,
            'last_active_at' => $now,
        ]);

        $fresh = $enrollment->fresh();
        $this->assertSame($lesson1->id, $fresh->last_active_lesson_id);
        $this->assertSame($lesson1->id, $fresh->lastActiveLesson->id);
        $this->assertEquals($now->toDateTimeString(), $fresh->last_active_at->toDateTimeString());

        // Deleting the lesson must set last_active_lesson_id to null via foreign key constraint
        $lesson1->delete();

        $freshAfterDelete = $enrollment->fresh();
        $this->assertNull($freshAfterDelete->last_active_lesson_id);
        $this->assertNull($freshAfterDelete->lastActiveLesson);
    }

    #[DataProvider('engineProvider')]
    public function test_enrollment_cascade_deletion_removes_associated_progress(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, $lesson2, $studentUser, $profile, $enrollment] = $this->createHarness($academy);

        $prog = LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);

        $this->assertDatabaseHas('lesson_progress', ['id' => $prog->id]);

        // Explicitly delete enrollment via DB table to test cascade constraint
        DB::table('enrollments')->where('id', $enrollment->id)->delete();

        $this->assertDatabaseMissing('lesson_progress', ['id' => $prog->id]);
    }

    #[DataProvider('engineProvider')]
    public function test_repeat_enrollment_supports_independent_progress_records(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, $profile, $enrollment1] = $this->createHarness($academy);

        // Progress for enrollment 1: completed
        $prog1 = LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment1->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now()->subMonths(6),
        ]);

        // Create a second enrollment for the student in the same academy (e.g. second course)
        $course2 = Course::factory()->create(['academy_id' => $academy->id]);
        $enrollment2 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course2->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        // Progress for enrollment 2 on the same student user: in_progress with separate position
        $prog2 = LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment2->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson1->id,
            'status' => 'in_progress',
            'last_position_seconds' => 45.0,
            'progress_percent' => 15,
        ]);

        $this->assertNotSame($prog1->id, $prog2->id);

        // Verify independent querying per enrollment
        $enrollment1Progress = LessonProgress::forEnrollment($enrollment1)->get();
        $enrollment2Progress = LessonProgress::forEnrollment($enrollment2)->get();

        $this->assertCount(1, $enrollment1Progress);
        $this->assertSame('completed', $enrollment1Progress->first()->status);

        $this->assertCount(1, $enrollment2Progress);
        $this->assertSame('in_progress', $enrollment2Progress->first()->status);
        $this->assertEquals(45.0, (float) $enrollment2Progress->first()->last_position_seconds);
    }

    #[DataProvider('engineProvider')]
    public function test_unique_constraint_enforces_one_record_per_enrollment_and_lesson(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::factory()->create();
        [$course, $module, $lesson1, , $studentUser, , $enrollment] = $this->createHarness($academy);

        LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
        ]);

        $this->expectException(QueryException::class);

        // Attempting to create duplicate progress for same enrollment and lesson must violate UNIQUE(enrollment_id, lesson_id)
        LessonProgress::create([
            'academy_id' => $academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $studentUser->id,
            'lesson_id' => $lesson1->id,
            'status' => 'in_progress',
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_legacy_data_migration_backfills_unambiguous_records_and_preserves_ambiguous(string $engine): void
    {
        $this->useEngine($engine);

        // 1. Roll back the 2 new migrations so we are at the old schema state
        Artisan::call('migrate:rollback', ['--database' => $engine, '--step' => 2, '--force' => true]);

        $academy = Academy::factory()->create();
        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $module = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Module Alpha',
            'display_order' => 1,
            'status' => 'Active',
        ]);
        $lessonA = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'Lesson A',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);
        $lessonB = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'Lesson B',
            'type' => 'video',
            'display_order' => 2,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);

        // Student 1: Unambiguous enrollment in Course
        $student1 = User::factory()->create();
        $profile1 = StudentProfile::factory()->create(['academy_id' => $academy->id, 'user_id' => $student1->id]);
        $enrollment1 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile1->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        // Student 2: No enrollment in Course (Unreconciled)
        $student2 = User::factory()->create();
        $profile2 = StudentProfile::factory()->create(['academy_id' => $academy->id, 'user_id' => $student2->id]);

        $completedTime = now()->subDays(5)->startOfSecond();

        // Insert legacy progress records directly into table without enrollment_id (since column doesn't exist yet)
        $id1 = (string) Str::uuid();
        DB::table('lesson_progress')->insert([
            'id' => $id1,
            'academy_id' => $academy->id,
            'user_id' => $student1->id,
            'lesson_id' => $lessonA->id,
            'status' => 'completed',
            'last_position_seconds' => 150.00,
            'watch_seconds' => 150.00,
            'progress_percent' => 100,
            'completed_at' => $completedTime,
            'last_accessed_at' => $completedTime,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id2 = (string) Str::uuid();
        DB::table('lesson_progress')->insert([
            'id' => $id2,
            'academy_id' => $academy->id,
            'user_id' => $student2->id,
            'lesson_id' => $lessonB->id,
            'status' => 'in_progress',
            'last_position_seconds' => 30.00,
            'watch_seconds' => 30.00,
            'progress_percent' => 20,
            'completed_at' => null,
            'last_accessed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Run migrate up: applies the 2 new additive migrations
        $exitCode = Artisan::call('migrate', ['--database' => $engine, '--force' => true]);
        $this->assertSame(0, $exitCode);

        // 3. Verify results
        $rec1 = DB::table('lesson_progress')->where('id', $id1)->first();
        $this->assertNotNull($rec1);
        $this->assertSame($enrollment1->id, $rec1->enrollment_id, 'Unambiguous record must be backfilled with enrollment_id');
        $this->assertSame('completed', $rec1->status);
        $this->assertEquals(150.00, (float) $rec1->watch_seconds);
        $this->assertSame(100, (int) $rec1->progress_percent);
        $this->assertEquals($completedTime->toDateTimeString(), Carbon::parse($rec1->completed_at)->toDateTimeString());

        $rec2 = DB::table('lesson_progress')->where('id', $id2)->first();
        $this->assertNotNull($rec2);
        $this->assertNull($rec2->enrollment_id, 'Unreconciled record must be preserved safely with NULL enrollment_id');
        $this->assertSame('in_progress', $rec2->status);
        $this->assertEquals(30.00, (float) $rec2->watch_seconds);
    }
}
