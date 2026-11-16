<?php

namespace App\Domain\Certificates\Models;

use App\Domain\Grading\Concerns\ImmutableRecordTrait;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateAuditEvent extends Model
{
    use HasUuids, ImmutableRecordTrait;

    public $timestamps = false;

    protected $fillable = [
        'certificate_id',
        'event_type',
        'actor_id',
        'reason',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
