<?php

namespace App\Http\Controllers;

use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificatePdfService;
use Illuminate\Http\Request;

class CertificateDownloadController extends Controller
{
    public function __construct(
        private readonly CertificatePdfService $pdfService,
    ) {}

    public function download(Request $request, Certificate $certificate)
    {
        $user = $request->user();

        // Authorization:
        // 1. Admin can access any certificate
        // 2. Authorized Trainer (assigned to this certificate's course) can access
        // 3. Student who owns the certificate can access
        // Support staff or other users cannot access unless explicitly authorized
        $canAccess = false;
        if ($user) {
            if ($user->hasRole('admin')) {
                $canAccess = true;
            } elseif ($user->hasRole('trainer')) {
                $course = $certificate->course;
                if ($course && $course->trainers()->where('users.id', $user->getKey())->exists()) {
                    $canAccess = true;
                }
            } elseif ($certificate->user_id === $user->id) {
                $canAccess = true;
            }
        }

        if (! $canAccess) {
            abort(403, 'Unauthorized access to this certificate.');
        }

        if ($certificate->isRevoked()) {
            abort(403, 'This certificate has been revoked and cannot be downloaded as a valid credential.');
        }

        if ($certificate->isUnderAudit()) {
            abort(403, 'This certificate is currently under academic review and cannot be downloaded.');
        }

        if ($certificate->isSuperseded()) {
            abort(403, 'This certificate version has been superseded by a reissued credential and cannot be downloaded as an active credential.');
        }

        // Generate or get existing PDF content
        $pdfContent = $this->pdfService->getPdfBinary($certificate);
        $filename = 'Certificate-'.$certificate->certificate_number.'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
