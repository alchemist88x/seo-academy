<?php

namespace App\Domain\Grading\Models;

use App\Domain\Grading\Concerns\ImmutableRecordTrait;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentResultPublicationEvent extends Model
{
    use HasUuids, ImmutableRecordTrait;

    public $timestamps = false;

    protected $fillable = [
        'enrollment_id',
        'assessment_type',
        'assessment_id',
        'raw_score',
        'scaled_score',
        'is_passing',
        'published_by',
        'publication_metadata',
        'published_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_score' => 'decimal:2',
            'scaled_score' => 'decimal:2',
            'is_passing' => 'boolean',
            'publication_metadata' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
