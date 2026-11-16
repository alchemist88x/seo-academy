<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Events\AssessmentGraded;
use App\Domain\Assessments\Events\AssessmentSubmitted;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Services\AssessmentFinalizationService;
use App\Domain\Assessments\Services\AssessmentService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseTrainer;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Quizzes\Models\QuizQuestion;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class Phase422ExaminationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected Module $module;

    protected Lesson $lesson;

    protected User $student;

    protected StudentProfile $studentProfile;

    protected Enrollment $enrollment;

    protected Quiz $quiz;

    protected QuizQuestion $q1;

    protected QuizQuestion $q2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::create([
            'id' => (string) Str::uuid(),
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);

        $this->course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'status' => 'Published',
        ]);

        $this->module = Module::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'title' => 'Core SEO Module',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);

        $this->lesson = Lesson::create([
            'academy_id' => $this->academy->id,
            'module_id' => $this->module->id,
            'title' => 'Core SEO Lesson',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'is_mandatory' => true,
        ]);

        $this->student = User::factory()->create(['is_active' => true]);
        $this->student->assignRole('student');
        $this->student->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->studentProfile = StudentProfile::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->student->id,
            'student_code' => 'STU-P422-'.Str::upper(Str::random(6)),
        ]);

        $this->enrollment = Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $this->studentProfile->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->quiz = Quiz::create([
            'course_id' => $this->course->id,
            'module_id' => $this->module->id,
            'title' => 'Technical SEO Examination',
            'status' => 'active',
            'published_at' => now()->subDay(),
            'is_required' => true,
            'passing_percentage' => 70,
            'attempt_limit' => 3,
            'time_limit_minutes' => 60,
            'grace_period_seconds' => 90,
            'is_scheduled_exam' => false,
        ]);

        $this->q1 = QuizQuestion::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'What is canonical URL?',
            'question_type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'options' => [
                ['key' => 'a', 'text' => 'Preferred master URL'],
                ['key' => 'b', 'text' => 'Deprecated duplicate URL'],
            ],
            'correct_answer' => 'a',
            'points' => 5,
            'display_order' => 1,
        ]);

        $this->q2 = QuizQuestion::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Explain crawl budget optimization techniques.',
            'question_type' => QuizQuestion::TYPE_LONG_ANSWER,
            'options' => null,
            'correct_answer' => null,
            'points' => 10,
            'display_order' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_scheduled_exam_eligibility_enforcement(): void
    {
        $now = Carbon::parse('2026-11-20 10:00:00');
        Carbon::setTestNow($now);

        $this->quiz->update([
            'is_scheduled_exam' => true,
            'exam_start_at' => Carbon::parse('2026-11-20 12:00:00'),
            'late_entry_deadline_at' => Carbon::parse('2026-11-20 12:30:00'),
            'exam_end_at' => Carbon::parse('2026-11-20 14:00:00'),
        ]);

        $service = app(AssessmentService::class);

        // 1. Attempting to start before exam_start_at must fail
        try {
            $service->createAttempt($this->enrollment, $this->quiz, $this->student);
            $this->fail('Expected DomainException for exam not started yet.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('not started yet', $e->getMessage());
        }

        // 2. Attempting to start after late_entry_deadline_at must fail
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:35:00'));
        try {
            $service->createAttempt($this->enrollment, $this->quiz, $this->student);
            $this->fail('Expected DomainException for late entry deadline.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Late entry deadline has passed', $e->getMessage());
        }

        // 3. Attempting to start after exam_end_at must fail
        Carbon::setTestNow(Carbon::parse('2026-11-20 14:05:00'));
        try {
            $service->createAttempt($this->enrollment, $this->quiz, $this->student);
            $this->fail('Expected DomainException for exam ended.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already ended', $e->getMessage());
        }

        // 4. Starting within valid window succeeds and freezes config
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:15:00'));
        $attempt = $service->createAttempt($this->enrollment, $this->quiz, $this->student);
        $this->assertInstanceOf(AssessmentAttempt::class, $attempt);
        $this->assertEquals(AssessmentAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertNotNull($attempt->config_snapshot);
        $this->assertTrue($attempt->config_snapshot['is_scheduled_exam']);
        $this->assertEquals(90, $attempt->config_snapshot['grace_period_seconds']);
    }

    public function test_server_authoritative_expiry_and_grace_period_calculation(): void
    {
        $now = Carbon::parse('2026-11-20 12:00:00');
        Carbon::setTestNow($now);

        // Time limit is 60 minutes, exam end at 12:45:00 -> nominal expiry must be capped at 12:45:00
        $this->quiz->update([
            'is_scheduled_exam' => true,
            'exam_start_at' => Carbon::parse('2026-11-20 12:00:00'),
            'exam_end_at' => Carbon::parse('2026-11-20 12:45:00'),
            'time_limit_minutes' => 60,
            'grace_period_seconds' => 120,
        ]);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($this->enrollment, $this->quiz, $this->student);

        // Nominally 60 minutes from 12:00 would be 13:00, but exam_end_at is 12:45
        $this->assertEquals(Carbon::parse('2026-11-20 12:45:00')->toIso8601String(), $attempt->expires_at->toIso8601String());
        // finalize_after = 12:45 + 120 seconds = 12:47:00
        $this->assertEquals(Carbon::parse('2026-11-20 12:47:00')->toIso8601String(), $attempt->finalize_after->toIso8601String());
    }

    public function test_atomic_answer_persistence_and_autosave(): void
    {
        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($this->enrollment, $this->quiz, $this->student);
        $q1Id = (string) $this->q1->id;

        // 1. First save revision 1
        $key1 = (string) Str::uuid();
        $ans1 = $service->saveAttemptAnswer(
            attempt: $attempt,
            questionId: $q1Id,
            studentAnswer: 'a',
            revision: 1,
            idempotencyKey: $key1,
            user: $this->student
        );

        $this->assertEquals(1, $ans1->revision);
        $this->assertEquals('a', $ans1->student_answer);
        $this->assertEquals($key1, $ans1->last_idempotency_key);
        $this->assertNull($ans1->is_correct); // Never auto-graded during in-progress autosave

        // 2. Idempotent replay with same revision and same idempotency key
        $replay = $service->saveAttemptAnswer(
            attempt: $attempt,
            questionId: $q1Id,
            studentAnswer: 'a',
            revision: 1,
            idempotencyKey: $key1,
            user: $this->student
        );
        $this->assertEquals($ans1->id, $replay->id);

        // 3. Reject stale revision (< current revision)
        try {
            $service->saveAttemptAnswer(
                attempt: $attempt,
                questionId: $q1Id,
                studentAnswer: 'b',
                revision: 0,
                idempotencyKey: (string) Str::uuid(),
                user: $this->student
            );
            $this->fail('Expected DomainException for stale revision.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Stale answer revision', $e->getMessage());
        }

        // 4. Reject same revision with different idempotency key
        try {
            $service->saveAttemptAnswer(
                attempt: $attempt,
                questionId: $q1Id,
                studentAnswer: 'b',
                revision: 1,
                idempotencyKey: (string) Str::uuid(),
                user: $this->student
            );
            $this->fail('Expected DomainException for conflicting revision.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Conflicting revision', $e->getMessage());
        }

        // 5. Newer revision (revision 2) succeeds
        $key2 = (string) Str::uuid();
        $ans2 = $service->saveAttemptAnswer(
            attempt: $attempt,
            questionId: $q1Id,
            studentAnswer: 'b',
            revision: 2,
            idempotencyKey: $key2,
            user: $this->student
        );
        $this->assertEquals(2, $ans2->revision);
        $this->assertEquals('b', $ans2->student_answer);
        $this->assertEquals($key2, $ans2->last_idempotency_key);

        // 6. Non-existent question ID rejected
        try {
            $service->saveAttemptAnswer(
                attempt: $attempt,
                questionId: (string) Str::uuid(),
                studentAnswer: 'foo',
                revision: 1,
                idempotencyKey: (string) Str::uuid(),
                user: $this->student
            );
            $this->fail('Expected InvalidArgumentException for question not belonging to attempt.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('does not belong to this attempt', $e->getMessage());
        }

        // 7. Another student cannot save answer
        $otherStudent = User::factory()->create(['is_active' => true]);
        $otherStudent->assignRole('student');
        try {
            $service->saveAttemptAnswer(
                attempt: $attempt,
                questionId: $q1Id,
                studentAnswer: 'b',
                revision: 3,
                idempotencyKey: (string) Str::uuid(),
                user: $otherStudent
            );
            $this->fail('Expected AuthorizationException for student not owning attempt.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('authorization to modify', $e->getMessage());
        }
    }

    public function test_recovery_after_interruption(): void
    {
        $now = Carbon::parse('2026-11-20 12:00:00');
        Carbon::setTestNow($now);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($this->enrollment, $this->quiz, $this->student);

        // Persist answers for q1 and q2
        $service->saveAttemptAnswer($attempt, (string) $this->q1->id, 'a', 1, (string) Str::uuid(), $this->student);
        $service->saveAttemptAnswer($attempt, (string) $this->q2->id, 'Detailed essay on crawl budget', 1, (string) Str::uuid(), $this->student);

        // Simulate 15 minutes elapsed
        Carbon::setTestNow($now->copy()->addMinutes(15));

        // Re-request student assessment data (simulating page reload)
        $data = $service->getStudentAssessmentData($this->quiz, $this->enrollment, $this->student);

        $this->assertNotNull($data['in_progress_attempt']);
        $this->assertEquals($attempt->id, $data['in_progress_attempt']['id']);
        // 60 min limit - 15 min = 45 min = 2700 seconds remaining
        $this->assertEquals(2700, $data['in_progress_attempt']['remaining_seconds']);
        // Verify saved answers recovered
        $this->assertEquals('a', $data['in_progress_attempt']['answers'][(string) $this->q1->id]);
        $this->assertEquals('Detailed essay on crawl budget', $data['in_progress_attempt']['answers'][(string) $this->q2->id]);
    }

    public function test_server_side_finalization_lifecycle_and_grading(): void
    {
        Event::fake([AssessmentSubmitted::class, AssessmentGraded::class]);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($this->enrollment, $this->quiz, $this->student);

        // Submit attempt with answers (q1 correct = 5 pts; q2 long answer requires manual grading = 10 pts possible)
        $finalized = $service->submitAttempt($attempt, [
            (string) $this->q1->id => 'a',
            (string) $this->q2->id => 'Essay response',
        ], $this->student);

        $this->assertEquals(AssessmentAttempt::STATUS_SUBMITTED, $finalized->status);
        $this->assertTrue($finalized->requires_manual_grading);
        $this->assertEquals('student_submit', $finalized->finalization_trigger);
        $this->assertEquals(5.0, (float) $finalized->earned_points);
        $this->assertEquals(15.0, (float) $finalized->total_points);
        $this->assertFalse($finalized->passed);

        Event::assertDispatched(AssessmentSubmitted::class);
        Event::assertNotDispatched(AssessmentGraded::class);

        // Idempotent guard: calling finalizeAttempt again must return immediately without side effects
        $finalizationService = app(AssessmentFinalizationService::class);
        $repeat = $finalizationService->finalizeAttempt($finalized, 'student_submit');
        $this->assertEquals($finalized->id, $repeat->id);
    }

    public function test_lazy_finalization_on_expired_attempt_access(): void
    {
        $now = Carbon::parse('2026-11-20 12:00:00');
        Carbon::setTestNow($now);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($this->enrollment, $this->quiz, $this->student);

        // Advance time beyond finalize_after (60 min + 90 sec = 13:01:30)
        Carbon::setTestNow(Carbon::parse('2026-11-20 13:05:00'));

        // 1. Calling saveAttemptAnswer triggers lazy finalization and throws DomainException
        try {
            $service->saveAttemptAnswer(
                attempt: $attempt,
                questionId: (string) $this->q1->id,
                studentAnswer: 'a',
                revision: 1,
                idempotencyKey: (string) Str::uuid(),
                user: $this->student
            );
            $this->fail('Expected DomainException for expired attempt.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('expired and was finalized', $e->getMessage());
        }

        // Attempt in DB should now be finalized with trigger timeout_expired
        $attempt->refresh();
        $this->assertNotEquals(AssessmentAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertEquals('timeout_expired', $attempt->finalization_trigger);
    }

    public function test_artisan_command_batch_finalization_of_expired_attempts(): void
    {
        $now = Carbon::parse('2026-11-20 12:00:00');
        Carbon::setTestNow($now);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($this->enrollment, $this->quiz, $this->student);

        // Advance time past finalize_after
        Carbon::setTestNow(Carbon::parse('2026-11-20 13:10:00'));

        $exitCode = Artisan::call('assessments:finalize-expired', ['--limit' => 50]);
        $this->assertEquals(0, $exitCode);

        $attempt->refresh();
        $this->assertNotEquals(AssessmentAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertEquals('timeout_expired', $attempt->finalization_trigger);
    }

    public function test_evaluator_authorization_matrix(): void
    {
        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($this->enrollment, $this->quiz, $this->student);
        $service->submitAttempt($attempt, [
            (string) $this->q1->id => 'a',
            (string) $this->q2->id => 'Candidate response',
        ], $this->student);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $assignedTrainer = User::factory()->create(['is_active' => true]);
        $assignedTrainer->assignRole('trainer');
        CourseTrainer::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'user_id' => $assignedTrainer->id,
            'is_primary' => true,
        ]);

        $unassignedTrainer = User::factory()->create(['is_active' => true]);
        $unassignedTrainer->assignRole('trainer');

        $supportStaff = User::factory()->create(['is_active' => true]);
        $supportStaff->assignRole('support_staff');

        // 1. Support staff cannot evaluate
        try {
            $service->gradeAttempt($attempt, [
                (string) $this->q2->id => ['points' => 8, 'feedback' => 'Good'],
            ], $supportStaff);
            $this->fail('Expected AuthorizationException for support staff.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('administrators and instructors', $e->getMessage());
        }

        // 2. Unassigned trainer cannot evaluate
        try {
            $service->gradeAttempt($attempt, [
                (string) $this->q2->id => ['points' => 8, 'feedback' => 'Good'],
            ], $unassignedTrainer);
            $this->fail('Expected AuthorizationException for unassigned trainer.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('assigned to them', $e->getMessage());
        }

        // 3. Assigned trainer can evaluate
        $gradedAttempt = $service->gradeAttempt($attempt, [
            (string) $this->q2->id => ['points' => 8, 'feedback' => 'Solid points'],
        ], $assignedTrainer);

        $this->assertEquals(AssessmentAttempt::STATUS_EVALUATED, $gradedAttempt->status);
        $this->assertFalse($gradedAttempt->requires_manual_grading);
        $this->assertEquals(13.0, (float) $gradedAttempt->earned_points); // 5 (q1) + 8 (q2) = 13
        $this->assertEquals(86.67, (float) $gradedAttempt->score_percentage);
        $this->assertTrue($gradedAttempt->passed);
    }

    public function test_finalize_after_exact_boundary_conditions(): void
    {
        $now = Carbon::parse('2026-11-20 12:00:00');
        Carbon::setTestNow($now);

        $service = app(AssessmentService::class);
        $attempt = $service->createAttempt($this->enrollment, $this->quiz, $this->student);
        // expires_at = 13:00:00, finalize_after = 13:01:30 (90 seconds grace)
        $finalizeAfter = $attempt->finalize_after;
        $this->assertEquals(Carbon::parse('2026-11-20 13:01:30')->toIso8601String(), $finalizeAfter->toIso8601String());

        // 1. Immediately before finalize_after (13:01:29) -> write accepted
        Carbon::setTestNow($finalizeAfter->copy()->subSecond());
        $ans = $service->saveAttemptAnswer(
            attempt: $attempt,
            questionId: (string) $this->q1->id,
            studentAnswer: 'a',
            revision: 1,
            idempotencyKey: (string) Str::uuid(),
            user: $this->student
        );
        $this->assertNotNull($ans);
        $attempt->refresh();
        $this->assertEquals(AssessmentAttempt::STATUS_IN_PROGRESS, $attempt->status);

        // 2. Exactly at finalize_after (13:01:30) -> write REJECTED, lazily finalized
        Carbon::setTestNow($finalizeAfter);
        try {
            $service->saveAttemptAnswer(
                attempt: $attempt,
                questionId: (string) $this->q1->id,
                studentAnswer: 'b',
                revision: 2,
                idempotencyKey: (string) Str::uuid(),
                user: $this->student
            );
            $this->fail('Expected DomainException at exact finalize_after boundary.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('expired and was finalized', $e->getMessage());
        }

        $attempt->refresh();
        $this->assertNotEquals(AssessmentAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertEquals('timeout_expired', $attempt->finalization_trigger);

        // 3. Immediately after finalize_after (13:01:31) on new attempt -> write REJECTED
        Carbon::setTestNow($now);
        // Increase attempt limit to allow attempt #2
        $this->quiz->update(['attempt_limit' => 5]);
        $attempt2 = $service->createAttempt($this->enrollment, $this->quiz, $this->student);
        $this->assertEquals(2, $attempt2->attempt_number);

        Carbon::setTestNow($attempt2->finalize_after->copy()->addSecond());
        try {
            $service->saveAttemptAnswer(
                attempt: $attempt2,
                questionId: (string) $this->q1->id,
                studentAnswer: 'a',
                revision: 1,
                idempotencyKey: (string) Str::uuid(),
                user: $this->student
            );
            $this->fail('Expected DomainException immediately after finalize_after.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('expired and was finalized', $e->getMessage());
        }

        $attempt2->refresh();
        $this->assertEquals('timeout_expired', $attempt2->finalization_trigger);
    }

    public function test_manual_grading_regression_scenarios(): void
    {
        $service = app(AssessmentService::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        // Scenario 1: Objective-only quiz auto-grades, passes, and triggers course completion
        $objQuiz = Quiz::create([
            'course_id' => $this->course->id,
            'module_id' => $this->module->id,
            'title' => 'Objective Exam',
            'status' => 'active',
            'is_required' => true,
            'passing_percentage' => 70,
            'attempt_limit' => 5,
        ]);
        $objQ = QuizQuestion::create([
            'quiz_id' => $objQuiz->id,
            'question_text' => '2+2 = ?',
            'question_type' => QuizQuestion::TYPE_SINGLE_CHOICE,
            'options' => [['key' => '4', 'text' => '4']],
            'correct_answer' => '4',
            'points' => 10,
        ]);

        $attempt1 = $service->createAttempt($this->enrollment, $objQuiz, $this->student);
        $finalized1 = $service->submitAttempt($attempt1, [(string) $objQ->id => '4'], $this->student);
        $this->assertEquals(AssessmentAttempt::STATUS_EVALUATED, $finalized1->status);
        $this->assertTrue($finalized1->passed);
        $this->assertFalse($finalized1->requires_manual_grading);

        // Scenario 2: Unanswered objective question is marked is_correct = false, 0 points
        $attempt1b = $service->createAttempt($this->enrollment, $objQuiz, $this->student);
        $finalized1b = $service->submitAttempt($attempt1b, [], $this->student);
        $this->assertEquals(AssessmentAttempt::STATUS_EVALUATED, $finalized1b->status);
        $this->assertFalse($finalized1b->passed);
        $ans1b = $finalized1b->answers()->where('canonical_question_id', (string) $objQ->id)->first();
        $this->assertFalse($ans1b->is_correct);
        $this->assertEquals(0.0, (float) $ans1b->points_awarded);

        // Scenario 3: Subjective-only quiz remains pending manual grading
        $subjQuiz = Quiz::create([
            'course_id' => $this->course->id,
            'module_id' => $this->module->id,
            'title' => 'Subjective Exam',
            'status' => 'active',
            'is_required' => true,
            'passing_percentage' => 70,
            'attempt_limit' => 5,
        ]);
        $subjQ = QuizQuestion::create([
            'quiz_id' => $subjQuiz->id,
            'question_text' => 'Write an essay on PageRank algorithm.',
            'question_type' => QuizQuestion::TYPE_LONG_ANSWER,
            'options' => null,
            'correct_answer' => null,
            'points' => 20,
        ]);

        $attempt2 = $service->createAttempt($this->enrollment, $subjQuiz, $this->student);
        $finalized2 = $service->submitAttempt($attempt2, [(string) $subjQ->id => 'My detailed PageRank essay.'], $this->student);
        $this->assertEquals(AssessmentAttempt::STATUS_SUBMITTED, $finalized2->status);
        $this->assertTrue($finalized2->requires_manual_grading);
        $this->assertFalse($finalized2->passed);

        // Scenario 4: Unanswered subjective question cannot cause attempt to pass
        $attempt2b = $service->createAttempt($this->enrollment, $subjQuiz, $this->student);
        $finalized2b = $service->submitAttempt($attempt2b, [], $this->student);
        $this->assertEquals(AssessmentAttempt::STATUS_SUBMITTED, $finalized2b->status);
        $this->assertTrue($finalized2b->requires_manual_grading);
        $this->assertFalse($finalized2b->passed);

        // Scenario 5: Repeated manual grading requests (idempotent score updates)
        $graded2 = $service->gradeAttempt($finalized2, [
            (string) $subjQ->id => ['points' => 10, 'feedback' => 'First review'],
        ], $admin);
        $this->assertEquals(10.0, (float) $graded2->earned_points);
        $this->assertEquals(50.0, (float) $graded2->score_percentage);
        $this->assertFalse($graded2->passed); // 50% < 70%

        // Re-grade to 18 points (90%) -> passes
        $graded2Again = $service->gradeAttempt($finalized2, [
            (string) $subjQ->id => ['points' => 18, 'feedback' => 'Revised review upon appeal'],
        ], $admin);
        $this->assertEquals(18.0, (float) $graded2Again->earned_points);
        $this->assertEquals(90.0, (float) $graded2Again->score_percentage);
        $this->assertTrue($graded2Again->passed);
    }
}
