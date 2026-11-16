<?php

namespace App\Domain\Certificates\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Models\Setting;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class CertificatePdfService
{
    public const DEFAULT_DISK = 'local';

    public const STORAGE_FOLDER = 'certificates';

    public function __construct(
        private readonly ?string $disk = null,
    ) {}

    public function getStorageDisk(): string
    {
        return $this->disk ?? config('filesystems.default', self::DEFAULT_DISK);
    }

    /**
     * Get or build certificate template configuration.
     * Supported templates: 'classic', 'modern', 'minimalist'
     */
    public function getTemplateConfig(string $template = 'classic', ?Certificate $certificate = null): array
    {
        $academy = $certificate?->course?->academy;
        $academyId = $academy?->id;

        // Fetch custom template settings if stored in settings table
        $settingSignatoryName = Setting::query()
            ->where('academy_id', $academyId)
            ->where('group', 'certificates')
            ->where('key', 'signatory_name')
            ->value('value');

        $settingSignatoryTitle = Setting::query()
            ->where('academy_id', $academyId)
            ->where('group', 'certificates')
            ->where('key', 'signatory_title')
            ->value('value');

        $settingDefaultTemplate = Setting::query()
            ->where('academy_id', $academyId)
            ->where('group', 'certificates')
            ->where('key', 'default_template')
            ->value('value');

        $effectiveTemplate = $template ?: ($settingDefaultTemplate ?: 'classic');

        $configs = [
            'classic' => [
                'name' => 'Classic Certificate',
                'view' => 'certificates.templates.classic',
                'font_family' => 'Georgia, serif',
                'border_style' => 'double',
                'border_color' => '#1e3a8a',
                'header_color' => '#1e3a8a',
                'accent_color' => '#d97706',
            ],
            'modern' => [
                'name' => 'Modern Minimalist',
                'view' => 'certificates.templates.modern',
                'font_family' => 'Helvetica, Arial, sans-serif',
                'border_style' => 'solid',
                'border_color' => '#0f172a',
                'header_color' => '#0f172a',
                'accent_color' => '#2563eb',
            ],
            'minimalist' => [
                'name' => 'Clean & Minimal',
                'view' => 'certificates.templates.minimalist',
                'font_family' => 'Arial, sans-serif',
                'border_style' => 'none',
                'border_color' => '#e2e8f0',
                'header_color' => '#334155',
                'accent_color' => '#059669',
            ],
        ];

        $selectedConfig = $configs[$effectiveTemplate] ?? $configs['classic'];

        $selectedConfig['signatory_name'] = $settingSignatoryName ?: ($academy?->name ? $academy->name.' Director' : 'Academic Director');
        $selectedConfig['signatory_title'] = $settingSignatoryTitle ?: 'Lead Instructor & Director';
        $selectedConfig['template_key'] = $effectiveTemplate;

        return $selectedConfig;
    }

    /**
     * Generate and store PDF for a certificate.
     */
    public function generatePdf(Certificate $certificate, bool $forceRegenerate = false): string
    {
        $disk = Storage::disk($this->getStorageDisk());
        $filename = self::STORAGE_FOLDER.'/'.$certificate->certificate_number.'.pdf';

        if (! $forceRegenerate && $certificate->pdf_path && $disk->exists($certificate->pdf_path)) {
            return $certificate->pdf_path;
        }

        $pdfBinary = $this->renderPdfBinary($certificate);

        $disk->put($filename, $pdfBinary);

        $certificate->pdf_path = $filename;
        $certificate->pdf_generation_status = 'completed';
        $certificate->pdf_generated_at = now();
        $certificate->pdf_generation_error = null;
        $certificate->save();

        return $filename;
    }

    /**
     * Render the certificate HTML to PDF binary string.
     */
    public function renderPdfBinary(Certificate $certificate): string
    {
        $template = $certificate->template ?: 'classic';
        $config = $this->getTemplateConfig($template, $certificate);

        $course = $certificate->course;
        $user = $certificate->user;
        $enrollment = $certificate->enrollment;
        $academy = $course?->academy;

        // Preserve immutable issuance snapshot if present
        $snapshot = $certificate->issuance_snapshot;
        if (is_array($snapshot)) {
            $studentName = $snapshot['student']['name'] ?? $user?->name ?? 'Student';
            $courseTitle = $snapshot['course']['title'] ?? $course?->title ?? 'Course';
            $academyName = $snapshot['academy']['name'] ?? $academy?->name ?? config('app.name', 'SEO Academy');

            $studentObj = $user ? clone $user : new User;
            $studentObj->name = $studentName;

            $courseObj = $course ? clone $course : new Course;
            $courseObj->title = $courseTitle;

            $academyObj = $academy ? clone $academy : new Academy;
            $academyObj->name = $academyName;
        } else {
            $studentObj = $user;
            $courseObj = $course;
            $academyObj = $academy;
        }

        $verificationUrl = route('certificates.verify', [
            'code' => $certificate->getDecryptedVerificationCode() ?: ($certificate->verification_code ?: $certificate->certificate_number),
        ]);

        $viewName = $config['view'];
        if (! view()->exists($viewName)) {
            $viewName = 'certificates.templates.classic';
        }

        $html = view($viewName, [
            'certificate' => $certificate,
            'course' => $courseObj,
            'student' => $studentObj,
            'enrollment' => $enrollment,
            'academy' => $academyObj,
            'config' => $config,
            'verificationUrl' => $verificationUrl,
            'isPdf' => true,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        // Standard landscape US Letter / A4
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Get binary content of the PDF file.
     */
    public function getPdfBinary(Certificate $certificate): string
    {
        $disk = Storage::disk($this->getStorageDisk());

        if (! $certificate->pdf_path || ! $disk->exists($certificate->pdf_path)) {
            $this->generatePdf($certificate, true);
        }

        return $disk->get($certificate->pdf_path);
    }

    /**
     * Delete stored PDF file.
     */
    public function deletePdfFile(Certificate $certificate): bool
    {
        if ($certificate->pdf_path) {
            $disk = Storage::disk($this->getStorageDisk());
            if ($disk->exists($certificate->pdf_path)) {
                return $disk->delete($certificate->pdf_path);
            }
        }

        return false;
    }
}
