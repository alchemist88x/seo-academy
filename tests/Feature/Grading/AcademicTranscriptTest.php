<?php

namespace Tests\Feature\Grading;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseTrainer;
use App\Domain\Courses\Models\Module;
use App\Domain\Grading\Models\AssessmentWeight;
use App\Domain\Grading\Models\CourseGradingScheme;
use App\Domain\Grading\Models\GradingCategory;
use App\Domain\Grading\Services\GradebookCalculationService;
use App\Domain\Grading\Services\TranscriptService;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Livewire\Grading\GradebookMatrix;
use App\Livewire\Student\AcademicTranscript;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcademicTranscriptTest extends TestCase
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

            if (! static::$mysqlMigrated) {
                Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
                static::$mysqlMigrated = true;
            } else {
                $tables = DB::connection('mysql')->select('SHOW TABLES');
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 0;');
                foreach ($tables as $t) {
                    $vals = array_values((array) $t);
                    $tableName = $vals[0];
                    if ($tableName !== 'migrations') {
                        DB::connection('mysql')->table($tableName)->truncate();
                    }
                }
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 1;');
            }
        }

        $this->seed(RoleSeeder::class);
    }

    private function createFixtures(): array
    {
        $academy = Academy::create([
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::random(5),
            'is_active' => true,
        ]);
        $academyId = $academy->id;

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
        $admin->academies()->attach($academyId, ['id' => (string) Str::uuid(), 'role' => 'admin']);

        $assignedTrainer = User::create([
            'name' => 'Trainer Assigned',
            'email' => 'trainer-assigned@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $assignedTrainer->assignRole('trainer');
        $assignedTrainer->academies()->attach($academyId, ['id' => (string) Str::uuid(), 'role' => 'trainer']);

        $unassignedTrainer = User::create([
            'name' => 'Trainer Unassigned',
            'email' => 'trainer-unassigned@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $unassignedTrainer->assignRole('trainer');
        $unassignedTrainer->academies()->attach($academyId, ['id' => (string) Str::uuid(), 'role' => 'trainer']);

        $studentA = User::create([
            'name' => 'Student Alice',
            'email' => 'alice@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $studentA->assignRole('student');
        $studentA->academies()->attach($academyId, ['id' => (string) Str::uuid(), 'role' => 'student']);

        $studentB = User::create([
            'name' => 'Student Bob',
            'email' => 'bob@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $studentB->assignRole('student');
        $studentB->academies()->attach($academyId, ['id' => (string) Str::uuid(), 'role' => 'student']);

        $profileA = StudentProfile::create([
            'academy_id' => $academyId,
            'user_id' => $studentA->id,
            'student_code' => 'STU-'.Str::random(6),
        ]);

        $profileB = StudentProfile::create([
            'academy_id' => $academyId,
            'user_id' => $studentB->id,
            'student_code' => 'STU-'.Str::random(6),
        ]);

        $course = Course::create([
            'academy_id' => $academyId,
            'title' => 'Technical SEO Mastery',
            'slug' => 'tech-seo-'.Str::random(5),
            'status' => 'Published',
            'visibility' => 'public',
        ]);

        CourseTrainer::create([
            'academy_id' => $academyId,
            'course_id' => $course->id,
            'user_id' => $assignedTrainer->id,
            'is_primary' => true,
        ]);

        $scheme = CourseGradingScheme::create([
            'course_id' => $course->id,
            'name' => 'Standard Scheme',
            'version' => 1,
            'passing_grade' => '70.00',
            'calculation_method' => 'weighted_categories',
            'status' => 'active',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $course->update(['active_grading_scheme_id' => $scheme->id]);

        $cat = GradingCategory::create([
            'course_id' => $course->id,
            'grading_scheme_id' => $scheme->id,
            'name' => 'Quizzes',
            'weight' => '100.00',
            'is_mandatory' => false,
            'display_order' => 1,
        ]);

        $module = Module::create([
            'academy_id' => $academyId,
            'course_id' => $course->id,
            'title' => 'Core Module',
            'display_order' => 1,
        ]);

        $quiz = Quiz::create([
            'module_id' => $module->id,
            'course_id' => $course->id,
            'title' => 'Quiz',
            'passing_percentage' => 70,
        ]);
        AssessmentWeight::create([
            'grading_scheme_id' => $scheme->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'quiz',
            'assessment_id' => $quiz->id,
            'custom_weight' => '100.00',
            'is_mandatory' => false,
        ]);

        $enrollmentA = Enrollment::create([
            'academy_id' => $academyId,
            'student_profile_id' => $profileA->id,
            'course_id' => $course->id,
            'status' => 'completed',
            'completed_at' => now(),
            'enrolled_at' => now()->toDateString(),
        ]);

        $enrollmentB = Enrollment::create([
            'academy_id' => $academyId,
            'student_profile_id' => $profileB->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        DB::table('assessment_attempts')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $academyId,
            'assessment_id' => $quiz->id,
            'enrollment_id' => $enrollmentA->id,
            'user_id' => $studentA->id,
            'attempt_number' => 1,
            'status' => 'evaluated',
            'started_at' => now(),
            'total_points' => '100.00',
            'earned_points' => '89.50',
            'score_percentage' => '89.50',
            'passed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'admin' => $admin,
            'assignedTrainer' => $assignedTrainer,
            'unassignedTrainer' => $unassignedTrainer,
            'studentA' => $studentA,
            'studentB' => $studentB,
            'course' => $course,
            'enrollmentA' => $enrollmentA,
            'enrollmentB' => $enrollmentB,
        ];
    }

    #[DataProvider('engineProvider')]
    public function test_student_can_view_own_transcript(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures();
        $calcService = app(GradebookCalculationService::class);
        $calcService->publishEnrollmentGrade($fx['enrollmentA'], $fx['admin'], 'key_alice_transcript', 'Official grade');

        $transcriptService = app(TranscriptService::class);
        $transcript = $transcriptService->getStudentTranscript($fx['enrollmentA'], $fx['studentA']);

        $this->assertEquals($fx['enrollmentA']->id, $transcript['enrollment_id']);
        $this->assertEquals('Student Alice', $transcript['student']['name']);
        $this->assertTrue($transcript['grade']['is_published']);
        $this->assertEquals('89.50', $transcript['grade']['final_score']);
        $this->assertEquals('B', $transcript['grade']['letter_grade']);
        $this->assertTrue($transcript['grade']['is_passing']);
        $this->assertTrue($transcript['completion']['is_completed']);
        $this->assertTrue($transcript['is_certificate_eligible']);
    }

    #[DataProvider('engineProvider')]
    public function test_student_cannot_view_other_students_transcript_403(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures();

        $transcriptService = app(TranscriptService::class);

        $this->expectException(AuthorizationException::class);
        $transcriptService->getStudentTranscript($fx['enrollmentA'], $fx['studentB']);
    }

    #[DataProvider('engineProvider')]
    public function test_student_does_not_see_unpublished_draft_grades(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures();
        $calcService = app(GradebookCalculationService::class);
        // Calculate draft but do NOT publish
        $calcService->calculateEnrollmentGrade($fx['enrollmentA'], actor: $fx['admin']);

        $transcriptService = app(TranscriptService::class);
        $transcript = $transcriptService->getStudentTranscript($fx['enrollmentA'], $fx['studentA']);

        // Student transcript must protect unreleased grades
        $this->assertFalse($transcript['grade']['is_published']);
        $this->assertNull($transcript['grade']['final_score']);
        $this->assertNull($transcript['grade']['letter_grade']);
        $this->assertFalse($transcript['is_certificate_eligible']);
        // Draft details hidden from student
        $this->assertNull($transcript['draft']);
    }

    #[DataProvider('engineProvider')]
    public function test_assigned_trainer_can_view_transcripts_for_their_course(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures();
        $transcriptService = app(TranscriptService::class);
        $transcript = $transcriptService->getStudentTranscript($fx['enrollmentA'], $fx['assignedTrainer']);

        $this->assertEquals($fx['enrollmentA']->id, $transcript['enrollment_id']);
        // Trainer can also see lifecycle history and draft data
        $this->assertArrayHasKey('lifecycle_events', $transcript);
    }

    #[DataProvider('engineProvider')]
    public function test_unassigned_trainer_cannot_view_transcript_403(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures();
        $transcriptService = app(TranscriptService::class);

        $this->expectException(AuthorizationException::class);
        $transcriptService->getStudentTranscript($fx['enrollmentA'], $fx['unassignedTrainer']);
    }

    #[DataProvider('engineProvider')]
    public function test_admin_can_view_any_transcript(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures();
        $transcriptService = app(TranscriptService::class);
        $transcript = $transcriptService->getStudentTranscript($fx['enrollmentA'], $fx['admin']);

        $this->assertEquals($fx['enrollmentA']->id, $transcript['enrollment_id']);
    }

    #[DataProvider('engineProvider')]
    public function test_livewire_transcript_component_renders_for_student(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures();
        $calcService = app(GradebookCalculationService::class);
        $calcService->publishEnrollmentGrade($fx['enrollmentA'], $fx['admin'], 'key_livewire_test', 'Official release');

        $this->actingAs($fx['studentA']);

        Livewire::test(AcademicTranscript::class, ['course' => $fx['course']])
            ->assertStatus(200)
            ->assertSee('Official Academic Transcript')
            ->assertSee('Student Alice')
            ->assertSee('89.50%')
            ->assertSee('Eligible');
    }

    #[DataProvider('engineProvider')]
    public function test_livewire_gradebook_matrix_component_renders_for_staff(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures();

        // 1. Admin access
        $this->actingAs($fx['admin']);
        Livewire::test(GradebookMatrix::class, ['course' => $fx['course']])
            ->assertStatus(200)
            ->assertSee('Course Gradebook')
            ->assertSee('Student Alice');

        // 2. Assigned trainer access
        $this->actingAs($fx['assignedTrainer']);
        Livewire::test(GradebookMatrix::class, ['course' => $fx['course']])
            ->assertStatus(200);

        // 3. Unassigned trainer blocked with 403
        $this->actingAs($fx['unassignedTrainer']);
        Livewire::test(GradebookMatrix::class, ['course' => $fx['course']])
            ->assertStatus(403);
    }
}
