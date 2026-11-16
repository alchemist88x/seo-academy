<?php

namespace App\Http\Controllers;

use App\Domain\Certificates\Models\Certificate;

class CertificateVerificationController extends Controller
{
    public function show(string $code)
    {
        $hashedCode = hash('sha256', $code);

        // Public verification strictly requires an opaque, unguessable token or its SHA-256 hash.
        // Sequential certificate numbers and internal IDs must never act as authentication credentials.
        $certificate = Certificate::query()
            ->with(['course.academy', 'user'])
            ->where('verification_code', $code)
            ->orWhere('verification_token_hash', $code)
            ->orWhere('verification_token_hash', $hashedCode)
            ->first();

        $verificationStatus = 'not_found';
        $publicData = null;

        if ($certificate !== null) {
            if ($certificate->isUnderAudit()) {
                $verificationStatus = 'under_audit';
            } elseif ($certificate->isSuperseded()) {
                $verificationStatus = 'superseded';
            } elseif ($certificate->isRevoked()) {
                $verificationStatus = 'revoked';
            } elseif ($certificate->isExpired()) {
                $verificationStatus = 'expired';
            } elseif ($certificate->isReinstated()) {
                $verificationStatus = 'reinstated';
            } elseif ($certificate->isIssued()) {
                $verificationStatus = 'valid';
            } else {
                $verificationStatus = 'invalid';
            }

            $snapshot = $certificate->issuance_snapshot;
            $rawName = is_array($snapshot) && isset($snapshot['student']['name'])
                ? $snapshot['student']['name']
                : $certificate->user?->name;

            // Student privacy policy: masked by default unless explicit consent is recorded
            $hasConsent = (bool) $certificate->public_identity_consent;
            $displayName = $hasConsent ? ($rawName ?: 'Verified Student') : Certificate::maskName($rawName);

            $courseTitle = is_array($snapshot) && isset($snapshot['course']['title'])
                ? $snapshot['course']['title']
                : ($certificate->course?->title ?? 'Academy Course');

            $academyName = is_array($snapshot) && isset($snapshot['academy']['name'])
                ? $snapshot['academy']['name']
                : ($certificate->course?->academy?->name ?? config('app.name', 'SEO Academy'));

            $publicData = [
                'status' => $verificationStatus,
                'certificate_number' => $certificate->certificate_number,
                'student_name' => $displayName,
                'masked_student_name' => Certificate::maskName($rawName),
                'is_name_masked' => ! $hasConsent,
                'course_title' => $courseTitle,
                'academy_name' => $academyName,
                'issued_at' => $certificate->issued_at?->format('F d, Y'),
                'revoked_at' => $certificate->revoked_at?->format('F d, Y'),
                'reinstated_at' => ($certificate->reinstated_at ?? $certificate->updated_at)?->format('F d, Y'),
                'expires_at' => $certificate->expires_at?->format('F d, Y'),
                'version' => $certificate->version ?? 1,
            ];
        }

        return view('certificates.verify', [
            'code' => $code,
            'certificate' => $certificate, // Kept for blade compatibility where checked
            'verificationStatus' => $verificationStatus,
            'publicData' => $publicData,
        ]);
    }
}
