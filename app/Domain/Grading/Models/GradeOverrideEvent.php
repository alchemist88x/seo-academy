<?php

namespace App\Domain\Grading\Models;

use App\Domain\Grading\Concerns\ImmutableRecordTrait;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeOverrideEvent extends Model
{
    use HasUuids, ImmutableRecordTrait;

    public $timestamps = false;

    protected $fillable = [
        'sequence_number',
        'previous_event_id',
        'reversal_of_event_id',
        'enrollment_id',
        'assessment_type',
        'assessment_id',
        'previous_score',
        'new_score',
        'effective_score',
        'reason',
        'overridden_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'previous_score' => 'decimal:2',
            'new_score' => 'decimal:2',
            'effective_score' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    public function previousEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_event_id');
    }

    public function reversalOfEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_event_id');
    }
}
