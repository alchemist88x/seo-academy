<?php

namespace Tests\Feature\Grading;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentEvaluation;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Grading\Models\AssessmentWeight;
use App\Domain\Grading\Models\CourseGradingScheme;
use App\Domain\Grading\Models\GradingCategory;
use App\Domain\Grading\Services\GradebookCalculationService;
use App\Domain\Grading\Services\GradeOverrideService;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GradebookCalculationEngineTest extends TestCase
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
            Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--database' => 'sqlite', '--force' => true]);
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
                Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--database' => 'mysql', '--force' => true]);
                self::$mysqlMigrated = true;
            } else {
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
                $tables = [
                    'enrollment_grade_lifecycle_events',
                    'enrollment_current_grades',
                    'enrollment_final_grades',
                    'enrollment_grade_drafts',
                    'grade_override_events',
                    'assessment_weights',
                    'grading_categories',
                    'course_grading_schemes',
                    'assignment_evaluations',
                    'assignment_submissions',
                    'assignments',
                    'assessment_attempts',
                    'quizzes',
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

        $adminId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $adminId,
            'name' => 'Admin User',
            'email' => 'admin-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        User::find($adminId)->assignRole('admin');
        DB::table('academy_users')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $academyId,
            'user_id' => $adminId,
            'role' => 'admin',
            'created_at' => now(),
        ]);

        $studentUserId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $studentUserId,
            'name' => 'Student Alice',
            'email' => 'student-'.Str::random(5).'@seo-academy.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        User::find($studentUserId)->assignRole('student');
        DB::table('academy_users')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $academyId,
            'user_id' => $studentUserId,
            'role' => 'student',
            'created_at' => now(),
        ]);

        $profileId = (string) Str::uuid();
        DB::table('student_profiles')->insert([
            'id' => $profileId,
            'academy_id' => $academyId,
            'user_id' => $studentUserId,
            'student_code' => 'STU-'.Str::random(8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = (string) Str::uuid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'academy_id' => $academyId,
            'title' => 'Technical SEO Mastery',
            'slug' => 'tech-seo-'.Str::random(5),
            'status' => 'Published',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schemeId = (string) Str::uuid();
        DB::table('course_grading_schemes')->insert([
            'id' => $schemeId,
            'course_id' => $courseId,
            'name' => 'Standard Scheme',
            'version' => 1,
            'passing_grade' => '70.00',
            'calculation_method' => 'weighted_categories',
            'status' => 'active',
            'is_active' => true,
            'created_by' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Point course active_grading_scheme_id
        DB::table('courses')->where('id', $courseId)->update([
            'active_grading_scheme_id' => $schemeId,
        ]);

        $enrollmentId = (string) Str::uuid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'academy_id' => $academyId,
            'student_profile_id' => $profileId,
            'course_id' => $courseId,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $moduleId = (string) Str::uuid();
        DB::table('modules')->insert([
            'id' => $moduleId,
            'academy_id' => $academyId,
            'course_id' => $courseId,
            'title' => 'Core Module',
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'academy' => Academy::find($academyId),
            'admin' => User::find($adminId),
            'studentUser' => User::find($studentUserId),
            'studentProfile' => StudentProfile::find($profileId),
            'course' => Course::find($courseId),
            'scheme' => CourseGradingScheme::find($schemeId),
            'enrollment' => Enrollment::find($enrollmentId),
            'module' => Module::find($moduleId),
        ];
    }

    #[DataProvider('engineProvider')]
    public function test_multi_category_weighted_calculation_preserves_16_decimal_precision(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        // 1. Assignments Category: 40%
        $cat1 = GradingCategory::create([
            'course_id' => $fx['course']->id,
            'grading_scheme_id' => $fx['scheme']->id,
            'name' => 'Assignments',
            'weight' => '40.00',
            'is_mandatory' => true,
            'display_order' => 1,
        ]);

        // 2. Quizzes Category: 30%
        $cat2 = GradingCategory::create([
            'course_id' => $fx['course']->id,
            'grading_scheme_id' => $fx['scheme']->id,
            'name' => 'Quizzes',
            'weight' => '30.00',
            'is_mandatory' => false,
            'display_order' => 2,
        ]);

        // 3. Final Exam Category: 30%
        $cat3 = GradingCategory::create([
            'course_id' => $fx['course']->id,
            'grading_scheme_id' => $fx['scheme']->id,
            'name' => 'Final Exam',
            'weight' => '30.00',
            'is_mandatory' => true,
            'display_order' => 3,
        ]);

        // Create assignment and submission: 85%
        $assignment = Assignment::create([
            'academy_id' => $fx['academy']->id,
            'course_id' => $fx['course']->id,
            'title' => 'Audit Project',
            'assignment_type' => Assignment::TYPE_TEXT_ONLY,
            'max_marks' => 100,
            'status' => Assignment::STATUS_PUBLISHED,
        ]);
        AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat1->id,
            'assessment_type' => 'assignment',
            'assessment_id' => $assignment->id,
            'custom_weight' => '100.00',
            'is_mandatory' => true,
        ]);
        $sub = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $fx['enrollment']->id,
            'user_id' => $fx['studentUser']->id,
            'version_number' => 1,
            'status' => AssignmentSubmission::STATUS_GRADED,
            'grade' => '85.00',
        ]);
        AssignmentEvaluation::create([
            'submission_id' => $sub->id,
            'evaluator_id' => $fx['admin']->id,
            'raw_score' => '85.00',
            'points_awarded' => '85.00',
            'points_possible' => '100.00',
            'score_percentage' => '85.00',
            'is_passed' => true,
            'status' => AssignmentEvaluation::STATUS_GRADED,
            'graded_at' => now(),
        ]);

        // Create quiz and attempt: 92.50%
        $quiz = Quiz::create([
            'module_id' => $fx['module']->id,
            'course_id' => $fx['course']->id,
            'title' => 'Technical Quiz',
            'passing_percentage' => 70,
            'is_published' => true,
        ]);
        AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat2->id,
            'assessment_type' => 'quiz',
            'assessment_id' => $quiz->id,
            'custom_weight' => '100.00',
            'is_mandatory' => false,
        ]);
        AssessmentAttempt::create([
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $quiz->id,
            'enrollment_id' => $fx['enrollment']->id,
            'user_id' => $fx['studentUser']->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => now(),
            'total_points' => '100.00',
            'earned_points' => '92.50',
            'score_percentage' => '92.50',
            'passed' => true,
        ]);

        // Create exam and attempt: 78.00%
        $exam = Quiz::create([
            'module_id' => $fx['module']->id,
            'course_id' => $fx['course']->id,
            'title' => 'Summative Final Exam',
            'passing_percentage' => 70,
            'is_published' => true,
        ]);
        AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat3->id,
            'assessment_type' => 'exam',
            'assessment_id' => $exam->id,
            'custom_weight' => '100.00',
            'is_mandatory' => true,
        ]);
        AssessmentAttempt::create([
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $exam->id,
            'enrollment_id' => $fx['enrollment']->id,
            'user_id' => $fx['studentUser']->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => now(),
            'total_points' => '100.00',
            'earned_points' => '78.00',
            'score_percentage' => '78.00',
            'passed' => true,
        ]);

        // Expected composite: (85 * 0.40) + (92.50 * 0.30) + (78 * 0.30)
        // = 34.00 + 27.75 + 23.40 = 85.15%
        $service = app(GradebookCalculationService::class);
        $draft = $service->calculateEnrollmentGrade($fx['enrollment'], actor: $fx['admin']);

        $this->assertEquals('85.15', (string) $draft->current_score);
        $this->assertEquals('B', $draft->current_letter_grade);
        $this->assertTrue((bool) $draft->is_passing);
        $this->assertEquals('passed', $draft->academic_status);
        $this->assertFalse((bool) $draft->missing_mandatory_assessments);

        // Verify unrounded 16-decimal score in calculation breakdown
        $breakdown = $draft->calculation_breakdown;
        $this->assertArrayHasKey('unrounded_score', $breakdown);
        $this->assertEquals('85.1500000000000000', $breakdown['unrounded_score']);
    }

    #[DataProvider('engineProvider')]
    public function test_exempt_assessment_weights_redistributed_proportionally(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $cat = GradingCategory::create([
            'course_id' => $fx['course']->id,
            'grading_scheme_id' => $fx['scheme']->id,
            'name' => 'Assessments',
            'weight' => '100.00',
            'is_mandatory' => false,
            'display_order' => 1,
        ]);

        $quiz1 = Quiz::create(['module_id' => $fx['module']->id, 'course_id' => $fx['course']->id, 'title' => 'Quiz 1', 'passing_percentage' => 70]);
        $quiz2 = Quiz::create(['module_id' => $fx['module']->id, 'course_id' => $fx['course']->id, 'title' => 'Quiz 2', 'passing_percentage' => 70]);
        $quiz3 = Quiz::create(['module_id' => $fx['module']->id, 'course_id' => $fx['course']->id, 'title' => 'Quiz 3', 'passing_percentage' => 70]);

        $w1 = AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'quiz',
            'assessment_id' => $quiz1->id,
            'custom_weight' => '50.00',
        ]);
        $w2 = AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'quiz',
            'assessment_id' => $quiz2->id,
            'custom_weight' => '25.00',
        ]);
        $w3 = AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'quiz',
            'assessment_id' => $quiz3->id,
            'custom_weight' => '25.00',
        ]);

        // Student scores 90% on Quiz 1, 80% on Quiz 2
        AssessmentAttempt::create([
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $quiz1->id,
            'enrollment_id' => $fx['enrollment']->id,
            'user_id' => $fx['studentUser']->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => now(),
            'score_percentage' => '90.00',
        ]);
        AssessmentAttempt::create([
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $quiz2->id,
            'enrollment_id' => $fx['enrollment']->id,
            'user_id' => $fx['studentUser']->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => now(),
            'score_percentage' => '80.00',
        ]);

        // Quiz 3 is exempted via GradeOverrideService with reason 'Exempted due to illness'
        $overrideService = app(GradeOverrideService::class);
        $overrideService->recordOverride(
            enrollmentId: $fx['enrollment']->id,
            assessmentType: 'quiz',
            assessmentId: $quiz3->id,
            expectedPreviousScore: null,
            newScore: '0.00',
            reason: 'Student exempted from Quiz 3',
            overriddenBy: $fx['admin']->id
        );

        $service = app(GradebookCalculationService::class);
        $draft = $service->calculateEnrollmentGrade($fx['enrollment'], actor: $fx['admin']);

        // Remaining active weights: 50% and 25% (total 75%).
        // Normalized weights: 50/75 = 66.66666666666667%, 25/75 = 33.33333333333333%.
        // Score: (90 * 2/3) + (80 * 1/3) = 60 + 26.6666... = 86.67%
        $this->assertEquals('86.67', (string) $draft->current_score);
        $this->assertTrue((bool) $draft->is_passing);
    }

    #[DataProvider('engineProvider')]
    public function test_missing_mandatory_assessment_produces_incomplete_status_and_fails_passing(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $cat = GradingCategory::create([
            'course_id' => $fx['course']->id,
            'grading_scheme_id' => $fx['scheme']->id,
            'name' => 'Assessments',
            'weight' => '100.00',
            'is_mandatory' => false,
            'display_order' => 1,
        ]);

        $quiz = Quiz::create(['module_id' => $fx['module']->id, 'course_id' => $fx['course']->id, 'title' => 'Regular Quiz', 'passing_percentage' => 70]);
        $mandatoryExam = Quiz::create(['module_id' => $fx['module']->id, 'course_id' => $fx['course']->id, 'title' => 'Mandatory Exit Exam', 'passing_percentage' => 70]);

        AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'quiz',
            'assessment_id' => $quiz->id,
            'custom_weight' => '50.00',
            'is_mandatory' => false,
        ]);
        AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'exam',
            'assessment_id' => $mandatoryExam->id,
            'custom_weight' => '50.00',
            'is_mandatory' => true, // MANDATORY!
        ]);

        // Student scores 100% on the regular quiz, but does not take the mandatory exam
        AssessmentAttempt::create([
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $quiz->id,
            'enrollment_id' => $fx['enrollment']->id,
            'user_id' => $fx['studentUser']->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => now(),
            'score_percentage' => '100.00',
        ]);

        $service = app(GradebookCalculationService::class);
        $draft = $service->calculateEnrollmentGrade($fx['enrollment'], actor: $fx['admin']);

        // Even though score is 50.00%, mandatory exam is missing!
        $this->assertTrue((bool) $draft->missing_mandatory_assessments);
        $this->assertEquals('incomplete', $draft->academic_status);
        $this->assertFalse((bool) $draft->is_passing);
    }

    #[DataProvider('engineProvider')]
    public function test_rounding_boundary_determines_passing_at_two_decimals(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $cat = GradingCategory::create([
            'course_id' => $fx['course']->id,
            'grading_scheme_id' => $fx['scheme']->id,
            'name' => 'Exams',
            'weight' => '100.00',
            'is_mandatory' => false,
            'display_order' => 1,
        ]);

        $exam = Quiz::create(['module_id' => $fx['module']->id, 'course_id' => $fx['course']->id, 'title' => 'Exam', 'passing_percentage' => 70]);
        AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'exam',
            'assessment_id' => $exam->id,
            'custom_weight' => '100.00',
            'is_mandatory' => false,
        ]);

        // Case A: 69.9950000000000000% rounds half-up to 70.00% -> passing = true
        AssessmentAttempt::create([
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $exam->id,
            'enrollment_id' => $fx['enrollment']->id,
            'user_id' => $fx['studentUser']->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => now(),
            'score_percentage' => '69.995',
        ]);

        $service = app(GradebookCalculationService::class);
        $draftA = $service->calculateEnrollmentGrade($fx['enrollment'], actor: $fx['admin']);

        $this->assertEquals('70.00', (string) $draftA->current_score);
        $this->assertTrue((bool) $draftA->is_passing);
        $this->assertEquals('passed', $draftA->academic_status);

        // Case B: 69.9949999999999999% rounds half-up to 69.99% -> passing = false
        AssessmentAttempt::where('enrollment_id', $fx['enrollment']->id)->delete();
        AssessmentAttempt::create([
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $exam->id,
            'enrollment_id' => $fx['enrollment']->id,
            'user_id' => $fx['studentUser']->id,
            'attempt_number' => 2,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => now(),
            'score_percentage' => '69.994',
        ]);

        $draftB = $service->calculateEnrollmentGrade($fx['enrollment'], actor: $fx['admin']);

        $this->assertEquals('69.99', (string) $draftB->current_score);
        $this->assertFalse((bool) $draftB->is_passing);
        $this->assertEquals('failed', $draftB->academic_status);
    }

    #[DataProvider('engineProvider')]
    public function test_grade_override_replaces_original_score_in_calculation(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $cat = GradingCategory::create([
            'course_id' => $fx['course']->id,
            'grading_scheme_id' => $fx['scheme']->id,
            'name' => 'Exams',
            'weight' => '100.00',
            'is_mandatory' => false,
            'display_order' => 1,
        ]);

        $exam = Quiz::create(['module_id' => $fx['module']->id, 'course_id' => $fx['course']->id, 'title' => 'Exam', 'passing_percentage' => 70]);
        AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'exam',
            'assessment_id' => $exam->id,
            'custom_weight' => '100.00',
            'is_mandatory' => false,
        ]);

        // Original score: 50.00% (failing)
        AssessmentAttempt::create([
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $exam->id,
            'enrollment_id' => $fx['enrollment']->id,
            'user_id' => $fx['studentUser']->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => now(),
            'score_percentage' => '50.00',
        ]);

        // Override to 85.00%
        $overrideService = app(GradeOverrideService::class);
        $overrideService->recordOverride(
            enrollmentId: $fx['enrollment']->id,
            assessmentType: 'exam',
            assessmentId: $exam->id,
            expectedPreviousScore: null,
            newScore: '85.00',
            reason: 'Correction of question 5 grading error',
            overriddenBy: $fx['admin']->id
        );

        $service = app(GradebookCalculationService::class);
        $draft = $service->calculateEnrollmentGrade($fx['enrollment'], actor: $fx['admin']);

        $this->assertEquals('85.00', (string) $draft->current_score);
        $this->assertEquals('B', $draft->current_letter_grade);
        $this->assertTrue((bool) $draft->is_passing);
        $this->assertEquals('passed', $draft->academic_status);
    }

    #[DataProvider('engineProvider')]
    public function test_course_completion_is_independent_of_academic_passing(string $engine): void
    {
        $this->useEngine($engine);
        $fx = $this->createFixtures($engine);

        $cat = GradingCategory::create([
            'course_id' => $fx['course']->id,
            'grading_scheme_id' => $fx['scheme']->id,
            'name' => 'Assessments',
            'weight' => '100.00',
            'is_mandatory' => false,
            'display_order' => 1,
        ]);
        $quiz = Quiz::create(['module_id' => $fx['module']->id, 'course_id' => $fx['course']->id, 'title' => 'Quiz', 'passing_percentage' => 70]);
        AssessmentWeight::create([
            'grading_scheme_id' => $fx['scheme']->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'quiz',
            'assessment_id' => $quiz->id,
            'custom_weight' => '100.00',
            'is_mandatory' => false,
        ]);

        $service = app(GradebookCalculationService::class);

        // Case 1: Course completed, but academic grade failing (45%)
        $fx['enrollment']->update([
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        AssessmentAttempt::create([
            'academy_id' => $fx['academy']->id,
            'assessment_id' => $quiz->id,
            'enrollment_id' => $fx['enrollment']->id,
            'user_id' => $fx['studentUser']->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => now(),
            'score_percentage' => '45.00',
        ]);

        $service->calculateEnrollmentGrade($fx['enrollment'], actor: $fx['admin']);
        $service->publishEnrollmentGrade($fx['enrollment'], $fx['admin'], 'idemp_key_1', 'Publishing failing grade');

        $this->assertEquals(Enrollment::STATUS_COMPLETED, $fx['enrollment']->fresh()->status);
        $this->assertFalse((bool) $fx['enrollment']->currentGrade->publishedGrade->is_passing);
        $this->assertFalse($service->isEligibleForCertificate($fx['enrollment']->fresh()));

        // Case 2: Grade passing (95%), but course incomplete (active)
        $fx['enrollment']->update([
            'status' => Enrollment::STATUS_ACTIVE,
            'completed_at' => null,
        ]);
        AssessmentAttempt::where('enrollment_id', $fx['enrollment']->id)->update(['score_percentage' => '95.00']);
        $service->calculateEnrollmentGrade($fx['enrollment'], actor: $fx['admin']);
        $service->publishEnrollmentGrade($fx['enrollment'], $fx['admin'], 'idemp_key_2', 'Publishing passing grade');

        $enrollment = $fx['enrollment']->fresh(['currentGrade.publishedGrade']);
        $this->assertEquals(Enrollment::STATUS_ACTIVE, $enrollment->status);
        $this->assertTrue((bool) $enrollment->currentGrade->publishedGrade->is_passing);
        $this->assertFalse($service->isEligibleForCertificate($enrollment));

        // Case 3: Both course completed AND published passing grade
        $enrollment->update([
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        $this->assertTrue($service->isEligibleForCertificate($enrollment->fresh(['currentGrade.publishedGrade'])));
    }
}
