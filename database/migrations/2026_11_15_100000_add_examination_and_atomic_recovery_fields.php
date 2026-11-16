<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ---------------------------------------------------------------------
        // Checkpoint 1: Create `_orphaned_attempt_answers` archive table
        // ---------------------------------------------------------------------
        if (! Schema::hasTable('_orphaned_attempt_answers')) {
            Schema::create('_orphaned_attempt_answers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('original_answer_id')->unique();
                $table->uuid('attempt_id');
                $table->uuid('question_id')->nullable();
                $table->uuid('bank_question_id')->nullable();
                $table->string('canonical_question_id', 64)->nullable();
                $table->longText('student_answer')->nullable();
                $table->unsignedInteger('revision')->default(1);
                $table->string('last_idempotency_key', 64)->nullable();
                $table->dateTime('saved_at')->nullable();
                $table->boolean('is_correct')->nullable();
                $table->decimal('points_awarded', 8, 2)->default(0);
                $table->decimal('points_possible', 8, 2)->default(1);
                $table->text('evaluator_feedback')->nullable();
                $table->uuid('evaluated_by')->nullable();
                $table->dateTime('evaluated_at')->nullable();
                $table->json('question_snapshot')->nullable();
                $table->string('quarantine_reason', 64);
                $table->json('quarantine_metadata')->nullable();
                $table->timestamp('archived_at')->useCurrent();
                $table->timestamps();

                $table->index(['attempt_id', 'canonical_question_id'], 'idx_orphaned_attempt_canonical');
                $table->index('quarantine_reason', 'idx_orphaned_reason');
            });
        }

        // ---------------------------------------------------------------------
        // Checkpoint 2: Update `quizzes` table additively
        // ---------------------------------------------------------------------
        if (Schema::hasTable('quizzes')) {
            Schema::table('quizzes', function (Blueprint $table) {
                if (! Schema::hasColumn('quizzes', 'exam_start_at')) {
                    $table->dateTime('exam_start_at')->nullable()->after('time_limit_minutes');
                }
                if (! Schema::hasColumn('quizzes', 'exam_end_at')) {
                    $table->dateTime('exam_end_at')->nullable()->after('exam_start_at');
                }
                if (! Schema::hasColumn('quizzes', 'late_entry_deadline_at')) {
                    $table->dateTime('late_entry_deadline_at')->nullable()->after('exam_end_at');
                }
                if (! Schema::hasColumn('quizzes', 'grace_period_seconds')) {
                    $table->unsignedInteger('grace_period_seconds')->default(60)->after('late_entry_deadline_at');
                }
                if (! Schema::hasColumn('quizzes', 'timezone')) {
                    $table->string('timezone', 64)->default('UTC')->after('grace_period_seconds');
                }
                if (! Schema::hasColumn('quizzes', 'is_scheduled_exam')) {
                    $table->boolean('is_scheduled_exam')->default(false)->after('timezone');
                }
            });
        }

        // ---------------------------------------------------------------------
        // Checkpoint 3: Update `assessment_attempts` table additively
        // ---------------------------------------------------------------------
        if (Schema::hasTable('assessment_attempts')) {
            Schema::table('assessment_attempts', function (Blueprint $table) {
                if (! Schema::hasColumn('assessment_attempts', 'expires_at')) {
                    $table->dateTime('expires_at')->nullable()->after('started_at');
                }
                if (! Schema::hasColumn('assessment_attempts', 'finalize_after')) {
                    $table->dateTime('finalize_after')->nullable()->after('expires_at');
                }
                if (! Schema::hasColumn('assessment_attempts', 'finalization_trigger')) {
                    $table->string('finalization_trigger', 32)->nullable()->after('status');
                }
                if (! Schema::hasColumn('assessment_attempts', 'config_snapshot')) {
                    $table->json('config_snapshot')->nullable()->after('question_snapshots');
                }
                if (! Schema::hasColumn('assessment_attempts', 'has_conflicting_historical_answers')) {
                    $table->boolean('has_conflicting_historical_answers')->default(false)->after('config_snapshot');
                }
            });

            // Add indexes if not present
            try {
                Schema::table('assessment_attempts', function (Blueprint $table) {
                    $table->index(['status', 'finalize_after'], 'idx_attempts_status_finalize');
                    $table->index(['status', 'expires_at'], 'idx_attempts_status_expires');
                });
            } catch (Throwable) {
                // Ignore if index already exists
            }
        }

        // ---------------------------------------------------------------------
        // Checkpoint 4: Additive nullable columns on `assessment_attempt_answers`
        // ---------------------------------------------------------------------
        if (Schema::hasTable('assessment_attempt_answers') || Schema::hasTable('assessment_attempt_answers_v2')) {
            if (Schema::hasTable('assessment_attempt_answers')) {
                Schema::table('assessment_attempt_answers', function (Blueprint $table) {
                    if (! Schema::hasColumn('assessment_attempt_answers', 'canonical_question_id')) {
                        $table->string('canonical_question_id', 64)->nullable()->after('bank_question_id');
                    }
                    if (! Schema::hasColumn('assessment_attempt_answers', 'revision')) {
                        $table->unsignedInteger('revision')->default(1)->after('student_answer');
                    }
                    if (! Schema::hasColumn('assessment_attempt_answers', 'last_idempotency_key')) {
                        $table->string('last_idempotency_key', 64)->nullable()->after('revision');
                    }
                    if (! Schema::hasColumn('assessment_attempt_answers', 'saved_at')) {
                        $table->dateTime('saved_at')->nullable()->after('last_idempotency_key');
                    }
                });

                // -----------------------------------------------------------------
                // Checkpoint 5: Canonical Identity Classification & Backfill
                // -----------------------------------------------------------------
                $this->classifyAndBackfillCanonicalIdentities();

                // -----------------------------------------------------------------
                // Checkpoint 6: Historical Duplicate Handling & Conflict Quarantine
                // -----------------------------------------------------------------
                $this->reconcileDuplicatesAndQuarantineConflicts();
            }

            // -----------------------------------------------------------------
            // Checkpoint 7: Engine-Specific Finalization (SQLite Rebuild or MySQL DDL)
            // -----------------------------------------------------------------
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                $this->reconstructSqliteTable();
            } elseif (Schema::hasTable('assessment_attempt_answers')) {
                $this->finalizeMysqlTable();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if (Schema::hasTable('assessment_attempt_answers')) {
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');

                Schema::dropIfExists('assessment_attempt_answers_v1_rollback');

                Schema::create('assessment_attempt_answers_v1_rollback', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
                    $table->uuid('question_id')->nullable();
                    $table->foreignUuid('bank_question_id')->nullable()->constrained('questions')->nullOnDelete();
                    $table->longText('student_answer')->nullable();
                    $table->boolean('is_correct')->nullable();
                    $table->decimal('points_awarded', 8, 2)->default(0);
                    $table->decimal('points_possible', 8, 2)->default(1);
                    $table->text('evaluator_feedback')->nullable();
                    $table->foreignUuid('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->dateTime('evaluated_at')->nullable();
                    $table->json('question_snapshot')->nullable();
                    $table->timestamps();

                    $table->index(['attempt_id', 'question_id']);
                    $table->index(['attempt_id', 'bank_question_id']);
                });

                DB::statement('INSERT INTO assessment_attempt_answers_v1_rollback (
                    id, attempt_id, question_id, bank_question_id, student_answer,
                    is_correct, points_awarded, points_possible, evaluator_feedback,
                    evaluated_by, evaluated_at, question_snapshot, created_at, updated_at
                ) SELECT 
                    id, attempt_id, question_id, bank_question_id, student_answer,
                    is_correct, points_awarded, points_possible, evaluator_feedback,
                    evaluated_by, evaluated_at, question_snapshot, created_at, updated_at
                FROM assessment_attempt_answers');

                Schema::drop('assessment_attempt_answers');
                Schema::rename('assessment_attempt_answers_v1_rollback', 'assessment_attempt_answers');

                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                try {
                    Schema::table('assessment_attempt_answers', function (Blueprint $table) {
                        $table->dropUnique('uq_attempt_canonical_question');
                    });
                } catch (Throwable) {
                }

                try {
                    Schema::table('assessment_attempt_answers', function (Blueprint $table) {
                        $table->dropColumn(['canonical_question_id', 'revision', 'last_idempotency_key', 'saved_at']);
                    });
                } catch (Throwable) {
                }
            }
        }

        if (Schema::hasTable('assessment_attempts')) {
            try {
                Schema::table('assessment_attempts', function (Blueprint $table) {
                    $table->dropIndex('idx_attempts_status_finalize');
                    $table->dropIndex('idx_attempts_status_expires');
                });
            } catch (Throwable) {
            }

            try {
                Schema::table('assessment_attempts', function (Blueprint $table) {
                    $table->dropColumn([
                        'expires_at',
                        'finalize_after',
                        'finalization_trigger',
                        'config_snapshot',
                        'has_conflicting_historical_answers',
                    ]);
                });
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('quizzes')) {
            try {
                Schema::table('quizzes', function (Blueprint $table) {
                    $table->dropColumn([
                        'exam_start_at',
                        'exam_end_at',
                        'late_entry_deadline_at',
                        'grace_period_seconds',
                        'timezone',
                        'is_scheduled_exam',
                    ]);
                });
            } catch (Throwable) {
            }
        }

        // Note: `_orphaned_attempt_answers` is intentionally preserved during rollback to protect forensic evidence
    }

    /**
     * Classify and backfill canonical question identities.
     */
    protected function classifyAndBackfillCanonicalIdentities(): void
    {
        // Fetch all active answers where canonical_question_id is NULL
        $answers = DB::table('assessment_attempt_answers')
            ->whereNull('canonical_question_id')
            ->get();

        foreach ($answers as $answer) {
            $hasQuestionId = ! empty($answer->question_id);
            $hasBankQuestionId = ! empty($answer->bank_question_id);
            $hasSnapshot = ! empty($answer->question_snapshot);

            $snapshotId = null;
            if ($hasSnapshot) {
                $snapshot = json_decode($answer->question_snapshot, true);
                if (is_array($snapshot) && ! empty($snapshot['id'])) {
                    $snapshotId = (string) $snapshot['id'];
                }
            }

            // Case 1: Both direct and bank IDs populated but conflicting
            if ($hasQuestionId && $hasBankQuestionId && (string) $answer->question_id !== (string) $answer->bank_question_id) {
                $this->quarantineRecord($answer, 'CONFLICTING_FOREIGN_KEYS', [
                    'question_id' => $answer->question_id,
                    'bank_question_id' => $answer->bank_question_id,
                    'snapshot_id' => $snapshotId,
                ]);

                continue;
            }

            // Case 2: Snapshot ID conflicts with populated foreign keys
            if ($snapshotId !== null) {
                if ($hasQuestionId && (string) $answer->question_id !== $snapshotId) {
                    $this->quarantineRecord($answer, 'SNAPSHOT_REFERENCE_MISMATCH', [
                        'question_id' => $answer->question_id,
                        'snapshot_id' => $snapshotId,
                    ]);

                    continue;
                }
                if ($hasBankQuestionId && (string) $answer->bank_question_id !== $snapshotId) {
                    $this->quarantineRecord($answer, 'SNAPSHOT_REFERENCE_MISMATCH', [
                        'bank_question_id' => $answer->bank_question_id,
                        'snapshot_id' => $snapshotId,
                    ]);

                    continue;
                }
            }

            // Determine verifiable canonical ID
            $canonicalId = null;
            if ($hasQuestionId) {
                $canonicalId = (string) $answer->question_id;
            } elseif ($hasBankQuestionId) {
                $canonicalId = (string) $answer->bank_question_id;
            } elseif ($snapshotId !== null) {
                $canonicalId = $snapshotId;
            }

            if ($canonicalId !== null) {
                // Backfill verified identity
                DB::table('assessment_attempt_answers')
                    ->where('id', $answer->id)
                    ->update(['canonical_question_id' => $canonicalId]);
            } else {
                // Unresolvable orphan: No keys, missing or invalid snapshot
                $this->quarantineRecord($answer, 'UNRESOLVABLE_IDENTITY', [
                    'question_id' => $answer->question_id,
                    'bank_question_id' => $answer->bank_question_id,
                    'has_snapshot' => $hasSnapshot,
                ]);
            }
        }
    }

    /**
     * Compute a deterministic SHA-256 hash across all 17 source attributes.
     */
    protected function computeRecordPayloadHash(object|array $record): string
    {
        $get = function (string $key) use ($record) {
            if (is_object($record)) {
                return $record->$key ?? null;
            }

            return $record[$key] ?? null;
        };

        // 1. Strings / UUIDs
        $attemptId = ! empty($get('attempt_id')) ? (string) $get('attempt_id') : null;
        $questionId = ! empty($get('question_id')) ? (string) $get('question_id') : null;
        $bankQuestionId = ! empty($get('bank_question_id')) ? (string) $get('bank_question_id') : null;
        $canonicalQuestionId = ! empty($get('canonical_question_id')) ? (string) $get('canonical_question_id') : null;
        $studentAnswer = $get('student_answer') !== null ? (string) $get('student_answer') : null;
        $lastIdempotencyKey = ! empty($get('last_idempotency_key')) ? (string) $get('last_idempotency_key') : null;
        $evaluatorFeedback = $get('evaluator_feedback') !== null ? (string) $get('evaluator_feedback') : null;
        $evaluatedBy = ! empty($get('evaluated_by')) ? (string) $get('evaluated_by') : null;

        // 2. Integers
        $revision = (int) ($get('revision') ?? 1);

        // 3. Decimals / Floats
        $pointsAwarded = number_format((float) ($get('points_awarded') ?? 0), 2, '.', '');
        $pointsPossible = number_format((float) ($get('points_possible') ?? 1), 2, '.', '');

        // 4. Booleans (tri-state)
        $isCorrectRaw = $get('is_correct');
        $isCorrect = $isCorrectRaw === null ? null : ((bool) $isCorrectRaw ? 1 : 0);

        // 5. Timestamps / Dates normalized to UTC ISO 8601
        $normalizeDate = function ($value): ?string {
            if (empty($value)) {
                return null;
            }
            try {
                return Carbon::parse($value)->setTimezone('UTC')->toIso8601String();
            } catch (Throwable) {
                return (string) $value;
            }
        };

        $savedAt = $normalizeDate($get('saved_at'));
        $evaluatedAt = $normalizeDate($get('evaluated_at'));
        $createdAt = $normalizeDate($get('created_at'));
        $updatedAt = $normalizeDate($get('updated_at'));

        // 6. JSON Snapshot (recursive key-sorted)
        $rawSnapshot = $get('question_snapshot');
        $normalizedSnapshot = null;
        if (! empty($rawSnapshot)) {
            $parsed = is_string($rawSnapshot) ? json_decode($rawSnapshot, true) : $rawSnapshot;
            if (is_array($parsed)) {
                $sortRecursive = function (&$arr) use (&$sortRecursive) {
                    if (! is_array($arr)) {
                        return;
                    }
                    if (array_is_list($arr)) {
                        foreach ($arr as &$item) {
                            if (is_array($item)) {
                                $sortRecursive($item);
                            }
                        }
                    } else {
                        ksort($arr);
                        foreach ($arr as &$item) {
                            if (is_array($item)) {
                                $sortRecursive($item);
                            }
                        }
                    }
                };
                $sortRecursive($parsed);
                $normalizedSnapshot = json_encode($parsed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $normalizedSnapshot = (string) $rawSnapshot;
            }
        }

        $payload = [
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'bank_question_id' => $bankQuestionId,
            'canonical_question_id' => $canonicalQuestionId,
            'student_answer' => $studentAnswer,
            'revision' => $revision,
            'last_idempotency_key' => $lastIdempotencyKey,
            'saved_at' => $savedAt,
            'is_correct' => $isCorrect,
            'points_awarded' => $pointsAwarded,
            'points_possible' => $pointsPossible,
            'evaluator_feedback' => $evaluatorFeedback,
            'evaluated_by' => $evaluatedBy,
            'evaluated_at' => $evaluatedAt,
            'question_snapshot' => $normalizedSnapshot,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        return hash('sha256', json_encode($payload));
    }

    /**
     * Compute a hash of substantive attributes to detect conflicting duplicates.
     */
    protected function computeDuplicateComparisonHash(object|array $record): string
    {
        $get = function (string $key) use ($record) {
            if (is_object($record)) {
                return $record->$key ?? null;
            }

            return $record[$key] ?? null;
        };

        $rawSnapshot = $get('question_snapshot');
        $normalizedSnapshot = null;
        if (! empty($rawSnapshot)) {
            $parsed = is_string($rawSnapshot) ? json_decode($rawSnapshot, true) : $rawSnapshot;
            if (is_array($parsed)) {
                $sortRecursive = function (&$arr) use (&$sortRecursive) {
                    if (! is_array($arr)) {
                        return;
                    }
                    if (array_is_list($arr)) {
                        foreach ($arr as &$item) {
                            if (is_array($item)) {
                                $sortRecursive($item);
                            }
                        }
                    } else {
                        ksort($arr);
                        foreach ($arr as &$item) {
                            if (is_array($item)) {
                                $sortRecursive($item);
                            }
                        }
                    }
                };
                $sortRecursive($parsed);
                $normalizedSnapshot = json_encode($parsed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $normalizedSnapshot = (string) $rawSnapshot;
            }
        }

        $normalizeDate = function ($value): ?string {
            if (empty($value)) {
                return null;
            }
            try {
                return Carbon::parse($value)->setTimezone('UTC')->toIso8601String();
            } catch (Throwable) {
                return (string) $value;
            }
        };

        $isCorrectRaw = $get('is_correct');
        $isCorrect = $isCorrectRaw === null ? null : ((bool) $isCorrectRaw ? 1 : 0);

        $payload = [
            'student_answer' => $get('student_answer') !== null ? (string) $get('student_answer') : null,
            'revision' => (int) ($get('revision') ?? 1),
            'last_idempotency_key' => ! empty($get('last_idempotency_key')) ? (string) $get('last_idempotency_key') : null,
            'is_correct' => $isCorrect,
            'points_awarded' => number_format((float) ($get('points_awarded') ?? 0), 2, '.', ''),
            'points_possible' => number_format((float) ($get('points_possible') ?? 1), 2, '.', ''),
            'evaluator_feedback' => $get('evaluator_feedback') !== null ? (string) $get('evaluator_feedback') : null,
            'evaluated_by' => ! empty($get('evaluated_by')) ? (string) $get('evaluated_by') : null,
            'evaluated_at' => $normalizeDate($get('evaluated_at')),
            'question_snapshot' => $normalizedSnapshot,
        ];

        return hash('sha256', json_encode($payload));
    }

    /**
     * Reconcile historical duplicates and quarantine conflicting groups.
     */
    protected function reconcileDuplicatesAndQuarantineConflicts(): void
    {
        $duplicateGroups = DB::table('assessment_attempt_answers')
            ->select('attempt_id', 'canonical_question_id', DB::raw('COUNT(*) as total_count'))
            ->whereNotNull('canonical_question_id')
            ->groupBy('attempt_id', 'canonical_question_id')
            ->having('total_count', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            $records = DB::table('assessment_attempt_answers')
                ->where('attempt_id', $group->attempt_id)
                ->where('canonical_question_id', $group->canonical_question_id)
                ->orderBy('created_at')
                ->get();

            if ($records->isEmpty()) {
                continue;
            }

            // Compare substantive attributes across all records in duplicate group
            $first = $records->first();
            $firstHash = $this->computeDuplicateComparisonHash($first);
            $isExactMatch = true;

            foreach ($records as $record) {
                if ($this->computeDuplicateComparisonHash($record) !== $firstHash) {
                    $isExactMatch = false;
                    break;
                }
            }

            if ($isExactMatch) {
                // Keep the earliest record; archive remaining redundant copies
                $recordsToArchive = $records->slice(1);
                foreach ($recordsToArchive as $duplicate) {
                    $this->quarantineRecord($duplicate, 'EXACT_DUPLICATE_RETRY', [
                        'retained_id' => $first->id,
                        'attempt_id' => $group->attempt_id,
                        'canonical_question_id' => $group->canonical_question_id,
                    ]);
                }
            } else {
                // Conflicting duplicate group: Quarantine ALL versions to prevent arbitrary answer selection
                foreach ($records as $conflictRecord) {
                    $this->quarantineRecord($conflictRecord, 'CONFLICTING_DUPLICATE_GROUP', [
                        'attempt_id' => $group->attempt_id,
                        'canonical_question_id' => $group->canonical_question_id,
                        'student_answer' => $conflictRecord->student_answer,
                        'points_awarded' => $conflictRecord->points_awarded,
                        'is_correct' => $conflictRecord->is_correct,
                    ]);
                }

                // Flag parent attempt for manual review without modifying historical scores or completion
                DB::table('assessment_attempts')
                    ->where('id', $group->attempt_id)
                    ->update([
                        'requires_manual_grading' => true,
                        'has_conflicting_historical_answers' => true,
                    ]);
            }
        }
    }

    /**
     * Idempotently quarantine a record into `_orphaned_attempt_answers`.
     */
    protected function quarantineRecord(object $record, string $reason, array $metadata = []): void
    {
        $existing = DB::table('_orphaned_attempt_answers')
            ->where('original_answer_id', $record->id)
            ->first();

        $sourceHash = $this->computeRecordPayloadHash($record);

        if ($existing) {
            // Verify all 17 source attributes match using deterministic SHA-256
            $existingHash = $this->computeRecordPayloadHash($existing);

            if ($existingHash !== $sourceHash) {
                throw new RuntimeException("Data integrity error: Archive record SHA-256 mismatch for original_answer_id [{$record->id}]. Active row retained.");
            }

            // Archive already has verified, bit-identical copy; safely delete active row
            DB::table('assessment_attempt_answers')->where('id', $record->id)->delete();

            return;
        }

        // Insert new archive row
        DB::table('_orphaned_attempt_answers')->insert([
            'id' => (string) Str::uuid(),
            'original_answer_id' => $record->id,
            'attempt_id' => $record->attempt_id,
            'question_id' => $record->question_id ?? null,
            'bank_question_id' => $record->bank_question_id ?? null,
            'canonical_question_id' => $record->canonical_question_id ?? null,
            'student_answer' => $record->student_answer ?? null,
            'revision' => $record->revision ?? 1,
            'last_idempotency_key' => $record->last_idempotency_key ?? null,
            'saved_at' => $record->saved_at ?? null,
            'is_correct' => $record->is_correct ?? null,
            'points_awarded' => $record->points_awarded ?? 0,
            'points_possible' => $record->points_possible ?? 1,
            'evaluator_feedback' => $record->evaluator_feedback ?? null,
            'evaluated_by' => $record->evaluated_by ?? null,
            'evaluated_at' => $record->evaluated_at ?? null,
            'question_snapshot' => $record->question_snapshot ?? null,
            'quarantine_reason' => $reason,
            'quarantine_metadata' => json_encode($metadata),
            'archived_at' => now(),
            'created_at' => $record->created_at ?? now(),
            'updated_at' => $record->updated_at ?? now(),
        ]);

        // Verify archival write by reading back and comparing full payload SHA-256
        $written = DB::table('_orphaned_attempt_answers')->where('original_answer_id', $record->id)->first();
        if (! $written) {
            throw new RuntimeException("Failed to verify archival write for original_answer_id [{$record->id}]. Active row retained.");
        }

        $writtenHash = $this->computeRecordPayloadHash($written);
        if ($sourceHash !== $writtenHash) {
            throw new RuntimeException("Data integrity error: Written archive payload SHA-256 mismatch for original_answer_id [{$record->id}]. Active row retained.");
        }

        // Delete active row only after verified match
        DB::table('assessment_attempt_answers')->where('id', $record->id)->delete();
    }

    /**
     * Crash-safe SQLite table reconstruction.
     */
    protected function reconstructSqliteTable(): void
    {
        $hasActive = Schema::hasTable('assessment_attempt_answers');
        $hasV2 = Schema::hasTable('assessment_attempt_answers_v2');

        // State C: Original table absent, replacement exists (interrupted after drop before rename)
        if (! $hasActive && $hasV2) {
            DB::statement('PRAGMA foreign_keys = OFF;');
            try {
                $fkCheck = DB::select('PRAGMA foreign_key_check;');
                if (! empty($fkCheck)) {
                    throw new RuntimeException('Foreign key integrity failure detected on assessment_attempt_answers_v2.');
                }
                Schema::rename('assessment_attempt_answers_v2', 'assessment_attempt_answers');
            } finally {
                DB::statement('PRAGMA foreign_keys = ON;');
            }

            return;
        }

        // State D: Active table already has canonical_question_id with NOT NULL and UNIQUE constraint
        if ($hasActive && ! $hasV2) {
            // Check if column exists
            if (Schema::hasColumn('assessment_attempt_answers', 'canonical_question_id')) {
                $nullCount = DB::table('assessment_attempt_answers')->whereNull('canonical_question_id')->count();
                if ($nullCount === 0) {
                    // Check if unique index exists
                    $indices = DB::select("PRAGMA index_list('assessment_attempt_answers')");
                    foreach ($indices as $idx) {
                        if ($idx->name === 'uq_attempt_canonical_question') {
                            // Reconstruction already completed successfully
                            return;
                        }
                    }
                }
            }
        }

        // State B: Both tables exist (interrupted copy or verification)
        if ($hasActive && $hasV2) {
            // Check if v2 is complete
            $activeCount = DB::table('assessment_attempt_answers')->count();
            $v2Count = DB::table('assessment_attempt_answers_v2')->count();

            if ($v2Count === $activeCount && $v2Count > 0) {
                // v2 is complete and verified
                DB::statement('PRAGMA foreign_keys = OFF;');
                try {
                    Schema::drop('assessment_attempt_answers');
                    Schema::rename('assessment_attempt_answers_v2', 'assessment_attempt_answers');
                } finally {
                    DB::statement('PRAGMA foreign_keys = ON;');
                }

                return;
            }

            // v2 is incomplete or partial; safe to drop because active table is intact
            Schema::drop('assessment_attempt_answers_v2');
        }

        // State A: Normal execution
        DB::statement('PRAGMA foreign_keys = OFF;');

        try {
            DB::transaction(function () {
                Schema::dropIfExists('assessment_attempt_answers_v2');

                Schema::create('assessment_attempt_answers_v2', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
                    $table->uuid('question_id')->nullable();
                    $table->foreignUuid('bank_question_id')->nullable()->constrained('questions')->nullOnDelete();
                    $table->string('canonical_question_id', 64); // Strict NOT NULL
                    $table->longText('student_answer')->nullable();
                    $table->unsignedInteger('revision')->default(1);
                    $table->string('last_idempotency_key', 64)->nullable();
                    $table->dateTime('saved_at')->nullable();
                    $table->boolean('is_correct')->nullable();
                    $table->decimal('points_awarded', 8, 2)->default(0);
                    $table->decimal('points_possible', 8, 2)->default(1);
                    $table->text('evaluator_feedback')->nullable();
                    $table->foreignUuid('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->dateTime('evaluated_at')->nullable();
                    $table->json('question_snapshot')->nullable();
                    $table->timestamps();

                    $table->unique(['attempt_id', 'canonical_question_id'], 'uq_attempt_canonical_question');
                    $table->index(['attempt_id', 'question_id']);
                    $table->index(['attempt_id', 'bank_question_id']);
                });

                // Copy all verified records from existing table
                DB::statement('INSERT INTO assessment_attempt_answers_v2 (
                    id, attempt_id, question_id, bank_question_id, canonical_question_id,
                    student_answer, revision, last_idempotency_key, saved_at,
                    is_correct, points_awarded, points_possible, evaluator_feedback,
                    evaluated_by, evaluated_at, question_snapshot, created_at, updated_at
                ) SELECT 
                    id, attempt_id, question_id, bank_question_id, canonical_question_id,
                    student_answer, COALESCE(revision, 1), last_idempotency_key, saved_at,
                    is_correct, points_awarded, points_possible, evaluator_feedback,
                    evaluated_by, evaluated_at, question_snapshot, created_at, updated_at
                FROM assessment_attempt_answers');

                // Verification assertions
                $activeCount = DB::table('assessment_attempt_answers')->count();
                $v2Count = DB::table('assessment_attempt_answers_v2')->count();

                if ($activeCount !== $v2Count) {
                    throw new RuntimeException("SQLite reconstruction verification failed: active count [{$activeCount}] does not match v2 count [{$v2Count}].");
                }

                $activeSum = (float) DB::table('assessment_attempt_answers')->sum('points_awarded');
                $v2Sum = (float) DB::table('assessment_attempt_answers_v2')->sum('points_awarded');

                if (abs($activeSum - $v2Sum) > 0.001) {
                    throw new RuntimeException("SQLite reconstruction verification failed: points sum mismatch [{$activeSum}] vs [{$v2Sum}].");
                }

                // Drop original and rename v2
                Schema::drop('assessment_attempt_answers');
                Schema::rename('assessment_attempt_answers_v2', 'assessment_attempt_answers');
            });

            // Run foreign key check
            $fkCheck = DB::select('PRAGMA foreign_key_check;');
            if (! empty($fkCheck)) {
                throw new RuntimeException('Foreign key integrity violation detected post-reconstruction.');
            }
        } finally {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }

    /**
     * Resumable MySQL table finalization.
     */
    protected function finalizeMysqlTable(): void
    {
        // Enforce NOT NULL on canonical_question_id
        $nullCount = DB::table('assessment_attempt_answers')->whereNull('canonical_question_id')->count();
        if ($nullCount > 0) {
            throw new RuntimeException("Cannot enforce NOT NULL on MySQL: [{$nullCount}] rows have NULL canonical_question_id.");
        }

        DB::statement('ALTER TABLE assessment_attempt_answers MODIFY canonical_question_id VARCHAR(64) NOT NULL');

        // Check if unique index exists
        $existingIndices = DB::select("SHOW INDEX FROM assessment_attempt_answers WHERE Key_name = 'uq_attempt_canonical_question'");
        if (empty($existingIndices)) {
            DB::statement('ALTER TABLE assessment_attempt_answers ADD CONSTRAINT uq_attempt_canonical_question UNIQUE (attempt_id, canonical_question_id)');
        }
    }
};
