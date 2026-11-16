<?php

namespace App\Domain\Certificates\Services;

use App\Domain\Certificates\Jobs\GenerateCertificatePdfJob;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Models\CertificateAuditEvent;
use App\Domain\Grading\Services\GradebookCalculationService;
use App\Domain\Notifications\Services\NotificationDispatcherService;
use App\Domain\Platform\Models\Setting;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CertificateIssuanceService
{
    public function __construct(
        private readonly ?CertificatePdfService $pdfService = null,
    ) {}

    /**
     * Issue a certificate for a completed enrollment.
     *
     * Principles:
     * - Certificates are only issued for completed enrollments.
     * - Strictly idempotent: if an issued certificate already exists for this enrollment (or user + course), return it.
     * - Generates unique certificate number and unguessable verification code.
     *
     * @throws InvalidArgumentException
     */
    public function issueCertificate(Enrollment $enrollment, string $template = 'classic'): Certificate
    {
        if (! $enrollment->isCompleted()) {
            throw new InvalidArgumentException('Certificates can only be issued for completed enrollments.');
        }

        // Mandatory Policy 2: Certificate eligibility requires course completion AND a published passing grade
        $course = $enrollment->course;
        if ($course && $course->active_grading_scheme_id !== null) {
            $currentGrade = $enrollment->currentGrade;
            $publishedGrade = ($currentGrade && $currentGrade->status === 'published')
                ? $currentGrade->publishedGrade
                : null;

            if (! $publishedGrade || ! (bool) $publishedGrade->is_passing) {
                throw new InvalidArgumentException('Certificate eligibility requires course completion AND a published passing grade.');
            }
        }

        $studentProfile = $enrollment->studentProfile;
        if (! $studentProfile || ! $studentProfile->user_id) {
            throw new InvalidArgumentException('Enrollment must be associated with a valid student user.');
        }

        $userId = $studentProfile->user_id;
        $courseId = $enrollment->course_id;

        try {
            return DB::transaction(function () use ($enrollment, $userId, $courseId, $template, $studentProfile, $course) {
                // If a certificate already exists for this enrollment (issued or revoked), return it
                // Revoked certificates cannot be silently reissued or overwritten.
                $existing = Certificate::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                // Fallback check for user + course where enrollment_id is null (historical record) or matches
                $existingLegacy = Certificate::query()
                    ->where('user_id', $userId)
                    ->where('course_id', $courseId)
                    ->first();

                if ($existingLegacy !== null) {
                    // If it doesn't have an enrollment_id, link it now
                    if ($existingLegacy->enrollment_id === null) {
                        $existingLegacy->enrollment_id = $enrollment->id;
                        $existingLegacy->save();
                    }

                    return $existingLegacy;
                }

                $certificateNumber = $this->generateUniqueCertificateNumber();
                $verificationCode = $this->generateUniqueVerificationCode();
                $verificationTokenHash = hash('sha256', $verificationCode);

                $studentUser = $studentProfile->user;
                $currentGrade = $enrollment->currentGrade;
                $publishedGrade = ($currentGrade && $currentGrade->status === 'published')
                    ? $currentGrade->publishedGrade
                    : null;
                $academy = $course?->academy;

                $signatoryName = Setting::query()
                    ->where('academy_id', $academy?->id)
                    ->where('group', 'certificates')
                    ->where('key', 'signatory_name')
                    ->value('value') ?: ($academy?->name ? $academy->name.' Director' : 'Academic Director');

                $signatoryTitle = Setting::query()
                    ->where('academy_id', $academy?->id)
                    ->where('group', 'certificates')
                    ->where('key', 'signatory_title')
                    ->value('value') ?: 'Lead Instructor & Director';

                $issuanceSnapshot = [
                    'student' => [
                        'id' => $userId,
                        'name' => $studentUser?->name ?? 'Student',
                        'email' => $studentUser?->email,
                        'code' => $studentProfile->student_code,
                    ],
                    'course' => [
                        'id' => $courseId,
                        'title' => $course?->title ?? 'Course',
                        'slug' => $course?->slug,
                    ],
                    'academy' => [
                        'id' => $academy?->id,
                        'name' => $academy?->name ?? config('app.name', 'SEO Academy'),
                    ],
                    'academic' => [
                        'final_score' => $publishedGrade ? (string) $publishedGrade->final_score : null,
                        'letter_grade' => $publishedGrade?->letter_grade,
                        'published_grade_id' => $publishedGrade?->id,
                        'is_passing' => $publishedGrade ? (bool) $publishedGrade->is_passing : true,
                    ],
                    'branding' => [
                        'template' => $template,
                        'signatory_name' => $signatoryName,
                        'signatory_title' => $signatoryTitle,
                    ],
                    'issued_at' => Carbon::now()->toDateString(),
                ];

                $certificate = Certificate::create([
                    'version' => 1,
                    'user_id' => $userId,
                    'course_id' => $courseId,
                    'enrollment_id' => $enrollment->id,
                    'original_enrollment_id' => $enrollment->id,
                    'issued_at' => Carbon::now()->toDateString(),
                    'certificate_number' => $certificateNumber,
                    'verification_code' => $verificationCode,
                    'verification_token_hash' => $verificationTokenHash,
                    'template' => $template,
                    'issuance_snapshot' => $issuanceSnapshot,
                    'pdf_generation_status' => 'pending',
                    'status' => Certificate::STATUS_ISSUED,
                    'public_identity_consent' => false,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                CertificateAuditEvent::create([
                    'certificate_id' => $certificate->id,
                    'event_type' => 'issued',
                    'actor_id' => $userId,
                    'reason' => 'Certificate officially issued upon curriculum completion and academic passing.',
                    'created_at' => now(),
                ]);

                DB::afterCommit(function () use ($certificate, $studentUser, $course, $certificateNumber) {
                    GenerateCertificatePdfJob::dispatch($certificate->id);

                    try {
                        $notificationService = app(NotificationDispatcherService::class);
                        $notificationService->send(
                            recipient: $studentUser,
                            category: 'academic',
                            title: 'Certificate Issued: '.($course?->title ?? 'Course'),
                            message: "Congratulations! Your certificate {$certificateNumber} has been officially issued.",
                            link: route('student.certificates.index'),
                            dedupKey: 'cert_issued_'.$certificate->id,
                            dedupWindowSeconds: 86400,
                        );
                    } catch (\Throwable) {
                    }
                });

                return $certificate;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Simultaneous issuance race condition: another concurrent process won the insert
            // Retrieve and return the existing certificate for that enrollment
            return $this->resolveExistingCertificateAfterDuplicateKeyConflict($enrollment, $userId, $courseId, $e);
        } catch (QueryException $e) {
            // Determine if the QueryException is genuinely a duplicate-key/unique-constraint violation.
            // Never treat SQLSTATE '23000' alone as proof of duplicate key, as 23000 also covers
            // FOREIGN KEY violations, NOT NULL violations, and CHECK constraint failures.
            if ($this->isDuplicateKeyException($e)) {
                return $this->resolveExistingCertificateAfterDuplicateKeyConflict($enrollment, $userId, $courseId, $e);
            }

            // Rethrow all unrelated query exceptions (including foreign key or not-null violations)
            throw $e;
        }
    }

    /**
     * Determine if a QueryException is specifically a unique key / duplicate entry violation.
     */
    private function isDuplicateKeyException(QueryException $e): bool
    {
        $errorCode = $e->errorInfo[1] ?? null;
        $message = $e->getMessage();

        // MySQL: error code 1062 is specifically ER_DUP_ENTRY
        if ($errorCode === 1062 || str_contains($message, 'Duplicate entry')) {
            return true;
        }

        // SQLite: error code 19 or 2067 (SQLITE_CONSTRAINT_UNIQUE) accompanied by "UNIQUE constraint failed"
        if (($errorCode === 19 || $errorCode === 2067) && str_contains($message, 'UNIQUE constraint failed')) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve the existing certificate for an enrollment after a confirmed duplicate key conflict.
     *
     * @throws \Throwable
     */
    private function resolveExistingCertificateAfterDuplicateKeyConflict(
        Enrollment $enrollment,
        string $userId,
        string $courseId,
        \Throwable $originalException
    ): Certificate {
        $existing = Certificate::query()
            ->where('enrollment_id', $enrollment->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $existingLegacy = Certificate::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if ($existingLegacy !== null) {
            return $existingLegacy;
        }

        throw $originalException;
    }

    /**
     * Revoke an issued certificate.
     */
    public function revokeCertificate(Certificate $certificate, ?User $revokedBy, string $reason): Certificate
    {
        if ($revokedBy !== null && (! $revokedBy->is_active || ($revokedBy->role !== 'admin' && ! $revokedBy->hasRole('admin')))) {
            throw new AuthorizationException('Unauthorized: Only administrators may revoke certificates.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A mandatory reason is required to revoke a certificate.');
        }

        if ($certificate->isRevoked()) {
            return $certificate;
        }

        $certificate->update([
            'status' => Certificate::STATUS_REVOKED,
            'revoked_at' => Carbon::now(),
            'revoked_by' => $revokedBy?->id,
            'revocation_reason' => $reason,
        ]);

        CertificateAuditEvent::create([
            'certificate_id' => $certificate->id,
            'event_type' => 'revoked',
            'actor_id' => $revokedBy?->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        return $certificate->fresh();
    }

    /**
     * Reinstate a revoked certificate after administrative review.
     * Enforces administrator authorization.
     *
     * @throws AuthorizationException
     */
    public function reinstateCertificate(Certificate $certificate, ?User $reinstatedBy, ?string $reason = null): Certificate
    {
        if ($reinstatedBy !== null && (! $reinstatedBy->is_active || ($reinstatedBy->role !== 'admin' && ! $reinstatedBy->hasRole('admin')))) {
            throw new AuthorizationException('Unauthorized: Only administrators may reinstate certificates.');
        }

        $certificate->update([
            'status' => Certificate::STATUS_REINSTATED,
            'revoked_at' => null,
            'revoked_by' => null,
            'revocation_reason' => null,
            'reinstated_at' => Carbon::now(),
            'reinstated_by' => $reinstatedBy?->id,
            'reinstatement_reason' => $reason,
        ]);

        CertificateAuditEvent::create([
            'certificate_id' => $certificate->id,
            'event_type' => 'reinstated',
            'actor_id' => $reinstatedBy?->id,
            'reason' => $reason ?? 'Certificate reinstated after administrative review.',
            'created_at' => now(),
        ]);

        return $certificate->fresh();
    }

    /**
     * Regenerate certificate PDF (or re-issue with updated template/branding).
     */
    public function regenerateCertificate(Certificate $certificate, ?string $newTemplate = null): Certificate
    {
        if ($certificate->isRevoked()) {
            throw new InvalidArgumentException('Cannot regenerate a revoked certificate.');
        }

        if ($newTemplate !== null) {
            $certificate->template = $newTemplate;
        }

        // Delete old PDF file if it exists
        if ($certificate->pdf_path && $this->pdfService) {
            $this->pdfService->deletePdfFile($certificate);
            $certificate->pdf_path = null;
        }

        $certificate->save();

        if ($this->pdfService) {
            $this->pdfService->generatePdf($certificate, true);
        }

        return $certificate->fresh();
    }

    /**
     * Flag a certificate for academic audit (e.g. following a grade recalculation or dispute).
     */
    public function flagForAudit(Certificate $certificate, ?User $actor, string $reason): Certificate
    {
        return DB::transaction(function () use ($certificate, $actor, $reason) {
            $certificate->update([
                'status' => Certificate::STATUS_UNDER_AUDIT,
                'requires_audit' => true,
                'audit_reason' => $reason,
                'audited_at' => now(),
                'audited_by' => $actor?->id,
            ]);

            CertificateAuditEvent::create([
                'certificate_id' => $certificate->id,
                'event_type' => 'audit_flagged',
                'actor_id' => $actor?->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            return $certificate->fresh();
        });
    }

    /**
     * Resolve an academic audit on a certificate.
     * Enforces Mandatory Policy 6: ONLY administrators may resolve certificate audits.
     *
     * @throws AuthorizationException
     */
    public function resolveAudit(
        Certificate $certificate,
        User $actor,
        string $resolution,
        string $notes
    ): Certificate {
        if (! $actor->is_active || ($actor->role !== 'admin' && ! $actor->hasRole('admin'))) {
            throw new AuthorizationException('Unauthorized: Only administrators may resolve certificate audits.');
        }

        return DB::transaction(function () use ($certificate, $actor, $resolution, $notes) {
            $newStatus = match ($resolution) {
                'cleared' => Certificate::STATUS_ISSUED,
                'revoked' => Certificate::STATUS_REVOKED,
                'reinstated' => Certificate::STATUS_REINSTATED,
                default => throw new InvalidArgumentException("Invalid audit resolution: '{$resolution}'"),
            };

            $updateData = [
                'status' => $newStatus,
                'requires_audit' => false,
                'audit_reason' => null,
                'audited_at' => now(),
                'audited_by' => $actor->id,
            ];

            if ($resolution === 'reinstated') {
                $updateData['reinstated_at'] = now();
                $updateData['reinstated_by'] = $actor->id;
                $updateData['reinstatement_reason'] = $notes;
            } elseif ($resolution === 'revoked') {
                $updateData['revoked_at'] = now();
                $updateData['revoked_by'] = $actor->id;
                $updateData['revocation_reason'] = $notes;
            }

            $certificate->update($updateData);

            CertificateAuditEvent::create([
                'certificate_id' => $certificate->id,
                'event_type' => 'audit_resolved',
                'actor_id' => $actor->id,
                'reason' => "Resolution: {$resolution}. Notes: {$notes}",
                'created_at' => now(),
            ]);

            return $certificate->fresh();
        });
    }

    /**
     * Retrieve the certificate for an enrollment if issued.
     */
    public function getCertificateForEnrollment(Enrollment $enrollment): ?Certificate
    {
        $byEnrollment = Certificate::query()
            ->where('enrollment_id', $enrollment->id)
            ->first();

        if ($byEnrollment !== null) {
            return $byEnrollment;
        }

        $byOriginal = Certificate::query()
            ->where('original_enrollment_id', $enrollment->id)
            ->whereNull('superseded_by_id')
            ->where('status', '!=', Certificate::STATUS_SUPERSEDED)
            ->first();

        if ($byOriginal !== null) {
            return $byOriginal;
        }

        $studentProfile = $enrollment->studentProfile;
        if (! $studentProfile || ! $studentProfile->user_id) {
            return null;
        }

        return Certificate::query()
            ->where('user_id', $studentProfile->user_id)
            ->where('course_id', $enrollment->course_id)
            ->whereNull('superseded_by_id')
            ->where('status', '!=', Certificate::STATUS_SUPERSEDED)
            ->first();
    }

    /**
     * Retrieve complete certificate version history for an enrollment.
     *
     * @return Collection<int, Certificate>
     */
    public function getCertificateHistoryForEnrollment(Enrollment $enrollment): Collection
    {
        return Certificate::query()
            ->where('original_enrollment_id', $enrollment->id)
            ->orderBy('version', 'desc')
            ->get();
    }

    /**
     * Reissue a certificate, creating a new version and superseding the previous certificate.
     * Enforces administrator authorization, academic eligibility, and audit preservation.
     *
     * @throws AuthorizationException|InvalidArgumentException
     */
    public function reissueCertificate(
        Certificate $oldCertificate,
        User $actor,
        string $reason,
        ?string $newTemplate = null
    ): Certificate {
        if (! $actor->is_active || ($actor->role !== 'admin' && ! $actor->hasRole('admin'))) {
            throw new AuthorizationException('Unauthorized: Only administrators may reissue certificates.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A mandatory reason is required to reissue a certificate.');
        }

        $enrollment = $oldCertificate->enrollment;
        if (! $enrollment) {
            throw new InvalidArgumentException('Cannot reissue certificate: associated enrollment record not found.');
        }

        // Academic eligibility check: must satisfy Policy 1 & 2
        $calcService = app(GradebookCalculationService::class);
        if (! $calcService->isEligibleForCertificate($enrollment)) {
            throw new InvalidArgumentException('Cannot reissue certificate: enrollment is not academically eligible.');
        }

        return DB::transaction(function () use ($oldCertificate, $actor, $reason, $newTemplate, $enrollment) {
            // Mark old certificate as superseded and release the unique enrollment constraint
            $oldCertificate->update([
                'status' => Certificate::STATUS_SUPERSEDED,
                'enrollment_id' => null,
            ]);

            $newCertNumber = $this->generateUniqueCertificateNumber();
            $newVerificationCode = $this->generateUniqueVerificationCode();
            $newVerificationHash = hash('sha256', $newVerificationCode);
            $template = $newTemplate ?: $oldCertificate->template ?: 'classic';

            $course = $enrollment->course;
            $studentProfile = $enrollment->studentProfile;
            $studentUser = $studentProfile?->user;
            $academy = $course?->academy;
            $currentGrade = $enrollment->currentGrade;
            $publishedGrade = ($currentGrade && $currentGrade->status === 'published')
                ? $currentGrade->publishedGrade
                : null;

            $signatoryName = Setting::query()
                ->where('academy_id', $academy?->id)
                ->where('group', 'certificates')
                ->where('key', 'signatory_name')
                ->value('value') ?: ($academy?->name ? $academy->name.' Director' : 'Academic Director');

            $signatoryTitle = Setting::query()
                ->where('academy_id', $academy?->id)
                ->where('group', 'certificates')
                ->where('key', 'signatory_title')
                ->value('value') ?: 'Lead Instructor & Director';

            $issuanceSnapshot = [
                'student' => [
                    'id' => $oldCertificate->user_id,
                    'name' => $studentUser?->name ?? 'Student',
                    'email' => $studentUser?->email,
                    'code' => $studentProfile?->student_code,
                ],
                'course' => [
                    'id' => $oldCertificate->course_id,
                    'title' => $course?->title ?? 'Course',
                    'slug' => $course?->slug,
                ],
                'academy' => [
                    'id' => $academy?->id,
                    'name' => $academy?->name ?? config('app.name', 'SEO Academy'),
                ],
                'academic' => [
                    'final_score' => $publishedGrade ? (string) $publishedGrade->final_score : null,
                    'letter_grade' => $publishedGrade?->letter_grade,
                    'published_grade_id' => $publishedGrade?->id,
                    'is_passing' => $publishedGrade ? (bool) $publishedGrade->is_passing : true,
                ],
                'branding' => [
                    'template' => $template,
                    'signatory_name' => $signatoryName,
                    'signatory_title' => $signatoryTitle,
                ],
                'issued_at' => Carbon::now()->toDateString(),
            ];

            $newCertificate = Certificate::create([
                'version' => ($oldCertificate->version ?? 1) + 1,
                'supersedes_id' => $oldCertificate->id,
                'user_id' => $oldCertificate->user_id,
                'course_id' => $oldCertificate->course_id,
                'enrollment_id' => $enrollment->id,
                'original_enrollment_id' => $enrollment->id,
                'issued_at' => Carbon::now()->toDateString(),
                'certificate_number' => $newCertNumber,
                'verification_code' => $newVerificationCode,
                'verification_token_hash' => $newVerificationHash,
                'template' => $template,
                'issuance_snapshot' => $issuanceSnapshot,
                'pdf_generation_status' => 'pending',
                'status' => Certificate::STATUS_ISSUED,
                'public_identity_consent' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $oldCertificate->update([
                'superseded_by_id' => $newCertificate->id,
            ]);

            CertificateAuditEvent::create([
                'certificate_id' => $oldCertificate->id,
                'event_type' => 'superseded',
                'actor_id' => $actor->id,
                'reason' => "Superseded by {$newCertNumber}. Reason: {$reason}",
                'created_at' => now(),
            ]);

            CertificateAuditEvent::create([
                'certificate_id' => $newCertificate->id,
                'event_type' => 'reissued',
                'actor_id' => $actor->id,
                'reason' => "Reissued from {$oldCertificate->certificate_number}. Reason: {$reason}",
                'created_at' => now(),
            ]);

            DB::afterCommit(function () use ($newCertificate, $studentUser, $course, $newCertNumber) {
                GenerateCertificatePdfJob::dispatch($newCertificate->id);

                try {
                    $notificationService = app(NotificationDispatcherService::class);
                    $notificationService->send(
                        recipient: $studentUser,
                        category: 'academic',
                        title: 'Certificate Reissued: '.($course?->title ?? 'Course'),
                        message: "Your certificate has been reissued as {$newCertNumber}.",
                        link: route('student.certificates.index'),
                        dedupKey: 'cert_reissued_'.$newCertificate->id,
                        dedupWindowSeconds: 86400,
                    );
                } catch (\Throwable) {
                }
            });

            return $newCertificate;
        });
    }

    /**
     * Record or withdraw explicit student consent for public name disclosure.
     *
     * @throws AuthorizationException
     */
    public function setPublicIdentityConsent(Certificate $certificate, User $actor, bool $consent): Certificate
    {
        $isOwner = $certificate->user_id === $actor->id;
        $isAdmin = $actor->role === 'admin' || $actor->hasRole('admin');
        if (! $isOwner && ! $isAdmin) {
            throw new AuthorizationException('Unauthorized: You can only manage consent for your own certificates.');
        }

        $updateData = [
            'public_identity_consent' => $consent,
        ];
        if ($consent) {
            $updateData['consent_recorded_at'] = now();
        } else {
            $updateData['consent_withdrawn_at'] = now();
        }

        $certificate->update($updateData);

        CertificateAuditEvent::create([
            'certificate_id' => $certificate->id,
            'event_type' => $consent ? 'consent_granted' : 'consent_withdrawn',
            'actor_id' => $actor->id,
            'reason' => $consent ? 'Student consented to public name display.' : 'Student withdrew consent for public name display.',
            'created_at' => now(),
        ]);

        return $certificate->fresh();
    }

    /**
     * Retry failed or pending certificate PDF generation.
     *
     * @throws AuthorizationException
     */
    public function retryPdfGeneration(Certificate $certificate, User $actor): Certificate
    {
        $isAdmin = $actor->role === 'admin' || $actor->hasRole('admin');
        $isAssignedTrainer = false;
        if ($actor->role === 'trainer' || $actor->hasRole('trainer')) {
            $isAssignedTrainer = $certificate->course?->trainers()->where('users.id', $actor->id)->exists();
        }

        if (! $isAdmin && ! $isAssignedTrainer) {
            throw new AuthorizationException('Unauthorized to retry PDF generation.');
        }

        $certificate->update([
            'pdf_generation_status' => 'pending',
            'pdf_generation_error' => null,
        ]);

        GenerateCertificatePdfJob::dispatch($certificate->id, true);

        return $certificate->fresh();
    }

    private function generateUniqueCertificateNumber(): string
    {
        $prefix = 'CERT-'.date('Y');

        do {
            $candidate = $prefix.'-'.strtoupper(Str::random(6));
        } while (Certificate::query()->where('certificate_number', $candidate)->exists());

        return $candidate;
    }

    private function generateUniqueVerificationCode(): string
    {
        do {
            $candidate = Str::random(32);
        } while (Certificate::query()->where('verification_code', $candidate)->exists());

        return $candidate;
    }
}
