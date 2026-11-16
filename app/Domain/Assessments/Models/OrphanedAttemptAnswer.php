<?php

namespace App\Domain\Assessments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrphanedAttemptAnswer extends Model
{
    use HasUuids;

    protected $table = '_orphaned_attempt_answers';

    protected $fillable = [
        'original_answer_id',
        'attempt_id',
        'question_id',
        'bank_question_id',
        'canonical_question_id',
        'student_answer',
        'revision',
        'last_idempotency_key',
        'saved_at',
        'is_correct',
        'points_awarded',
        'points_possible',
        'evaluator_feedback',
        'evaluated_by',
        'evaluated_at',
        'question_snapshot',
        'quarantine_reason',
        'quarantine_metadata',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'is_correct' => 'boolean',
            'points_awarded' => 'decimal:2',
            'points_possible' => 'decimal:2',
            'saved_at' => 'datetime',
            'evaluated_at' => 'datetime',
            'archived_at' => 'datetime',
            'question_snapshot' => 'array',
            'quarantine_metadata' => 'array',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'attempt_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
