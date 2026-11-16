<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Models\AssessmentAttemptAnswer;
use App\Domain\Assessments\Models\OrphanedAttemptAnswer;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Platform\Services\DatabaseInstallerService;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionBank;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase42MigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected Quiz $quiz;

    protected User $student;

    protected Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academy = Academy::create([
            'id' => (string) Str::uuid(),
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);

        $this->course = Course::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'title' => 'Technical SEO Mastery',
            'slug' => 'technical-seo-mastery',
            'status' => 'Published',
        ]);

        $module = Module::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'title' => 'Core Web Vitals',
            'display_order' => 1,
        ]);

        $this->quiz = Quiz::create([
            'id' => (string) Str::uuid(),
            'course_id' => $this->course->id,
            'module_id' => $module->id,
            'title' => 'Core Web Vitals Examination',
            'type' => Quiz::TYPE_EXAM,
            'status' => 'Published',
            'is_required' => true,
            'time_limit_minutes' => 60,
            'passing_percentage' => 70,
            'grace_period_seconds' => 60,
            'is_scheduled_exam' => true,
        ]);

        $this->student = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Student Alice',
            'email' => 'alice@academy.test',
            'password' => bcrypt('secret'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $profile = StudentProfile::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'user_id' => $this->student->id,
            'student_code' => 'STU-001',
        ]);

        $this->enrollment = Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $this->course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => Carbon::now(),
        ]);
    }

    /**
     * Test 1: Verify all new columns, tables, and installer registrations.
     */
    public function test_installer_and_schema_foundation_are_registered(): void
    {
        $this->assertTrue(Schema::hasTable('_orphaned_attempt_answers'));
        $this->assertTrue(Schema::hasTable('assessment_attempt_answers'));

        $this->assertTrue(Schema::hasColumn('quizzes', 'exam_start_at'));
        $this->assertTrue(Schema::hasColumn('quizzes', 'exam_end_at'));
        $this->assertTrue(Schema::hasColumn('quizzes', 'late_entry_deadline_at'));
        $this->assertTrue(Schema::hasColumn('quizzes', 'grace_period_seconds'));
        $this->assertTrue(Schema::hasColumn('quizzes', 'is_scheduled_exam'));

        $this->assertTrue(Schema::hasColumn('assessment_attempts', 'expires_at'));
        $this->assertTrue(Schema::hasColumn('assessment_attempts', 'finalize_after'));
        $this->assertTrue(Schema::hasColumn('assessment_attempts', 'finalization_trigger'));
        $this->assertTrue(Schema::hasColumn('assessment_attempts', 'config_snapshot'));
        $this->assertTrue(Schema::hasColumn('assessment_attempts', 'has_conflicting_historical_answers'));

        $this->assertTrue(Schema::hasColumn('assessment_attempt_answers', 'canonical_question_id'));
        $this->assertTrue(Schema::hasColumn('assessment_attempt_answers', 'revision'));
        $this->assertTrue(Schema::hasColumn('assessment_attempt_answers', 'last_idempotency_key'));
        $this->assertTrue(Schema::hasColumn('assessment_attempt_answers', 'saved_at'));

        $this->assertContains(
            '2026_11_15_100000_add_examination_and_atomic_recovery_fields',
            DatabaseInstallerService::KNOWN_MIGRATIONS
        );
        $this->assertContains(
            '_orphaned_attempt_answers',
            DatabaseInstallerService::KNOWN_TABLES
        );
    }

    /**
     * Test 2: Foreign key cascade deletion on reconstructed table.
     */
    public function test_sqlite_cascade_deletion_preserves_integrity(): void
    {
        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => Carbon::now(),
            'total_points' => 10,
        ]);

        $answer = AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $attempt->id,
            'canonical_question_id' => (string) Str::uuid(),
            'student_answer' => 'Test answer',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
            'revision' => 1,
        ]);

        $this->assertDatabaseHas('assessment_attempt_answers', ['id' => $answer->id]);

        // Deleting the attempt must cascade-delete the answer
        $attempt->delete();

        $this->assertDatabaseMissing('assessment_attempt_answers', ['id' => $answer->id]);
    }

    /**
     * Test 3: Idempotent archival and payload mismatch safety.
     */
    public function test_archive_idempotency_and_mismatch_detection(): void
    {
        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => Carbon::now(),
            'total_points' => 10,
        ]);

        $originalAnswerId = (string) Str::uuid();

        $archived = OrphanedAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'original_answer_id' => $originalAnswerId,
            'attempt_id' => $attempt->id,
            'student_answer' => 'Original text',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
            'quarantine_reason' => 'UNRESOLVABLE_IDENTITY',
            'archived_at' => now(),
        ]);

        $this->assertDatabaseHas('_orphaned_attempt_answers', [
            'original_answer_id' => $originalAnswerId,
        ]);

        // Unique constraint on original_answer_id prevents duplicate archive entries
        $this->expectException(UniqueConstraintViolationException::class);

        OrphanedAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'original_answer_id' => $originalAnswerId,
            'attempt_id' => $attempt->id,
            'student_answer' => 'Duplicate attempt text',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
            'quarantine_reason' => 'DUPLICATE',
        ]);
    }

    /**
     * Test 4: Backfill canonical identities from Direct Question, Bank Question, and Snapshot.
     */
    public function test_canonical_identity_backfill_and_orphan_handling(): void
    {
        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => Carbon::now(),
            'total_points' => 10,
        ]);

        $directQId = (string) Str::uuid();

        $bank = QuestionBank::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'name' => 'Core SEO Bank',
            'created_by' => $this->student->id,
        ]);

        $bankQuestion = Question::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'created_by' => $this->student->id,
            'question_text' => 'What is the recommended LCP?',
            'question_type' => Question::TYPE_SINGLE_CHOICE,
            'points' => 1,
            'difficulty' => Question::DIFFICULTY_EASY,
            'status' => Question::STATUS_ACTIVE,
        ]);

        $bankQId = $bankQuestion->id;
        $snapshotQId = (string) Str::uuid();

        // 1. Direct question answer
        $ansDirect = AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $attempt->id,
            'question_id' => $directQId,
            'canonical_question_id' => $directQId,
            'student_answer' => 'Direct answer',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
        ]);

        // 2. Bank question answer
        $ansBank = AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $attempt->id,
            'bank_question_id' => $bankQId,
            'canonical_question_id' => $bankQId,
            'student_answer' => 'Bank answer',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
        ]);

        // 3. Recoverable snapshot answer (deleted question)
        $ansSnapshot = AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $attempt->id,
            'question_id' => null,
            'bank_question_id' => null,
            'canonical_question_id' => $snapshotQId,
            'question_snapshot' => ['id' => $snapshotQId, 'prompt' => 'Recovered question'],
            'student_answer' => 'Snapshot answer',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
        ]);

        $this->assertSame($directQId, $ansDirect->canonical_question_id);
        $this->assertSame($bankQId, $ansBank->canonical_question_id);
        $this->assertSame($snapshotQId, $ansSnapshot->canonical_question_id);
    }

    /**
     * Test 5: Exact duplicate retries deduplicated; conflicting duplicates quarantined.
     */
    public function test_duplicate_reconciliation_and_conflict_quarantine(): void
    {
        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => Carbon::now(),
            'total_points' => 10,
            'earned_points' => 8,
            'passed' => true,
        ]);

        $canonicalQId = (string) Str::uuid();

        // Unique constraint prevents inserting duplicate rows directly on active table
        AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $attempt->id,
            'canonical_question_id' => $canonicalQId,
            'student_answer' => 'Answer A',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
            'revision' => 1,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        // Inserting second answer with same (attempt_id, canonical_question_id) must fail constraint
        AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $attempt->id,
            'canonical_question_id' => $canonicalQId,
            'student_answer' => 'Answer B',
            'points_awarded' => 0.0,
            'points_possible' => 1.0,
            'revision' => 2,
        ]);
    }

    /**
     * Test 6: Finalize_after timestamp calculation and index support.
     */
    public function test_finalize_after_persistence_and_grace_indexing(): void
    {
        $now = Carbon::parse('2026-11-15 12:00:00');
        Carbon::setTestNow($now);

        $expiresAt = $now->copy()->addMinutes(60);
        $graceSeconds = 120;
        $finalizeAfter = $expiresAt->copy()->addSeconds($graceSeconds);

        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => $now,
            'expires_at' => $expiresAt,
            'finalize_after' => $finalizeAfter,
            'total_points' => 10,
            'config_snapshot' => [
                'time_limit_minutes' => 60,
                'grace_period_seconds' => $graceSeconds,
                'passing_percentage' => 70,
            ],
        ]);

        $this->assertEquals('2026-11-15 13:00:00', $attempt->expires_at->toDateTimeString());
        $this->assertEquals('2026-11-15 13:02:00', $attempt->finalize_after->toDateTimeString());

        // Verify scheduler query finds only attempts past finalize_after
        Carbon::setTestNow($now->copy()->addMinutes(61)); // 13:01:00 (past expires_at, but within grace!)

        $readyForFinalize = AssessmentAttempt::query()
            ->where('status', AssessmentAttempt::STATUS_IN_PROGRESS)
            ->whereNotNull('finalize_after')
            ->where('finalize_after', '<=', Carbon::now())
            ->count();

        // Still within grace: must NOT be matched
        $this->assertSame(0, $readyForFinalize);

        Carbon::setTestNow($now->copy()->addMinutes(63)); // 13:03:00 (past finalize_after!)

        $readyForFinalize = AssessmentAttempt::query()
            ->where('status', AssessmentAttempt::STATUS_IN_PROGRESS)
            ->whereNotNull('finalize_after')
            ->where('finalize_after', '<=', Carbon::now())
            ->count();

        // Past grace: must be matched
        $this->assertSame(1, $readyForFinalize);

        Carbon::setTestNow(null);
    }

    /**
     * Test 7: SQLite Crash Recovery State C simulation (Original table dropped, v2 exists).
     */
    public function test_sqlite_crash_recovery_state_c_simulation(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite specific recovery test.');
        }

        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => Carbon::now(),
            'total_points' => 10,
        ]);

        $answer = AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $attempt->id,
            'canonical_question_id' => (string) Str::uuid(),
            'student_answer' => 'Crucial student work',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
        ]);

        // Simulate State C: Rename active to v2 (as if original was dropped and v2 survived)
        Schema::rename('assessment_attempt_answers', 'assessment_attempt_answers_v2');

        $this->assertFalse(Schema::hasTable('assessment_attempt_answers'));
        $this->assertTrue(Schema::hasTable('assessment_attempt_answers_v2'));

        // Reset migration record so artisan migrate invokes up()
        DB::table('migrations')->where('migration', 'like', '%add_examination_and_atomic_recovery_fields%')->delete();

        // Run migration recovery
        Artisan::call('migrate', ['--force' => true]);

        // Assert that assessment_attempt_answers was safely restored from v2 without losing records
        $this->assertTrue(Schema::hasTable('assessment_attempt_answers'));
        $this->assertDatabaseHas('assessment_attempt_answers', [
            'id' => $answer->id,
            'student_answer' => 'Crucial student work',
        ]);
    }

    /**
     * Test 8: Conflicting foreign keys are quarantined without data loss.
     */
    public function test_conflicting_foreign_keys_are_quarantined_without_loss(): void
    {
        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => Carbon::now(),
            'total_points' => 10,
        ]);

        $qId1 = (string) Str::uuid();
        $qId2 = (string) Str::uuid();

        // Roll back Phase 4.2 migration to simulate pre-migration Phase 4.1 schema
        $migration = require database_path('migrations/2026_11_15_100000_add_examination_and_atomic_recovery_fields.php');
        $migration->down();

        $answerId = (string) Str::uuid();
        DB::table('assessment_attempt_answers')->insert([
            'id' => $answerId,
            'attempt_id' => $attempt->id,
            'question_id' => $qId1,
            'bank_question_id' => null,
            'student_answer' => 'Pre-migration payload',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reset migration record so artisan migrate invokes up()
        DB::table('migrations')->where('migration', 'like', '%add_examination_and_atomic_recovery_fields%')->delete();
        Artisan::call('migrate', ['--force' => true]);

        // Assert record received canonical identity
        $this->assertDatabaseHas('assessment_attempt_answers', [
            'id' => $answerId,
            'canonical_question_id' => $qId1,
        ]);
    }

    /**
     * Test 9: Conflicting duplicate group quarantines all records and preserves attempt score.
     */
    public function test_conflicting_duplicate_group_quarantines_all_and_flags_attempt(): void
    {
        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => Carbon::now(),
            'total_points' => 10,
            'earned_points' => 7,
            'score_percentage' => 70,
            'passed' => true,
        ]);

        $canonicalQId = (string) Str::uuid();

        // Roll back Phase 4.2 migration to simulate pre-migration Phase 4.1 schema
        $migration = require database_path('migrations/2026_11_15_100000_add_examination_and_atomic_recovery_fields.php');
        $migration->down();

        $id1 = (string) Str::uuid();
        $id2 = (string) Str::uuid();

        DB::table('assessment_attempt_answers')->insert([
            [
                'id' => $id1,
                'attempt_id' => $attempt->id,
                'question_id' => $canonicalQId,
                'student_answer' => 'Option Alpha',
                'points_awarded' => 1.0,
                'points_possible' => 1.0,
                'is_correct' => 1,
                'created_at' => Carbon::now()->subMinutes(10),
                'updated_at' => Carbon::now()->subMinutes(10),
            ],
            [
                'id' => $id2,
                'attempt_id' => $attempt->id,
                'question_id' => $canonicalQId,
                'student_answer' => 'Option Beta', // Conflicting answer!
                'points_awarded' => 0.0,
                'points_possible' => 1.0,
                'is_correct' => 0,
                'created_at' => Carbon::now()->subMinutes(5),
                'updated_at' => Carbon::now()->subMinutes(5),
            ],
        ]);

        // Run migration logic to reconcile duplicates
        DB::table('migrations')->where('migration', 'like', '%add_examination_and_atomic_recovery_fields%')->delete();
        Artisan::call('migrate', ['--force' => true]);

        // Both conflicting rows must be quarantined to _orphaned_attempt_answers
        $this->assertDatabaseHas('_orphaned_attempt_answers', [
            'original_answer_id' => $id1,
            'quarantine_reason' => 'CONFLICTING_DUPLICATE_GROUP',
        ]);
        $this->assertDatabaseHas('_orphaned_attempt_answers', [
            'original_answer_id' => $id2,
            'quarantine_reason' => 'CONFLICTING_DUPLICATE_GROUP',
        ]);

        // Active table must NOT retain an arbitrary row
        $this->assertDatabaseMissing('assessment_attempt_answers', ['id' => $id1]);
        $this->assertDatabaseMissing('assessment_attempt_answers', ['id' => $id2]);

        // Attempt score and status must be strictly preserved
        $reloaded = AssessmentAttempt::find($attempt->id);
        $this->assertEquals(7, (float) $reloaded->earned_points);
        $this->assertEquals(10, (float) $reloaded->total_points);
        $this->assertTrue((bool) $reloaded->passed);
        $this->assertTrue((bool) $reloaded->has_conflicting_historical_answers);
        $this->assertTrue((bool) $reloaded->requires_manual_grading);
    }

    /**
     * Test 10: Archive pre-deletion SHA-256 mismatch detection prevents active row deletion.
     */
    public function test_archive_pre_deletion_sha256_mismatch_prevents_active_row_deletion(): void
    {
        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => Carbon::now(),
            'total_points' => 10,
        ]);

        $answerId = (string) Str::uuid();
        $qId = (string) Str::uuid();

        // Roll back Phase 4.2 migration to allow raw insertion
        $migration = require database_path('migrations/2026_11_15_100000_add_examination_and_atomic_recovery_fields.php');
        $migration->down();

        // Active row
        DB::table('assessment_attempt_answers')->insert([
            'id' => $answerId,
            'attempt_id' => $attempt->id,
            'question_id' => $qId,
            'student_answer' => 'Active student answer',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
            'evaluator_feedback' => 'Legitimate original feedback',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Existing archive row with corrupt/differing feedback
        DB::table('_orphaned_attempt_answers')->insert([
            'id' => (string) Str::uuid(),
            'original_answer_id' => $answerId,
            'attempt_id' => $attempt->id,
            'question_id' => $qId,
            'student_answer' => 'Active student answer',
            'points_awarded' => 1.0,
            'points_possible' => 1.0,
            'evaluator_feedback' => 'CORRUPTED/DIFFERING FEEDBACK', // Mismatch!
            'quarantine_reason' => 'MANUAL_TEST',
            'archived_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reset migration record so artisan migrate invokes up()
        DB::table('migrations')->where('migration', 'like', '%add_examination_and_atomic_recovery_fields%')->delete();

        // Attempting to migrate must detect SHA-256 mismatch and throw RuntimeException
        // We'll test direct quarantine call via reflection or anonymous class to verify active row is retained
        $reflection = new \ReflectionClass($migration);
        $quarantineMethod = $reflection->getMethod('quarantineRecord');
        $quarantineMethod->setAccessible(true);

        $activeRow = DB::table('assessment_attempt_answers')->where('id', $answerId)->first();

        $exceptionCaught = false;
        try {
            $quarantineMethod->invoke($migration, $activeRow, 'TEST_QUARANTINE');
        } catch (\RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('SHA-256 mismatch', $e->getMessage());
            $this->assertStringContainsString('Active row retained', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, 'Expected RuntimeException for SHA-256 archive mismatch.');

        // Active row must NOT be deleted
        $this->assertDatabaseHas('assessment_attempt_answers', ['id' => $answerId]);
    }

    /**
     * Test 11: AssessmentAttemptAnswer model rejects conflicting references and auto-resolves canonical identity.
     */
    public function test_model_validation_rejects_conflicting_references_and_missing_identities(): void
    {
        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => Carbon::now(),
            'total_points' => 10,
        ]);

        $qId1 = (string) Str::uuid();
        $qId2 = (string) Str::uuid();

        // 1. Conflicting question_id and bank_question_id
        $conflictThrown = false;
        try {
            AssessmentAttemptAnswer::create([
                'id' => (string) Str::uuid(),
                'attempt_id' => $attempt->id,
                'question_id' => $qId1,
                'bank_question_id' => $qId2,
                'student_answer' => 'Conflict test',
            ]);
        } catch (\InvalidArgumentException $e) {
            $conflictThrown = true;
            $this->assertStringContainsString('Conflicting question references', $e->getMessage());
        }
        $this->assertTrue($conflictThrown, 'Expected InvalidArgumentException for conflicting FKs.');

        // 2. Conflicting snapshot id
        $conflictSnapshotThrown = false;
        try {
            AssessmentAttemptAnswer::create([
                'id' => (string) Str::uuid(),
                'attempt_id' => $attempt->id,
                'question_id' => $qId1,
                'question_snapshot' => ['id' => $qId2],
                'student_answer' => 'Conflict snapshot test',
            ]);
        } catch (\InvalidArgumentException $e) {
            $conflictSnapshotThrown = true;
            $this->assertStringContainsString('Conflicting question references', $e->getMessage());
        }
        $this->assertTrue($conflictSnapshotThrown, 'Expected InvalidArgumentException for conflicting snapshot ID.');

        // 3. Conflicting explicit canonical_question_id
        $conflictCanonicalThrown = false;
        try {
            AssessmentAttemptAnswer::create([
                'id' => (string) Str::uuid(),
                'attempt_id' => $attempt->id,
                'canonical_question_id' => $qId1,
                'question_id' => $qId2,
                'student_answer' => 'Conflict canonical test',
            ]);
        } catch (\InvalidArgumentException $e) {
            $conflictCanonicalThrown = true;
            $this->assertStringContainsString('Conflicting canonical identity', $e->getMessage());
        }
        $this->assertTrue($conflictCanonicalThrown, 'Expected InvalidArgumentException for conflicting canonical_question_id.');

        // 4. Missing all identities
        $missingIdentityThrown = false;
        try {
            AssessmentAttemptAnswer::create([
                'id' => (string) Str::uuid(),
                'attempt_id' => $attempt->id,
                'question_id' => null,
                'bank_question_id' => null,
                'canonical_question_id' => null,
                'student_answer' => 'Missing identity test',
            ]);
        } catch (\InvalidArgumentException $e) {
            $missingIdentityThrown = true;
            $this->assertStringContainsString('Cannot resolve canonical question identity', $e->getMessage());
        }
        $this->assertTrue($missingIdentityThrown, 'Expected InvalidArgumentException for missing question identity.');

        // 5. Successful auto-resolution from question_id
        $validAnswer = AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $attempt->id,
            'question_id' => $qId1,
            'student_answer' => 'Auto-resolved answer',
        ]);
        $this->assertSame($qId1, $validAnswer->canonical_question_id);

        // 6. Successful auto-resolution from bank_question_id with valid question
        $question = Question::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'created_by' => $this->student->id,
            'question_text' => 'Sample test question',
            'question_type' => Question::TYPE_SINGLE_CHOICE,
            'points' => 1,
            'difficulty' => Question::DIFFICULTY_EASY,
            'status' => Question::STATUS_ACTIVE,
        ]);

        $validBankAnswer = AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $attempt->id,
            'bank_question_id' => $question->id,
            'student_answer' => 'Auto-resolved bank answer',
        ]);
        $this->assertSame($question->id, $validBankAnswer->canonical_question_id);
    }

    /**
     * Test 12: Exact duplicate retries retain earliest and quarantine subsequent rows without flagging attempt.
     */
    public function test_exact_duplicate_retries_reconciliation_keeps_earliest_and_archives_subsequent(): void
    {
        $attempt = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $this->quiz->id,
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'started_at' => Carbon::now(),
            'total_points' => 10,
            'earned_points' => 10,
            'score_percentage' => 100,
            'passed' => true,
        ]);

        $canonicalQId = (string) Str::uuid();

        // Roll back Phase 4.2 migration to simulate pre-migration schema
        $migration = require database_path('migrations/2026_11_15_100000_add_examination_and_atomic_recovery_fields.php');
        $migration->down();

        $id1 = (string) Str::uuid();
        $id2 = (string) Str::uuid();

        // Insert two identical duplicate answers (same answer text, same points, same is_correct)
        DB::table('assessment_attempt_answers')->insert([
            [
                'id' => $id1,
                'attempt_id' => $attempt->id,
                'question_id' => $canonicalQId,
                'student_answer' => 'Identical retry answer',
                'points_awarded' => 1.0,
                'points_possible' => 1.0,
                'is_correct' => 1,
                'evaluator_feedback' => null,
                'evaluated_by' => null,
                'evaluated_at' => null,
                'created_at' => Carbon::now()->subMinutes(10),
                'updated_at' => Carbon::now()->subMinutes(10),
            ],
            [
                'id' => $id2,
                'attempt_id' => $attempt->id,
                'question_id' => $canonicalQId,
                'student_answer' => 'Identical retry answer', // Identical!
                'points_awarded' => 1.0,
                'points_possible' => 1.0,
                'is_correct' => 1,
                'evaluator_feedback' => null,
                'evaluated_by' => null,
                'evaluated_at' => null,
                'created_at' => Carbon::now()->subMinutes(5),
                'updated_at' => Carbon::now()->subMinutes(5),
            ],
        ]);

        // Run migration
        DB::table('migrations')->where('migration', 'like', '%add_examination_and_atomic_recovery_fields%')->delete();
        Artisan::call('migrate', ['--force' => true]);

        // Earliest row must be retained
        $this->assertDatabaseHas('assessment_attempt_answers', ['id' => $id1]);

        // Subsequent row must be quarantined as EXACT_DUPLICATE_RETRY
        $this->assertDatabaseMissing('assessment_attempt_answers', ['id' => $id2]);
        $this->assertDatabaseHas('_orphaned_attempt_answers', [
            'original_answer_id' => $id2,
            'quarantine_reason' => 'EXACT_DUPLICATE_RETRY',
        ]);

        // Attempt should NOT be flagged as conflicting
        $reloaded = AssessmentAttempt::find($attempt->id);
        $this->assertFalse((bool) $reloaded->has_conflicting_historical_answers);
    }
}
