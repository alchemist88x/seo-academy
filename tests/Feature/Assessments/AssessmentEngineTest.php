<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Events\AssessmentAttemptStarted;
use App\Domain\Assessments\Events\AssessmentGraded;
use App\Domain\Assessments\Events\AssessmentSubmitted;
use App\Domain\Assessments\Models\Assessment;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Services\AssessmentService;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Learning\Services\LearningProgressService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Quizzes\Models\QuizQuestion;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Livewire\Student\AssessmentPlayer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssessmentEngineTest extends TestCase
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
            try {
                DB::connection('mysql')->getPdo();
            } catch (\Throwable $e) {
                $this->markTestSkipped('MySQL connection not available: '.$e->getMessage());
            }
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
            'title' => 'Core Module',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);
        $lesson = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'Core Lesson',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'is_mandatory' => true,
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

        return [$course, $module, $lesson, $studentUser, $profile, $enrollment];
    }

    #[DataProvider('engineProvider')]
    public function test_assessment_creation_and_question_types_configuration(string $engine): void
    {
        $this->useEngine($engine);
        $academy = Academy::factory()->create();
        [$course, $module] = $this->createHarness($academy);

        $assessment = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Comprehensive SEO Evaluation',
            'type' => 'exam',
            'is_required' => true,
            'attempt_limit' => 3,
            'time_limit_minutes' => 45,
            'passing_percentage' => 75,
            'pass_mark' => 75,
            'status' => 'Active',
            'published_at' => now(),
        ]);

        $this->assertDatabaseHas('quizzes', [
            'id' => $assessment->id,
            'course_id' => $course->id,
            'type' => 'exam',
            'is_required' => 1,
            'attempt_limit' => 3,
            'time_limit_minutes' => 45,
            'passing_percentage' => 75,
        ]);

        // Create questions for all 5 question types
        $q1 = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'prompt' => 'What does HTTP 301 indicate?',
            'options' => ['Temporary redirect', 'Permanent redirect', 'Not found', 'Server error'],
            'answer_key' => 'Permanent redirect',
            'points' => 2,
            'display_order' => 1,
        ]);

        $q2 = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_MULTIPLE_CHOICE,
            'prompt' => 'Which tags belong in HTML head? (Select all that apply)',
            'options' => ['meta description', 'canonical link', 'footer', 'title'],
            'answer_key' => ['meta description', 'canonical link', 'title'],
            'points' => 3,
            'display_order' => 2,
        ]);

        $q3 = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_TRUE_FALSE,
            'prompt' => 'Robots.txt prevents search engines from indexing a URL that has inbound links.',
            'options' => ['true', 'false'],
            'answer_key' => false,
            'points' => 1,
            'display_order' => 3,
        ]);

        $q4 = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_SHORT_ANSWER,
            'prompt' => 'What status code should a deleted page return if permanently removed?',
            'rubric' => 'Accept 404 or 410',
            'points' => 2,
            'display_order' => 4,
        ]);

        $q5 = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_LONG_ANSWER,
            'prompt' => 'Describe your strategy for migrating an e-commerce website to a new domain.',
            'rubric' => 'Must cover 301 mapping, search console property setup, and backlink outreach.',
            'points' => 10,
            'display_order' => 5,
        ]);

        $this->assertEquals(5, $assessment->questions()->count());
        $this->assertTrue($q1->isObjective());
        $this->assertTrue($q2->isObjective());
        $this->assertTrue($q3->isObjective());
        $this->assertFalse($q4->isObjective());
        $this->assertFalse($q5->isObjective());
        $this->assertTrue($q4->requiresManualGrading());
        $this->assertTrue($q5->requiresManualGrading());
    }

    #[DataProvider('engineProvider')]
    public function test_answer_key_protection_and_sanitization(string $engine): void
    {
        $this->useEngine($engine);
        $academy = Academy::factory()->create();
        [$course, $module, $lesson, $studentUser, $studentProfile, $enrollment] = $this->createHarness($academy);

        $assessment = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Security Audit Quiz',
            'status' => 'Active',
        ]);

        QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'prompt' => 'Secret prompt',
            'options' => ['Option A', 'Option B'],
            'answer_key' => 'Option A',
            'explanation' => 'Super secret explanation',
            'rubric' => 'Secret rubric',
            'points' => 5,
        ]);

        $service = app(AssessmentService::class);
        $studentData = $service->getStudentAssessmentData($assessment, $enrollment, $studentUser);

        $this->assertArrayNotHasKey('answer_key', $studentData['questions'][0]);
        $this->assertArrayNotHasKey('rubric', $studentData['questions'][0]);
        $this->assertArrayNotHasKey('explanation', $studentData['questions'][0]);
        $this->assertEquals('Secret prompt', $studentData['questions'][0]['question_text']);
        $this->assertEquals(['Option A', 'Option B'], $studentData['questions'][0]['options']);
    }

    #[DataProvider('engineProvider')]
    public function test_automatic_grading_and_evaluation_workflow(string $engine): void
    {
        $this->useEngine($engine);
        $academy = Academy::factory()->create();
        [$course, $module, $lesson, $studentUser, $studentProfile, $enrollment] = $this->createHarness($academy);

        Event::fake([AssessmentAttemptStarted::class, AssessmentSubmitted::class, AssessmentGraded::class]);

        $assessment = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Objective Only Quiz',
            'type' => 'quiz',
            'passing_percentage' => 70,
            'status' => 'Active',
        ]);

        $q1 = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'prompt' => 'Question 1',
            'options' => ['A', 'B', 'C'],
            'answer_key' => 'B',
            'points' => 5,
        ]);

        $q2 = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_TRUE_FALSE,
            'prompt' => 'Question 2',
            'options' => ['true', 'false'],
            'answer_key' => true,
            'points' => 5,
        ]);

        $service = app(AssessmentService::class);

        // Attempt 1: Start
        $attempt = $service->createAttempt($enrollment, $assessment, $studentUser);
        $this->assertNotNull($attempt);
        $this->assertEquals(1, $attempt->attempt_number);
        $this->assertEquals(AssessmentAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertEquals(10, $attempt->score_possible);
        Event::assertDispatched(AssessmentAttemptStarted::class);

        // Submit 1: Perfect score (10/10 = 100% -> Pass)
        $answers = [
            $q1->id => 'B',
            $q2->id => true,
        ];

        $submittedAttempt = $service->submitAttempt($attempt, $answers);
        $this->assertEquals(AssessmentAttempt::STATUS_EVALUATED, $submittedAttempt->status);
        $this->assertFalse($submittedAttempt->requires_manual_grading);
        $this->assertEquals(10, $submittedAttempt->score_awarded);
        $this->assertEquals(100, $submittedAttempt->score_percentage);
        $this->assertTrue($submittedAttempt->is_passed);
        Event::assertDispatched(AssessmentSubmitted::class);
        Event::assertDispatched(AssessmentGraded::class);

        // Check individual answers
        $this->assertDatabaseHas('assessment_attempt_answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $q1->id,
            'is_correct' => 1,
            'points_awarded' => 5,
        ]);
        $this->assertDatabaseHas('assessment_attempt_answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $q2->id,
            'is_correct' => 1,
            'points_awarded' => 5,
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_attempt_limit_enforcement(string $engine): void
    {
        $this->useEngine($engine);
        $academy = Academy::factory()->create();
        [$course, $module, $lesson, $studentUser, $studentProfile, $enrollment] = $this->createHarness($academy);

        $assessment = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Strict Limit Exam',
            'attempt_limit' => 2,
            'status' => 'Active',
        ]);

        $q = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'prompt' => 'Q',
            'options' => ['A', 'B'],
            'answer_key' => 'A',
            'points' => 1,
        ]);

        $service = app(AssessmentService::class);

        // Attempt 1
        $attempt1 = $service->createAttempt($enrollment, $assessment, $studentUser);
        $service->submitAttempt($attempt1, [$q->id => 'B']); // fail

        // Attempt 2
        $attempt2 = $service->createAttempt($enrollment, $assessment, $studentUser);
        $service->submitAttempt($attempt2, [$q->id => 'B']); // fail

        // Attempt 3: Should fail with exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attempt limit reached for this assessment.');
        $service->createAttempt($enrollment, $assessment, $studentUser);
    }

    #[DataProvider('engineProvider')]
    public function test_manual_grading_workflow_and_pending_evaluation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = Academy::factory()->create();
        [$course, $module, $lesson, $studentUser, $studentProfile, $enrollment] = $this->createHarness($academy);

        $trainer = User::factory()->create();
        $trainer->assignRole('trainer');
        $trainer->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $assessment = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Hybrid Practical Test',
            'passing_percentage' => 60,
            'status' => 'Active',
        ]);

        $q1 = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'prompt' => 'Multiple choice prompt',
            'options' => ['A', 'B'],
            'answer_key' => 'A',
            'points' => 4,
        ]);

        $q2 = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_LONG_ANSWER,
            'prompt' => 'Essay prompt',
            'rubric' => 'Grade based on depth',
            'points' => 6,
        ]);

        $service = app(AssessmentService::class);

        $attempt = $service->createAttempt($enrollment, $assessment, $studentUser);
        $submitted = $service->submitAttempt($attempt, [
            $q1->id => 'A',
            $q2->id => 'My comprehensive strategy for internal linking...',
        ]);

        // Because q2 requires manual grading, status should be 'submitted', requires_manual_grading = true, is_passed = false
        $this->assertEquals(AssessmentAttempt::STATUS_SUBMITTED, $submitted->status);
        $this->assertTrue($submitted->requires_manual_grading);
        $this->assertFalse($submitted->is_passed);
        $this->assertEquals(4, $submitted->score_awarded);

        // Retrieve answer record for q2
        $q2Answer = $submitted->answers()->where('question_id', $q2->id)->first();
        $this->assertNotNull($q2Answer);

        // Instructor grades q2 with 5 points out of 6
        $service->gradeAttempt(
            attempt: $submitted,
            manualScores: [
                $q2->id => [
                    'points' => 5,
                    'feedback' => 'Solid explanation.',
                ],
            ],
            evaluator: $trainer,
            notes: 'Well done overall.'
        );

        $submitted->refresh();
        $this->assertEquals(AssessmentAttempt::STATUS_EVALUATED, $submitted->status);
        $this->assertFalse($submitted->requires_manual_grading);
        $this->assertEquals(9, $submitted->score_awarded); // 4 + 5 = 9 / 10
        $this->assertEquals(90, $submitted->score_percentage);
        $this->assertTrue($submitted->is_passed); // 90% >= 60%
        $this->assertEquals($trainer->id, $submitted->evaluator_id);
        $this->assertEquals('Well done overall.', $submitted->feedback);
    }

    #[DataProvider('engineProvider')]
    public function test_submitted_attempt_immutability(string $engine): void
    {
        $this->useEngine($engine);
        $academy = Academy::factory()->create();
        [$course, $module, $lesson, $studentUser, $studentProfile, $enrollment] = $this->createHarness($academy);

        $assessment = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Immutable Quiz',
            'status' => 'Active',
        ]);

        $q = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'prompt' => 'Q',
            'options' => ['A', 'B'],
            'answer_key' => 'A',
            'points' => 1,
        ]);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($enrollment, $assessment, $studentUser);
        $service->submitAttempt($attempt, [$q->id => 'A']);

        // Attempting to re-submit must throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This attempt has already been submitted.');
        $service->submitAttempt($attempt, [$q->id => 'B']);
    }

    #[DataProvider('engineProvider')]
    public function test_cross_enrollment_isolation_and_unauthorized_access(string $engine): void
    {
        $this->useEngine($engine);
        $academy = Academy::factory()->create();
        [$course, $module, $lesson, $student1, $profile1, $enrollment1] = $this->createHarness($academy);

        $student2 = User::factory()->create(['is_active' => true]);
        $student2->assignRole('student');
        $student2->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $profile2 = StudentProfile::factory()->create([
            'academy_id' => $academy->id,
            'user_id' => $student2->id,
        ]);
        $enrollment2 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile2->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        $assessment = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Tenant Security Quiz',
            'status' => 'Active',
        ]);

        $service = app(AssessmentService::class);

        // Student 1 attempts to pass Student 2's enrollment
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You do not own this course enrollment.');
        $service->createAttempt($enrollment2, $assessment, $student1);
    }

    #[DataProvider('engineProvider')]
    public function test_course_completion_gated_by_required_assessments(string $engine): void
    {
        $this->useEngine($engine);
        $academy = Academy::factory()->create();
        [$course, $module, $lesson, $studentUser, $studentProfile, $enrollment] = $this->createHarness($academy);

        // Add a required assessment to the course
        $assessment = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Mandatory Final Exam',
            'is_required' => true,
            'passing_percentage' => 70,
            'status' => 'Active',
        ]);

        $q = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'prompt' => 'Question',
            'options' => ['Correct', 'Wrong'],
            'answer_key' => 'Correct',
            'points' => 10,
        ]);

        $progressService = app(LearningProgressService::class);
        $completionService = app(CourseCompletionService::class);
        $assessmentService = app(AssessmentService::class);

        // Step 1: Complete lesson
        $progressService->completeLesson($enrollment, $lesson, $studentUser);
        $enrollment->refresh();

        // Course should NOT complete yet because mandatory assessment is not passed
        $this->assertFalse($enrollment->isCompleted());
        $status = $completionService->getCompletionStatus($enrollment);
        $this->assertFalse($status['is_eligible']);
        $this->assertFalse($status['assessments'][0]['is_passed']);

        // Step 2: Fail the assessment (score 0%)
        $attempt1 = $assessmentService->createAttempt($enrollment, $assessment, $studentUser);
        $assessmentService->submitAttempt($attempt1, [$q->id => 'Wrong']);
        $enrollment->refresh();

        $this->assertFalse($enrollment->isCompleted());

        // Step 3: Pass the assessment (score 100%)
        $attempt2 = $assessmentService->createAttempt($enrollment, $assessment, $studentUser);
        $assessmentService->submitAttempt($attempt2, [$q->id => 'Correct']);
        $enrollment->refresh();

        // Now course completion must be granted automatically!
        $this->assertTrue($enrollment->isCompleted());
        $this->assertEquals(Enrollment::STATUS_COMPLETED, $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);

        // Certificate should also have been issued
        $cert = app(CertificateIssuanceService::class)->getCertificateForEnrollment($enrollment);
        $this->assertNotNull($cert);
        $this->assertNotEmpty($cert->certificate_number);
    }

    #[DataProvider('engineProvider')]
    public function test_optional_assessments_do_not_block_course_completion(string $engine): void
    {
        $this->useEngine($engine);
        $academy = Academy::factory()->create();
        [$course, $module, $lesson, $studentUser, $studentProfile, $enrollment] = $this->createHarness($academy);

        // Add an OPTIONAL assessment to the course
        Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Self-Practice Quiz (Optional)',
            'is_required' => false,
            'passing_percentage' => 70,
            'status' => 'Active',
        ]);

        $progressService = app(LearningProgressService::class);

        // Complete lesson
        $progressService->completeLesson($enrollment, $lesson, $studentUser);
        $enrollment->refresh();

        // Optional assessment should not block completion
        $this->assertTrue($enrollment->isCompleted());
        $this->assertEquals(Enrollment::STATUS_COMPLETED, $enrollment->status);
    }

    #[DataProvider('engineProvider')]
    public function test_assessment_player_livewire_component_flow(string $engine): void
    {
        $this->useEngine($engine);
        $academy = Academy::factory()->create();
        [$course, $module, $lesson, $studentUser, $studentProfile, $enrollment] = $this->createHarness($academy);

        $assessment = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Livewire Student Quiz',
            'status' => 'Active',
            'passing_percentage' => 50,
        ]);

        $q = QuizQuestion::create([
            'quiz_id' => $assessment->id,
            'type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'prompt' => 'Test Prompt?',
            'options' => ['Alpha', 'Beta'],
            'answer_key' => 'Alpha',
            'points' => 10,
        ]);

        $this->actingAs($studentUser);

        Livewire::test(AssessmentPlayer::class, [
            'course' => $course,
            'assessment' => $assessment,
        ])
            ->assertSee('Livewire Student Quiz')
            ->call('startAttempt')
            ->set('answers.'.$q->id, 'Alpha')
            ->call('submitAttempt')
            ->assertSee('Assessment Passed!');

        $this->assertDatabaseHas('assessment_attempts', [
            'assessment_id' => $assessment->id,
            'user_id' => $studentUser->id,
            'enrollment_id' => $enrollment->id,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'passed' => 1,
            'earned_points' => 10,
        ]);
    }
}
