<?php

namespace Tests\Feature\Grading;

use App\Domain\Grading\Services\DecimalPrecisionEngine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HistoricalGradingSchemeProtectionTest extends TestCase
{
    protected static bool $sqliteMigrated = false;

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
                $tables = [
                    'enrollment_current_grades',
                    'enrollment_final_grades',
                    'assessment_weights',
                    'grading_categories',
                    'course_grading_schemes',
                    'enrollments',
                    'student_profiles',
                    'courses',
                    'academies',
                    'users',
                ];
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
            'title' => 'Technical SEO Mastery',
            'slug' => 'technical-seo-'.Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollmentId = (string) Str::uuid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'academy_id' => $academyId,
            'student_profile_id' => $profileId,
            'course_id' => $courseId,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$userId, $courseId, $enrollmentId];
    }

    #[DataProvider('engineProvider')]
    public function test_published_grade_retains_scheme_version_and_snapshot_when_scheme_is_modified(string $engine): void
    {
        $this->useEngine($engine);
        [$userId, $courseId, $enrollmentId] = $this->createFixtures($engine);

        // 1. Create Scheme V1
        $schemeV1Id = (string) Str::uuid();
        DB::table('course_grading_schemes')->insert([
            'id' => $schemeV1Id,
            'course_id' => $courseId,
            'version' => 1,
            'name' => 'Grading Scheme 2026 V1',
            'status' => 'active',
            'passing_grade' => 70.00,
            'calculation_method' => 'weighted_categories',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('courses')->where('id', $courseId)->update(['active_grading_scheme_id' => $schemeV1Id]);

        // Publish Final Grade against Scheme V1
        $finalGradeId = (string) Str::uuid();
        $snapshot = [
            'scheme_name' => 'Grading Scheme 2026 V1',
            'version' => 1,
            'passing_grade' => 70.00,
            'components' => [
                ['category' => 'Assignments', 'weight' => 40.0, 'score' => 85.0],
                ['category' => 'Final Exam', 'weight' => 60.0, 'score' => 90.0],
            ],
            'calculated_score' => 88.00,
        ];

        DB::table('enrollment_final_grades')->insert([
            'id' => $finalGradeId,
            'enrollment_id' => $enrollmentId,
            'grading_scheme_id' => $schemeV1Id,
            'version' => 1,
            'final_score' => '88.00',
            'letter_grade' => 'B+',
            'is_passing' => 1,
            'academic_status' => 'passed',
            'calculation_snapshot' => json_encode($snapshot),
            'published_at' => now(),
            'published_by' => $userId,
            'created_at' => now(),
        ]);

        // 2. Scheme V1 is archived and Scheme V2 is introduced
        DB::table('course_grading_schemes')->where('id', $schemeV1Id)->update([
            'status' => 'archived',
        ]);

        $schemeV2Id = (string) Str::uuid();
        DB::table('course_grading_schemes')->insert([
            'id' => $schemeV2Id,
            'course_id' => $courseId,
            'version' => 2,
            'name' => 'Grading Scheme 2027 V2 (Strict)',
            'status' => 'active',
            'passing_grade' => 75.00,
            'calculation_method' => 'weighted_categories',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('courses')->where('id', $courseId)->update(['active_grading_scheme_id' => $schemeV2Id]);

        // 3. Verify that the published final grade remains completely untouched
        $grade = DB::table('enrollment_final_grades')->where('id', $finalGradeId)->first();
        $this->assertNotNull($grade);
        $this->assertSame($schemeV1Id, $grade->grading_scheme_id);
        $this->assertSame(1, (int) $grade->version);
        $this->assertSame(0, DecimalPrecisionEngine::compare((string) $grade->final_score, '88.00', 2));
        $this->assertSame('B+', $grade->letter_grade);
        $this->assertSame('passed', $grade->academic_status);

        $decodedSnapshot = json_decode($grade->calculation_snapshot, true);
        $this->assertSame('Grading Scheme 2026 V1', $decodedSnapshot['scheme_name']);
        $this->assertSame(70.0, (float) $decodedSnapshot['passing_grade']);
    }

    #[DataProvider('engineProvider')]
    public function test_relational_integrity_blocks_deletion_of_scheme_referenced_by_published_grade(string $engine): void
    {
        $this->useEngine($engine);
        [$userId, $courseId, $enrollmentId] = $this->createFixtures($engine);

        $schemeId = (string) Str::uuid();
        DB::table('course_grading_schemes')->insert([
            'id' => $schemeId,
            'course_id' => $courseId,
            'version' => 1,
            'name' => 'Base Scheme',
            'status' => 'active',
            'passing_grade' => 70.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $finalGradeId = (string) Str::uuid();
        DB::table('enrollment_final_grades')->insert([
            'id' => $finalGradeId,
            'enrollment_id' => $enrollmentId,
            'grading_scheme_id' => $schemeId,
            'version' => 1,
            'final_score' => '80.00',
            'letter_grade' => 'B',
            'is_passing' => 1,
            'academic_status' => 'passed',
            'calculation_snapshot' => json_encode(['score' => 80]),
            'published_at' => now(),
            'published_by' => $userId,
            'created_at' => now(),
        ]);

        // Attempting to DELETE the scheme referenced by published final grade MUST FAIL (restrictOnDelete)
        $deleteBlocked = false;
        try {
            DB::table('course_grading_schemes')->where('id', $schemeId)->delete();
        } catch (QueryException $e) {
            $deleteBlocked = true;
        }

        $this->assertTrue($deleteBlocked, "Deleting course_grading_schemes referenced by enrollment_final_grades must be blocked on [{$engine}].");
    }
}
