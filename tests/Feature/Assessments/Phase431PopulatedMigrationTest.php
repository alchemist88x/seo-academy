<?php

namespace Tests\Feature\Assessments;

use App\Domain\Courses\Models\Module;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Phase431PopulatedMigrationTest extends TestCase
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
        config(['cache.default' => 'array', 'permission.cache.store' => 'array']);

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
    }

    #[DataProvider('engineProvider')]
    public function test_populated_database_migration_with_legacy_duplicate_submissions(string $engine): void
    {
        $this->useEngine($engine);

        // 1. Rollback or migrate only up to Phase 4.2.1 (prior to Phase 4.3 migrations)
        Artisan::call('migrate:fresh', ['--database' => $engine, '--force' => true]);

        // Roll back the Phase 4.3 migrations to reach legacy state
        Artisan::call('migrate:rollback', ['--database' => $engine, '--step' => 5, '--force' => true]);

        $this->seed(RoleSeeder::class);

        // Verify we are at the legacy schema state for assignments and submissions
        $this->assertFalse(Schema::hasColumn('assignment_submissions', 'version_number'));
        $this->assertFalse(Schema::hasColumn('assignment_submissions', 'enrollment_id'));

        // 2. Populate legacy database fixtures
        $academyId = (string) Str::uuid();
        DB::table('academies')->insert([
            'id' => $academyId,
            'name' => 'Legacy Upgrade Academy',
            'slug' => 'legacy-upgrade-'.Str::lower(Str::random(6)),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = (string) Str::uuid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'academy_id' => $academyId,
            'title' => 'Legacy SEO Mastery',
            'slug' => 'legacy-seo-mastery',
            'status' => 'Published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $moduleId = (string) Str::uuid();
        DB::table('modules')->insert([
            'id' => $moduleId,
            'academy_id' => $academyId,
            'course_id' => $courseId,
            'title' => 'Legacy Technical Module',
            'display_order' => 1,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create 2 legacy assignments (with module_id NOT NULL as in legacy schema)
        $assign1Id = (string) Str::uuid();
        DB::table('assignments')->insert([
            'id' => $assign1Id,
            'module_id' => $moduleId,
            'title' => 'Legacy Assignment 1',
            'description' => 'Legacy desc 1',
            'status' => 'Active',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $assign2Id = (string) Str::uuid();
        DB::table('assignments')->insert([
            'id' => $assign2Id,
            'module_id' => $moduleId,
            'title' => 'Legacy Assignment 2',
            'description' => 'Legacy desc 2',
            'status' => 'Active',
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);

        // Create 2 students with profiles and enrollments
        $studentAId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $studentAId,
            'name' => 'Student Alice',
            'email' => 'alice@legacy-test.com',
            'password' => 'secret',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $profileAId = (string) Str::uuid();
        DB::table('student_profiles')->insert([
            'id' => $profileAId,
            'academy_id' => $academyId,
            'user_id' => $studentAId,
            'student_code' => 'STU-LEG-A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentAId = (string) Str::uuid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentAId,
            'academy_id' => $academyId,
            'course_id' => $courseId,
            'student_profile_id' => $profileAId,
            'status' => 'Active',
            'enrolled_at' => now()->subDays(15),
            'created_at' => now()->subDays(15),
            'updated_at' => now()->subDays(15),
        ]);

        $studentBId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $studentBId,
            'name' => 'Student Bob',
            'email' => 'bob@legacy-test.com',
            'password' => 'secret',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $profileBId = (string) Str::uuid();
        DB::table('student_profiles')->insert([
            'id' => $profileBId,
            'academy_id' => $academyId,
            'user_id' => $studentBId,
            'student_code' => 'STU-LEG-B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentBId = (string) Str::uuid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentBId,
            'academy_id' => $academyId,
            'course_id' => $courseId,
            'student_profile_id' => $profileBId,
            'status' => 'Active',
            'enrolled_at' => now()->subDays(15),
            'created_at' => now()->subDays(15),
            'updated_at' => now()->subDays(15),
        ]);

        // Insert legacy duplicate submissions for Student A on Assignment 1 (3 duplicate submissions)
        $subA1_1 = (string) Str::uuid();
        DB::table('assignment_submissions')->insert([
            'id' => $subA1_1,
            'assignment_id' => $assign1Id,
            'user_id' => $studentAId,
            'file_path' => 'legacy/a1_first.pdf',
            'notes' => 'Alice attempt 1',
            'grade' => 60.00,
            'status' => 'Submitted',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $subA1_2 = (string) Str::uuid();
        DB::table('assignment_submissions')->insert([
            'id' => $subA1_2,
            'assignment_id' => $assign1Id,
            'user_id' => $studentAId,
            'file_path' => 'legacy/a1_second.pdf',
            'notes' => 'Alice attempt 2',
            'grade' => 75.00,
            'status' => 'Submitted',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $subA1_3 = (string) Str::uuid();
        DB::table('assignment_submissions')->insert([
            'id' => $subA1_3,
            'assignment_id' => $assign1Id,
            'user_id' => $studentAId,
            'file_path' => null, // Nullable file_path test
            'notes' => 'Alice attempt 3 text only',
            'grade' => 90.00,
            'status' => 'Submitted',
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        // Insert legacy submission for Student B on Assignment 1 (1 submission)
        $subB1 = (string) Str::uuid();
        DB::table('assignment_submissions')->insert([
            'id' => $subB1,
            'assignment_id' => $assign1Id,
            'user_id' => $studentBId,
            'file_path' => 'legacy/b1.pdf',
            'notes' => 'Bob attempt 1',
            'grade' => 80.00,
            'status' => 'Submitted',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        // Insert legacy submission for Student A on Assignment 2 (1 submission)
        $subA2 = (string) Str::uuid();
        DB::table('assignment_submissions')->insert([
            'id' => $subA2,
            'assignment_id' => $assign2Id,
            'user_id' => $studentAId,
            'file_path' => 'legacy/a2.pdf',
            'notes' => 'Alice assignment 2',
            'grade' => 88.00,
            'status' => 'Submitted',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        // 3. EXECUTE MIGRATION UPGRADE (Applies Phase 4.3 migrations on populated data)
        $exitCode = Artisan::call('migrate', ['--database' => $engine, '--force' => true]);
        $this->assertSame(0, $exitCode, 'Migration upgrade must exit with code 0 on populated database.');

        // 4. VERIFY SCHEMA AND DATA PRESERVATION
        $this->assertTrue(Schema::hasColumn('assignment_submissions', 'version_number'));
        $this->assertTrue(Schema::hasColumn('assignment_submissions', 'previous_submission_id'));
        $this->assertTrue(Schema::hasColumn('assignment_submissions', 'enrollment_id'));
        $this->assertTrue(Schema::hasColumn('assignments', 'course_id'));

        // All 5 original submissions must exist with their original UUIDs intact
        $this->assertSame(5, DB::table('assignment_submissions')->count());

        // Check Student A on Assignment 1 (was 3 legacy rows)
        $a1Subs = DB::table('assignment_submissions')
            ->where('assignment_id', $assign1Id)
            ->where('user_id', $studentAId)
            ->orderBy('version_number')
            ->get();

        $this->assertCount(3, $a1Subs);
        $this->assertSame(1, (int) $a1Subs[0]->version_number);
        $this->assertSame($subA1_1, $a1Subs[0]->id);
        $this->assertNull($a1Subs[0]->previous_submission_id);
        $this->assertSame($enrollmentAId, $a1Subs[0]->enrollment_id);

        $this->assertSame(2, (int) $a1Subs[1]->version_number);
        $this->assertSame($subA1_2, $a1Subs[1]->id);
        $this->assertSame($subA1_1, $a1Subs[1]->previous_submission_id);
        $this->assertSame($enrollmentAId, $a1Subs[1]->enrollment_id);

        $this->assertSame(3, (int) $a1Subs[2]->version_number);
        $this->assertSame($subA1_3, $a1Subs[2]->id);
        $this->assertSame($subA1_2, $a1Subs[2]->previous_submission_id);
        $this->assertSame($enrollmentAId, $a1Subs[2]->enrollment_id);

        // Check Student B on Assignment 1 (was 1 legacy row)
        $b1Sub = DB::table('assignment_submissions')
            ->where('assignment_id', $assign1Id)
            ->where('user_id', $studentBId)
            ->first();
        $this->assertNotNull($b1Sub);
        $this->assertSame(1, (int) $b1Sub->version_number);
        $this->assertSame($subB1, $b1Sub->id);
        $this->assertNull($b1Sub->previous_submission_id);
        $this->assertSame($enrollmentBId, $b1Sub->enrollment_id);

        // Check Student A on Assignment 2 (was 1 legacy row)
        $a2Sub = DB::table('assignment_submissions')
            ->where('assignment_id', $assign2Id)
            ->where('user_id', $studentAId)
            ->first();
        $this->assertNotNull($a2Sub);
        $this->assertSame(1, (int) $a2Sub->version_number);
        $this->assertSame($subA2, $a2Sub->id);
        $this->assertNull($a2Sub->previous_submission_id);
        $this->assertSame($enrollmentAId, $a2Sub->enrollment_id);

        // Verify assignments backfilled course_id and academy_id from module
        $freshAssign1 = DB::table('assignments')->where('id', $assign1Id)->first();
        $this->assertSame($courseId, $freshAssign1->course_id);
        $this->assertSame($academyId, $freshAssign1->academy_id);

        // Verify unique constraint enforcement: attempting to insert duplicate (assign1, studentA, v1) must fail
        $duplicateAttemptCaught = false;
        try {
            DB::table('assignment_submissions')->insert([
                'id' => (string) Str::uuid(),
                'assignment_id' => $assign1Id,
                'user_id' => $studentAId,
                'version_number' => 1, // Duplicate version 1
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $duplicateAttemptCaught = true;
        }
        $this->assertTrue($duplicateAttemptCaught, 'Unique constraint assign_sub_user_version_unique must block duplicate version numbers.');
    }
}
