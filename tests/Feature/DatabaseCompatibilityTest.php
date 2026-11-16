<?php

namespace Tests\Feature;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Media;
use App\Domain\Platform\Services\DatabaseInstallerService;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Video\Models\VideoProgress;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DatabaseCompatibilityTest extends TestCase
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
        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
        ]);
        app('cache')->forgetDriver();

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

        $this->assertSame($engine, DB::connection()->getDriverName());
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--database' => $engine, '--force' => true]));
    }

    #[DataProvider('engineProvider')]
    public function test_all_migrations_run_and_rollback_cleanly(string $engine): void
    {
        $this->useEngine($engine);

        $this->assertSame(count(DatabaseInstallerService::KNOWN_MIGRATIONS), DB::table('migrations')->count());
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('academies'));
        $this->assertTrue(Schema::hasTable('courses'));
        $this->assertTrue(Schema::hasTable('course_categories'));
        $this->assertTrue(Schema::hasTable('course_trainers'));
        $this->assertTrue(Schema::hasTable('course_prerequisites'));
        $this->assertTrue(Schema::hasTable('lesson_prerequisites'));
        $this->assertTrue(Schema::hasTable('lesson_progress'));
        $this->assertTrue(Schema::hasTable('video_progress'));
        $this->assertTrue(Schema::hasTable('assignment_submissions'));
        $this->assertTrue(Schema::hasTable('audit_log'));
        $this->assertTrue(Schema::hasTable('media'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('class_videos'));
        $this->assertTrue(Schema::hasTable('video_events'));
        $this->assertTrue(Schema::hasTable('practice_checks'));
        $this->assertTrue(Schema::hasTable('admissions'));
        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertTrue(Schema::hasTable('portal_menus'));
        $this->assertTrue(Schema::hasTable('quiz_questions'));
        $this->assertTrue(Schema::hasTable('academy_ai_credentials'));
        $this->assertTrue(Schema::hasTable('trainer_profiles'));
        $this->assertTrue(Schema::hasTable('invitations'));
        $this->assertTrue(Schema::hasTable('support_tickets'));
        $this->assertTrue(Schema::hasTable('support_ticket_messages'));
        $this->assertTrue(Schema::hasTable('notification_preferences'));
        $this->assertTrue(Schema::hasTable('backups'));

        $rollbackExit = Artisan::call('migrate:rollback', ['--database' => $engine, '--force' => true]);
        $this->assertSame(0, $rollbackExit);
        $this->assertFalse(Schema::hasTable('courses'));
        $this->assertFalse(Schema::hasTable('users'));
    }

    #[DataProvider('engineProvider')]
    public function test_transactions_commit_and_rollback_cleanly(string $engine): void
    {
        $this->useEngine($engine);

        DB::transaction(function (): void {
            Academy::create([
                'name' => 'Committed Academy',
                'slug' => 'committed-academy',
                'is_active' => true,
            ]);
        });

        $this->assertDatabaseHas('academies', ['slug' => 'committed-academy']);

        try {
            DB::transaction(function (): void {
                User::factory()->create([
                    'email' => 'rolled-back-user@example.com',
                ]);

                throw new RuntimeException('Simulated failure inside transaction');
            });
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated failure inside transaction', $e->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'rolled-back-user@example.com']);
        $this->assertDatabaseHas('academies', ['slug' => 'committed-academy']);
    }

    #[DataProvider('engineProvider')]
    public function test_json_fields_round_trip_accurately(string $engine): void
    {
        $this->useEngine($engine);

        $academySettings = [
            'theme' => 'dark',
            'features' => ['ai_tutor' => true, 'badges' => ['seo', 'analytics']],
            'max_upload_mb' => 250,
        ];
        $academy = Academy::create([
            'name' => 'JSON Academy',
            'slug' => 'json-academy',
            'settings' => $academySettings,
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $auditProperties = [
            'before' => ['status' => 'Draft'],
            'after' => ['status' => 'Active', 'tags' => ['seo', 'technical']],
        ];
        $auditLog = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'course.published',
            'subject_type' => Academy::class,
            'subject_id' => $academy->id,
            'properties' => $auditProperties,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $mediaMetadata = [
            'width' => 1920,
            'height' => 1080,
            'codecs' => ['h264', 'aac'],
            'nested' => ['hls' => true],
        ];
        $media = Media::create([
            'model_type' => Academy::class,
            'model_id' => $academy->id,
            'collection' => 'videos',
            'disk' => 'local',
            'path' => 'videos/intro.mp4',
            'filename' => 'intro.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 102400,
            'metadata' => $mediaMetadata,
            'created_at' => now(),
        ]);

        $freshAcademySettings = $academy->fresh()->settings;
        $freshAuditProperties = $auditLog->fresh()->properties;
        $freshMediaMetadata = $media->fresh()->metadata;

        $this->assertEquals($academySettings, $freshAcademySettings);
        $this->assertSame(true, $freshAcademySettings['features']['ai_tutor']);
        $this->assertSame(['seo', 'analytics'], $freshAcademySettings['features']['badges']);
        $this->assertSame(250, $freshAcademySettings['max_upload_mb']);

        $this->assertEquals($auditProperties, $freshAuditProperties);
        $this->assertSame(['seo', 'technical'], $freshAuditProperties['after']['tags']);

        $this->assertEquals($mediaMetadata, $freshMediaMetadata);
        $this->assertSame(1920, $freshMediaMetadata['width']);
        $this->assertSame(['h264', 'aac'], $freshMediaMetadata['codecs']);
        $this->assertSame(true, $freshMediaMetadata['nested']['hls']);
    }

    #[DataProvider('engineProvider')]
    public function test_timestamps_are_populated_and_preserved_accurately(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::create([
            'name' => 'Timestamp Academy',
            'slug' => 'timestamp-academy',
            'is_active' => true,
        ]);

        $this->assertNotNull($academy->created_at);
        $this->assertNotNull($academy->updated_at);

        $customTime = Carbon::create(2026, 5, 15, 14, 30, 45);
        $academy->created_at = $customTime;
        $academy->updated_at = $customTime->copy()->addMinutes(10);
        $academy->save();

        $fresh = $academy->fresh();
        $this->assertSame('2026-05-15 14:30:45', $fresh->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-15 14:40:45', $fresh->updated_at->format('Y-m-d H:i:s'));
    }

    #[DataProvider('engineProvider')]
    public function test_uniqueness_and_foreign_key_constraints_are_enforced(string $engine): void
    {
        $this->useEngine($engine);

        User::factory()->create(['email' => 'unique-student@example.com']);

        $duplicateEmailThrown = false;
        try {
            User::factory()->create(['email' => 'unique-student@example.com']);
        } catch (QueryException) {
            $duplicateEmailThrown = true;
        }
        $this->assertTrue($duplicateEmailThrown, "Expected QueryException for duplicate user email on {$engine}.");

        $academy = Academy::create([
            'name' => 'Unique Slug Academy',
            'slug' => 'unique-slug',
            'is_active' => true,
        ]);

        Course::create([
            'academy_id' => $academy->id,
            'title' => 'First Course',
            'slug' => 'unique-course-slug',
            'status' => 'Active',
        ]);

        $duplicateSlugThrown = false;
        try {
            Course::create([
                'academy_id' => $academy->id,
                'title' => 'Duplicate Slug Course',
                'slug' => 'unique-course-slug',
                'status' => 'Active',
            ]);
        } catch (QueryException) {
            $duplicateSlugThrown = true;
        }
        $this->assertTrue($duplicateSlugThrown, "Expected QueryException for duplicate course slug on {$engine}.");

        $invalidFkThrown = false;
        try {
            Course::create([
                'academy_id' => (string) Str::uuid(),
                'title' => 'Orphan Course',
                'slug' => 'orphan-course',
                'status' => 'Active',
            ]);
        } catch (QueryException) {
            $invalidFkThrown = true;
        }
        $this->assertTrue($invalidFkThrown, "Expected QueryException for invalid foreign key on {$engine}.");
    }

    #[DataProvider('engineProvider')]
    public function test_decimal_precision_is_preserved(string $engine): void
    {
        $this->useEngine($engine);

        $user = User::factory()->create();

        $progress = VideoProgress::create([
            'user_id' => $user->id,
            'lesson_key' => 'module-1-lesson-1',
            'position_seconds' => '123.45',
            'duration_seconds' => '987.65',
            'view_seconds' => '456.78',
            'completed' => false,
            'updated_at' => now(),
        ]);

        $freshProgress = VideoProgress::where('id', $progress->id)->firstOrFail();
        $this->assertSame('123.45', (string) $freshProgress->position_seconds);
        $this->assertSame('987.65', (string) $freshProgress->duration_seconds);

        $academy = Academy::create([
            'name' => 'Grading Academy',
            'slug' => 'grading-academy',
            'is_active' => true,
        ]);
        $course = Course::create([
            'academy_id' => $academy->id,
            'title' => 'SEO Foundations',
            'slug' => 'seo-foundations',
            'status' => 'Active',
        ]);
        $module = Module::create([
            'course_id' => $course->id,
            'title' => 'Module 1',
            'display_order' => 1,
            'status' => 'Active',
        ]);
        $assignment = Assignment::create([
            'module_id' => $module->id,
            'title' => 'Keyword Audit',
            'status' => 'Active',
        ]);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'grade' => '94.75',
            'status' => 'Graded',
        ]);

        $this->assertSame('94.75', (string) $submission->fresh()->grade);
    }

    #[DataProvider('engineProvider')]
    public function test_eloquent_relationships_work_identically(string $engine): void
    {
        $this->useEngine($engine);

        $academy = Academy::create([
            'name' => 'Relationship Academy',
            'slug' => 'relationship-academy',
            'is_active' => true,
        ]);

        $course = Course::create([
            'academy_id' => $academy->id,
            'title' => 'Technical SEO',
            'slug' => 'technical-seo',
            'status' => 'Active',
        ]);

        $module = Module::create([
            'course_id' => $course->id,
            'title' => 'Crawling & Indexing',
            'display_order' => 1,
            'status' => 'Active',
        ]);

        $lesson = Lesson::create([
            'module_id' => $module->id,
            'title' => 'Robots.txt Deep Dive',
            'display_order' => 1,
            'status' => 'Active',
        ]);

        $this->assertCount(1, $academy->courses);
        $this->assertSame($course->id, $academy->courses->first()->id);
        $this->assertCount(1, $course->modules);
        $this->assertSame($module->id, $course->modules->first()->id);
        $this->assertCount(1, $module->lessons);
        $this->assertSame($lesson->id, $module->lessons->first()->id);
        $this->assertSame($academy->id, $lesson->module->course->academy->id);

        $user = User::factory()->create();
        $profile = StudentProfile::create([
            'user_id' => $user->id,
            'academy_id' => $academy->id,
            'student_code' => 'STU-2026-001',
            'batch' => '2026-A',
            'level' => 'Beginner',
            'active' => true,
        ]);

        $enrollment = Enrollment::create([
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => 'Active',
        ]);

        $this->assertNotNull($user->studentProfile);
        $this->assertSame($profile->id, $user->studentProfile->id);
        $this->assertCount(1, $user->studentProfile->enrollments);
        $this->assertSame($enrollment->id, $user->studentProfile->enrollments->first()->id);
        $this->assertSame($course->id, $user->studentProfile->enrollments->first()->course->id);
    }
}
