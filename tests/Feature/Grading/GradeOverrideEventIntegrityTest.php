<?php

namespace Tests\Feature\Grading;

use App\Domain\Grading\Exceptions\GradeOverrideStaleException;
use App\Domain\Grading\Services\GradeOverrideService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GradeOverrideEventIntegrityTest extends TestCase
{
    protected static bool $mysqlMigrated = false;

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
            Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
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

            if (! self::$mysqlMigrated) {
                Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
                self::$mysqlMigrated = true;
            } else {
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
                $tables = ['grade_override_events', 'enrollments', 'student_profiles', 'courses', 'academies', 'users'];
                foreach ($tables as $t) {
                    if (Schema::connection('mysql')->hasTable($t)) {
                        DB::connection('mysql')->statement("TRUNCATE TABLE `{$t}`");
                    }
                }
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    private function createFixtures(string $engine): array
    {
        $academyId = (string) Str::uuid();
        DB::table('academies')->insert([
            'id' => $academyId,
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::random(5),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Instructor User',
            'email' => 'instructor-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profileId = (string) Str::uuid();
        DB::table('student_profiles')->insert([
            'id' => $profileId,
            'user_id' => $userId,
            'academy_id' => $academyId,
            'student_code' => 'STU-'.Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = (string) Str::uuid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'academy_id' => $academyId,
            'title' => 'Course 101',
            'slug' => 'course-101-'.Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollmentId = (string) Str::uuid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'academy_id' => $academyId,
            'student_profile_id' => $profileId,
            'course_id' => $courseId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$userId, $enrollmentId];
    }

    #[DataProvider('engineProvider')]
    public function test_monotonic_sequence_and_previous_event_chaining(string $engine): void
    {
        $this->useEngine($engine);
        [$userId, $enrollmentId] = $this->createFixtures($engine);

        $service = new GradeOverrideService;
        $assessmentId = (string) Str::uuid();

        // 1. First override: initial sequence = 1, previous_event_id = null
        $event1 = $service->recordOverride(
            $enrollmentId,
            'assignment',
            $assessmentId,
            '70.00',
            '85.00',
            'Recalibrated rubric for criteria 3',
            $userId
        );

        $this->assertSame(1, $event1->sequence_number);
        $this->assertNull($event1->previous_event_id);
        $this->assertSame('70.00', (string) $event1->previous_score);
        $this->assertSame('85.00', (string) $event1->new_score);
        $this->assertSame('85.00', (string) $event1->effective_score);

        // 2. Second override: sequence = 2, previous_event_id = event1->id
        $event2 = $service->recordOverride(
            $enrollmentId,
            'assignment',
            $assessmentId,
            '85.00',
            '92.00',
            'Late submission penalty waived due to verified medical notice',
            $userId
        );

        $this->assertSame(2, $event2->sequence_number);
        $this->assertSame($event1->id, $event2->previous_event_id);
        $this->assertSame('85.00', (string) $event2->previous_score);
        $this->assertSame('92.00', (string) $event2->new_score);
        $this->assertSame('92.00', (string) $event2->effective_score);
    }

    #[DataProvider('engineProvider')]
    public function test_cas_validation_rejects_stale_overrides(string $engine): void
    {
        $this->useEngine($engine);
        [$userId, $enrollmentId] = $this->createFixtures($engine);

        $service = new GradeOverrideService;
        $assessmentId = (string) Str::uuid();

        // Initial override: 70 -> 80
        $service->recordOverride(
            $enrollmentId,
            'quiz',
            $assessmentId,
            '70.00',
            '80.00',
            'Question 4 ambiguity fix',
            $userId
        );

        // Attempt second override with stale expectation (passing 70 instead of 80) -> MUST THROW
        $this->expectException(GradeOverrideStaleException::class);
        $service->recordOverride(
            $enrollmentId,
            'quiz',
            $assessmentId,
            '70.00', // Stale! Current score is 80.00
            '90.00',
            'Concurrent instructor edit',
            $userId
        );
    }

    #[DataProvider('engineProvider')]
    public function test_explicit_reversal_references_original_event_and_restores_score(string $engine): void
    {
        $this->useEngine($engine);
        [$userId, $enrollmentId] = $this->createFixtures($engine);

        $service = new GradeOverrideService;
        $assessmentId = (string) Str::uuid();

        // Event 1: Original score 65.00 overridden to 85.00
        $event1 = $service->recordOverride(
            $enrollmentId,
            'assignment',
            $assessmentId,
            '65.00',
            '85.00',
            'Granted appeal by department chair',
            $userId
        );

        // Reverse Event 1
        $reversalEvent = $service->reverseOverride(
            $event1,
            'Reversed: appeal documentation invalidated after audit',
            $userId
        );

        $this->assertSame(2, $reversalEvent->sequence_number);
        $this->assertSame($event1->id, $reversalEvent->previous_event_id);
        $this->assertSame($event1->id, $reversalEvent->reversal_of_event_id);
        $this->assertSame('85.00', (string) $reversalEvent->previous_score);
        $this->assertSame('65.00', (string) $reversalEvent->new_score);
        $this->assertSame('65.00', (string) $reversalEvent->effective_score);

        // Double reversal attempt must be rejected
        $this->expectException(LogicException::class);
        $service->reverseOverride($event1, 'Attempt double reversal', $userId);
    }
}
