<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Models\AssessmentAttemptAnswer;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Quizzes\Models\QuizQuestion;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Student\AssessmentPlayer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class Phase422AssessmentPlayerTest extends TestCase
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
            'title' => 'Technical SEO Player Exam',
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
            'correct_answer' => 'secret_master_url_key',
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

    public function test_player_does_not_leak_correct_answers(): void
    {
        $comp = Livewire::actingAs($this->student)
            ->test(AssessmentPlayer::class, [
                'course' => $this->course,
                'assessment' => $this->quiz,
            ]);

        $comp->call('startAttempt');

        // Verify correct answer is strictly stripped from frontend view
        $comp->assertDontSee('secret_master_url_key');
    }

    public function test_player_start_autosave_and_recovery(): void
    {
        $q1Id = (string) $this->q1->id;

        // 1. Start attempt and save an answer
        $comp1 = Livewire::actingAs($this->student)
            ->test(AssessmentPlayer::class, [
                'course' => $this->course,
                'assessment' => $this->quiz,
            ]);

        $comp1->call('startAttempt')
            ->assertSet('isSubmitted', false)
            ->set('answers.'.$q1Id, 'a')
            ->assertSet('saveStatus', 'saved');

        // Check DB row
        $answerRow = AssessmentAttemptAnswer::where('canonical_question_id', $q1Id)->first();
        $this->assertNotNull($answerRow);
        $this->assertEquals('a', $answerRow->student_answer);
        $this->assertEquals(1, $answerRow->revision);

        // 2. Re-open player in fresh component instance (simulating browser reload)
        $comp2 = Livewire::actingAs($this->student)
            ->test(AssessmentPlayer::class, [
                'course' => $this->course,
                'assessment' => $this->quiz,
            ]);

        $comp2->assertSet('answers.'.$q1Id, 'a');
        $this->assertNotNull($comp2->get('currentAttempt'));
        $this->assertEquals(AssessmentAttempt::STATUS_IN_PROGRESS, $comp2->get('currentAttempt')->status);
    }

    public function test_player_submission_and_completion(): void
    {
        $q1Id = (string) $this->q1->id;
        $q2Id = (string) $this->q2->id;

        $comp = Livewire::actingAs($this->student)
            ->test(AssessmentPlayer::class, [
                'course' => $this->course,
                'assessment' => $this->quiz,
            ]);

        $comp->call('startAttempt')
            ->set('answers.'.$q1Id, 'a')
            ->set('answers.'.$q2Id, 'Detailed long answer essay text')
            ->call('submitAttempt')
            ->assertSet('isSubmitted', true)
            ->assertSet('currentAttempt', null);

        $attempt = AssessmentAttempt::where('user_id', $this->student->id)->latest()->first();
        $this->assertNotNull($attempt);
        $this->assertEquals(AssessmentAttempt::STATUS_SUBMITTED, $attempt->status);
        $this->assertTrue($attempt->requires_manual_grading);
        $this->assertEquals('student_submit', $attempt->finalization_trigger);
    }

    public function test_player_timer_expiration(): void
    {
        $now = Carbon::parse('2026-11-20 12:00:00');
        Carbon::setTestNow($now);

        $comp = Livewire::actingAs($this->student)
            ->test(AssessmentPlayer::class, [
                'course' => $this->course,
                'assessment' => $this->quiz,
            ]);

        $comp->call('startAttempt');
        $attempt = $comp->get('currentAttempt');
        $this->assertNotNull($attempt);

        // Advance time past finalize_after
        Carbon::setTestNow(Carbon::parse('2026-11-20 13:05:00'));

        $comp->call('checkTimer');
        $comp->assertSet('isExpired', true);
        $comp->assertSet('currentAttempt', null);

        $attempt->refresh();
        $this->assertEquals('timeout_expired', $attempt->finalization_trigger);
    }
}
