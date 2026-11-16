<?php

namespace App\Domain\Certificates\Models;

use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Certificate extends Model
{
    use HasUuids;

    public const STATUS_ISSUED = 'Issued';

    public const STATUS_REVOKED = 'Revoked';

    public const STATUS_UNDER_AUDIT = 'Under Audit';

    public const STATUS_REINSTATED = 'Reinstated';

    public const STATUS_SUPERSEDED = 'Superseded';

    public const STATUS_EXPIRED = 'Expired';

    protected $fillable = [
        'version',
        'supersedes_id',
        'superseded_by_id',
        'user_id',
        'course_id',
        'enrollment_id',
        'original_enrollment_id',
        'issued_at',
        'expires_at',
        'certificate_number',
        'verification_code',
        'encrypted_verification_code',
        'verification_token_hash',
        'template',
        'issuance_snapshot',
        'pdf_path',
        'pdf_generation_status',
        'pdf_generated_at',
        'pdf_generation_error',
        'status',
        'requires_audit',
        'audit_reason',
        'audited_at',
        'audited_by',
        'audit_notes',
        'reinstated_at',
        'reinstated_by',
        'reinstatement_reason',
        'reinstatement_notes',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'public_identity_consent',
        'consent_recorded_at',
        'consent_withdrawn_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'issued_at' => 'date',
            'expires_at' => 'datetime',
            'issuance_snapshot' => 'array',
            'public_identity_consent' => 'boolean',
            'consent_recorded_at' => 'datetime',
            'consent_withdrawn_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
            'requires_audit' => 'boolean',
            'audited_at' => 'datetime',
            'reinstated_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'encrypted_verification_code',
        'verification_code',
    ];

    public function getVerificationCodeAttribute(): ?string
    {
        return $this->getDecryptedVerificationCode();
    }

    public function setVerificationCodeAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['verification_code'] = null;
            $this->attributes['encrypted_verification_code'] = null;

            return;
        }

        // Authenticated encryption at rest
        $this->attributes['encrypted_verification_code'] = Crypt::encryptString($value);
        // Clear plaintext column at rest
        $this->attributes['verification_code'] = null;
        // Maintain SHA-256 hash for fast indexed verification lookup
        $this->attributes['verification_token_hash'] = hash('sha256', $value);
    }

    public function getDecryptedVerificationCode(): ?string
    {
        if (! empty($this->attributes['encrypted_verification_code'])) {
            try {
                return Crypt::decryptString($this->attributes['encrypted_verification_code']);
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->attributes['verification_code'] ?? null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate) {
            if ($certificate->original_enrollment_id === null && $certificate->enrollment_id !== null) {
                $certificate->original_enrollment_id = $certificate->enrollment_id;
            }
        });
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'original_enrollment_id');
    }

    public function activeEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }

    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function auditedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by');
    }

    public function reinstatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reinstated_by');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(CertificateAuditEvent::class, 'certificate_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function isIssued(): bool
    {
        return strtolower((string) $this->status) === 'issued'
            && $this->revoked_at === null
            && ! $this->isUnderAudit()
            && ! $this->isSuperseded()
            && ! $this->isExpired();
    }

    public function isRevoked(): bool
    {
        return (strtolower((string) $this->status) === 'revoked' || $this->revoked_at !== null)
            && ! $this->isReinstated()
            && ! $this->isSuperseded();
    }

    public function isUnderAudit(): bool
    {
        return strtolower((string) $this->status) === 'under audit'
            || (bool) ($this->requires_audit ?? false);
    }

    public function isReinstated(): bool
    {
        return strtolower((string) $this->status) === 'reinstated';
    }

    public function isSuperseded(): bool
    {
        return strtolower((string) $this->status) === 'superseded'
            || $this->superseded_by_id !== null;
    }

    public function isExpired(): bool
    {
        return strtolower((string) $this->status) === 'expired'
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    public function getMaskedStudentNameAttribute(): string
    {
        return self::maskName($this->user?->name);
    }

    public static function maskName(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return 'Verified Student';
        }

        $parts = preg_split('/\s+/', trim($name));
        $maskedParts = [];

        foreach ($parts as $part) {
            $len = mb_strlen($part);
            if ($len <= 1) {
                $maskedParts[] = $part.'***';
            } elseif ($len === 2) {
                $maskedParts[] = mb_substr($part, 0, 1).'*';
            } else {
                $maskedParts[] = mb_substr($part, 0, 1).str_repeat('*', min(4, $len - 1));
            }
        }

        return implode(' ', $maskedParts);
    }
}
