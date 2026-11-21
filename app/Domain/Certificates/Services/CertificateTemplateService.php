<?php

namespace App\Domain\Certificates\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Models\CertificateTemplate;
use App\Domain\Certificates\Models\CertificateTemplateVersion;
use App\Domain\Platform\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CertificateTemplateService
{
    public const SUPPORTED_PLACEHOLDERS = [
        '{{student_name}}' => 'Student Name (Full legal or registered name)',
        '{{course_title}}' => 'Course Title',
        '{{academy_name}}' => 'Academy / Organization Name',
        '{{academy_tagline}}' => 'Academy Tagline or Motto',
        '{{completion_date}}' => 'Completion / Issuance Date',
        '{{certificate_number}}' => 'Unique Certificate Number (Required for verification)',
        '{{course_duration}}' => 'Course Duration or Academic Hours',
        '{{trainer_name}}' => 'Lead Trainer / Instructor Name',
        '{{signatory_name}}' => 'Signatory / Academic Director Name',
        '{{signatory_designation}}' => 'Signatory Designation / Academic Title',
        '{{verification_url}}' => 'Public Online Verification URL',
        '{{verification_qr}}' => 'Verification QR Code Image (Scannable link)',
    ];

    public function __construct(
        protected QrCodeService $qrCodeService
    ) {}

    /**
     * Get all available certificate templates for the given academy.
     *
     * @return Collection<int, CertificateTemplate>
     */
    public function getTemplates(Academy $academy, bool $includeArchived = false): Collection
    {
        $query = CertificateTemplate::query()
            ->where('academy_id', $academy->id)
            ->with(['currentVersion', 'author']);

        if (! $includeArchived) {
            $query->where('is_archived', false);
        }

        return $query->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve the active default template for an academy.
     */
    public function getDefaultTemplate(Academy $academy): ?CertificateTemplate
    {
        return CertificateTemplate::query()
            ->where('academy_id', $academy->id)
            ->where('is_archived', false)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? CertificateTemplate::query()
                ->where('academy_id', $academy->id)
                ->where('is_archived', false)
                ->where('is_active', true)
                ->first();
    }

    /**
     * Create a new certificate template with initial published version 1.
     */
    public function createTemplate(Academy $academy, array $data, ?User $author = null): CertificateTemplate
    {
        $name = trim($data['name'] ?? 'Custom Certificate');
        $slug = Str::slug($name).'-'.Str::random(5);

        $designData = $data['design_data'] ?? $this->getPresetDesign('classic', $academy);

        return DB::transaction(function () use ($academy, $name, $slug, $data, $designData, $author) {
            $template = CertificateTemplate::create([
                'academy_id' => $academy->id,
                'name' => $name,
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'is_default' => (bool) ($data['is_default'] ?? false),
                'is_active' => true,
                'is_archived' => false,
                'page_size' => $designData['canvas']['pageSize'] ?? 'A4',
                'orientation' => $designData['canvas']['orientation'] ?? 'landscape',
                'background_color' => $designData['canvas']['backgroundColor'] ?? '#ffffff',
                'background_image_path' => $data['background_image_path'] ?? null,
                'border_design' => $designData['canvas']['borderDesign'] ?? 'double',
                'design_data' => $designData,
                'created_by' => $author?->id,
                'updated_by' => $author?->id,
            ]);

            // Create initial published Version 1
            $version = CertificateTemplateVersion::create([
                'template_id' => $template->id,
                'version_number' => 1,
                'status' => 'published',
                'design_data' => $designData,
                'change_summary' => 'Initial template creation',
                'created_by' => $author?->id,
                'published_at' => now(),
            ]);

            $template->current_version_id = $version->id;
            $template->save();

            if ($template->is_default) {
                $this->setDefaultTemplate($template);
            }

            return $template;
        });
    }

    /**
     * Duplicate an existing certificate template.
     */
    public function duplicateTemplate(CertificateTemplate $template, ?User $author = null): CertificateTemplate
    {
        $design = $template->getActiveDesignData();

        return $this->createTemplate($template->academy, [
            'name' => $template->name.' (Copy)',
            'description' => $template->description,
            'is_default' => false,
            'design_data' => $design,
            'background_image_path' => $template->background_image_path,
        ], $author);
    }

    /**
     * Save draft modifications without changing the live published version.
     */
    public function saveDraft(
        CertificateTemplate $template,
        array $designData,
        ?User $author = null,
        ?string $changeSummary = 'Draft changes'
    ): CertificateTemplateVersion {
        return DB::transaction(function () use ($template, $designData, $author, $changeSummary) {
            $template->design_data = $designData;
            $template->updated_by = $author?->id;
            $template->save();

            $nextVersionNumber = ($template->versions()->max('version_number') ?? 0) + 1;

            return CertificateTemplateVersion::create([
                'template_id' => $template->id,
                'version_number' => $nextVersionNumber,
                'status' => 'draft',
                'design_data' => $designData,
                'change_summary' => $changeSummary,
                'created_by' => $author?->id,
                'published_at' => null,
            ]);
        });
    }

    /**
     * Publish a new template version to production.
     */
    public function publishVersion(
        CertificateTemplate $template,
        array $designData,
        ?User $author = null,
        ?string $changeSummary = 'Published updated design'
    ): CertificateTemplateVersion {
        return DB::transaction(function () use ($template, $designData, $author, $changeSummary) {
            $nextVersionNumber = ($template->versions()->max('version_number') ?? 0) + 1;

            $version = CertificateTemplateVersion::create([
                'template_id' => $template->id,
                'version_number' => $nextVersionNumber,
                'status' => 'published',
                'design_data' => $designData,
                'change_summary' => $changeSummary,
                'created_by' => $author?->id,
                'published_at' => now(),
            ]);

            $template->update([
                'current_version_id' => $version->id,
                'design_data' => $designData,
                'page_size' => $designData['canvas']['pageSize'] ?? $template->page_size,
                'orientation' => $designData['canvas']['orientation'] ?? $template->orientation,
                'background_color' => $designData['canvas']['backgroundColor'] ?? $template->background_color,
                'border_design' => $designData['canvas']['borderDesign'] ?? $template->border_design,
                'updated_by' => $author?->id,
            ]);

            return $version;
        });
    }

    /**
     * Restore a previous version as the current published version.
     */
    public function restoreVersion(
        CertificateTemplate $template,
        CertificateTemplateVersion $version,
        ?User $author = null
    ): CertificateTemplate {
        if ((string) $version->template_id !== (string) $template->id) {
            throw new InvalidArgumentException('Version does not belong to this template.');
        }

        return DB::transaction(function () use ($template, $version, $author) {
            $this->publishVersion(
                $template,
                $version->design_data,
                $author,
                "Restored from version {$version->version_number}"
            );

            return $template->fresh();
        });
    }

    /**
     * Set the template as the default for the academy.
     */
    public function setDefaultTemplate(CertificateTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            CertificateTemplate::query()
                ->where('academy_id', $template->academy_id)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);

            $template->update([
                'is_default' => true,
                'is_active' => true,
                'is_archived' => false,
            ]);

            Setting::updateOrCreate(
                [
                    'academy_id' => $template->academy_id,
                    'group' => 'certificates',
                    'key' => 'default_template',
                ],
                [
                    'value' => $template->id,
                    'type' => 'string',
                    'is_public' => false,
                ]
            );
        });
    }

    /**
     * Archive a template (cannot be default).
     */
    public function archiveTemplate(CertificateTemplate $template): CertificateTemplate
    {
        if ($template->is_default) {
            throw new InvalidArgumentException('Default template cannot be archived. Designate another default template first.');
        }

        $template->update([
            'is_archived' => true,
            'is_active' => false,
        ]);

        return $template;
    }

    /**
     * Ensure default built-in starter templates exist for the academy.
     *
     * @return Collection<int, CertificateTemplate>
     */
    public function ensureDefaultTemplatesExist(Academy $academy, ?User $author = null): Collection
    {
        $existing = CertificateTemplate::where('academy_id', $academy->id)->get();
        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $presets = [
            [
                'name' => 'Classic Academic',
                'description' => 'Traditional distinguished certificate with navy double borders and gold typography.',
                'is_default' => true,
                'design' => $this->getPresetDesign('classic', $academy),
            ],
            [
                'name' => 'Modern Professional',
                'description' => 'Crisp contemporary certificate with geometric accents and integrated QR verification.',
                'is_default' => false,
                'design' => $this->getPresetDesign('modern', $academy),
            ],
            [
                'name' => 'Gold Achievement',
                'description' => 'High-honor distinction layout with rich gold borders and ribbon achievement badge.',
                'is_default' => false,
                'design' => $this->getPresetDesign('gold', $academy),
            ],
            [
                'name' => 'Minimal Corporate',
                'description' => 'Clean executive certificate with minimalist typography and emerald styling.',
                'is_default' => false,
                'design' => $this->getPresetDesign('minimalist', $academy),
            ],
        ];

        foreach ($presets as $p) {
            $this->createTemplate($academy, [
                'name' => $p['name'],
                'description' => $p['description'],
                'is_default' => $p['is_default'],
                'design_data' => $p['design'],
            ], $author);
        }

        return CertificateTemplate::where('academy_id', $academy->id)->get();
    }

    /**
     * Provide sample preview data for the live designer.
     *
     * @return array<string, mixed>
     */
    public function getSamplePreviewData(?Academy $academy = null): array
    {
        $academyName = $academy?->name ?: 'SEO Academy';
        $tagline = $academy?->setting('tagline') ?: 'Advanced Digital Marketing';
        $websiteUrl = $academy?->website_url ?: config('app.url', 'https://seoacademy.local');

        $certNumber = 'CERT-2026-PREVIEW-X1';
        $verificationUrl = route('certificates.verify', ['code' => $certNumber]);
        $qrPng = $this->qrCodeService->generatePngDataUri($verificationUrl, 160);

        return [
            'student_name' => 'Alex Morgan',
            'course_title' => 'Advanced SEO Architecture & Technical Audit',
            'academy_name' => $academyName,
            'academy_tagline' => $tagline,
            'completion_date' => now()->format('F d, Y'),
            'certificate_number' => $certNumber,
            'course_duration' => '12 Academic Weeks',
            'trainer_name' => 'David Vance',
            'signatory_name' => 'Dr. Jane Smith',
            'signatory_designation' => 'Academic Director & Lead Instructor',
            'verification_url' => $verificationUrl,
            'verification_qr' => $qrPng,
        ];
    }

    /**
     * Resolve placeholders in element content from certificate model or snapshot.
     */
    public function resolveDataForCertificate(Certificate $certificate): array
    {
        $course = $certificate->course;
        $user = $certificate->user;
        $academy = $course?->academy ?? Academy::current();
        $snapshot = $certificate->issuance_snapshot ?? [];

        $studentName = $snapshot['student']['name'] ?? $user?->name ?? 'Student';
        $courseTitle = $snapshot['course']['title'] ?? $course?->title ?? 'Course';
        $academyName = $snapshot['academy']['name'] ?? $academy?->name ?? config('app.name', 'SEO Academy');
        $tagline = $academy?->setting('tagline') ?: 'Digital Marketing';

        $signatoryName = $snapshot['branding']['signatory_name']
            ?? Setting::where('academy_id', $academy?->id)->where('group', 'certificates')->where('key', 'signatory_name')->value('value')
            ?? ($academyName.' Director');

        $signatoryTitle = $snapshot['branding']['signatory_title']
            ?? Setting::where('academy_id', $academy?->id)->where('group', 'certificates')->where('key', 'signatory_title')->value('value')
            ?? 'Academic Director';

        $code = $certificate->getDecryptedVerificationCode() ?: ($certificate->verification_code ?: $certificate->certificate_number);
        $verificationUrl = route('certificates.verify', ['code' => $code]);
        $qrPng = $this->qrCodeService->generatePngDataUri($verificationUrl, 160);

        return [
            'student_name' => $studentName,
            'course_title' => $courseTitle,
            'academy_name' => $academyName,
            'academy_tagline' => $tagline,
            'completion_date' => $certificate->issued_at ? $certificate->issued_at->format('F d, Y') : now()->format('F d, Y'),
            'certificate_number' => $certificate->certificate_number,
            'course_duration' => $course?->duration_months ? "{$course->duration_months} Months" : 'Professional Course',
            'trainer_name' => $course?->trainers()->first()?->name ?? 'Course Faculty',
            'signatory_name' => $signatoryName,
            'signatory_designation' => $signatoryTitle,
            'verification_url' => $verificationUrl,
            'verification_qr' => $qrPng,
        ];
    }

    /**
     * Replace all placeholders in a string.
     */
    public function replacePlaceholders(string $text, array $data): string
    {
        foreach ($data as $key => $val) {
            $placeholder = '{{'.$key.'}}';
            if (str_contains($text, $placeholder)) {
                $text = str_replace($placeholder, (string) $val, $text);
            }
        }

        return $text;
    }

    /**
     * Check if mandatory verification elements exist in the design data.
     */
    public function validateVerificationBindings(array $designData): array
    {
        $elements = $designData['elements'] ?? [];
        $hasCertNumber = false;
        $hasVerification = false;

        foreach ($elements as $el) {
            $content = (string) ($el['content'] ?? '');
            if (str_contains($content, '{{certificate_number}}')) {
                $hasCertNumber = true;
            }
            if (str_contains($content, '{{verification_url}}') || str_contains($content, '{{verification_qr}}') || ($el['type'] ?? '') === 'qr') {
                $hasVerification = true;
            }
        }

        $warnings = [];
        if (! $hasCertNumber) {
            $warnings[] = 'Certificate Number placeholder {{certificate_number}} is missing. It is highly recommended to display the unique certificate number.';
        }
        if (! $hasVerification) {
            $warnings[] = 'Verification URL {{verification_url}} or Verification QR Code {{verification_qr}} is missing. Students and employers will not be able to scan or verify credentials online.';
        }

        return [
            'is_valid' => true,
            'warnings' => $warnings,
            'has_cert_number' => $hasCertNumber,
            'has_verification' => $hasVerification,
        ];
    }

    /**
     * Store an uploaded asset file for certificate templates.
     */
    public function storeAsset(UploadedFile $file, Academy $academy, string $category = 'elements'): string
    {
        $allowedMimes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            throw new InvalidArgumentException('Unsupported file format. Please upload a PNG, JPG, or WebP image.');
        }

        if ($file->getSize() > 3 * 1024 * 1024) {
            throw new InvalidArgumentException('Asset exceeds maximum upload size of 3MB.');
        }

        $dir = "academies/{$academy->id}/certificates/{$category}";
        $path = $file->store($dir, 'public');

        return Storage::disk('public')->url($path);
    }

    /**
     * Built-in starter preset designs.
     */
    public function getPresetDesign(string $preset, Academy $academy): array
    {
        $academyName = $academy->name ?: 'SEO Academy';
        $tagline = $academy->setting('tagline') ?: 'Digital Marketing';

        return match ($preset) {
            'modern' => $this->getModernPreset($academyName, $tagline),
            'gold' => $this->getGoldPreset($academyName, $tagline),
            'minimalist' => $this->getMinimalistPreset($academyName, $tagline),
            default => $this->getClassicPreset($academyName, $tagline),
        };
    }

    protected function getClassicPreset(string $academyName, string $tagline): array
    {
        return [
            'canvas' => [
                'pageSize' => 'A4',
                'orientation' => 'landscape',
                'width' => 842,
                'height' => 595,
                'backgroundColor' => '#ffffff',
                'borderDesign' => 'double',
                'borderColor' => '#1e3a8a',
                'borderWidth' => 6,
                'margin' => 20,
            ],
            'elements' => [
                [
                    'id' => 'elem_border',
                    'type' => 'rectangle',
                    'x' => 25,
                    'y' => 20,
                    'width' => 792,
                    'height' => 555,
                    'rotation' => 0,
                    'zIndex' => 1,
                    'locked' => true,
                    'content' => '',
                    'style' => [
                        'backgroundColor' => 'transparent',
                        'borderColor' => '#1e3a8a',
                        'borderWidth' => 4,
                        'borderStyle' => 'double',
                    ],
                ],
                [
                    'id' => 'elem_academy',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 50,
                    'width' => 700,
                    'height' => 30,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => '{{academy_name}}',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 18,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#1e3a8a',
                        'letterSpacing' => 2,
                    ],
                ],
                [
                    'id' => 'elem_tagline',
                    'type' => 'text',
                    'x' => 71,
                    'y' => 80,
                    'width' => 700,
                    'height' => 20,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => '{{academy_tagline}}',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 10,
                        'fontStyle' => 'italic',
                        'textAlign' => 'center',
                        'color' => '#64748b',
                    ],
                ],
                [
                    'id' => 'elem_title',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 115,
                    'width' => 700,
                    'height' => 45,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'CERTIFICATE OF COMPLETION',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 26,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#d97706',
                        'letterSpacing' => 3,
                    ],
                ],
                [
                    'id' => 'elem_certify_text',
                    'type' => 'text',
                    'x' => 71,
                    'y' => 170,
                    'width' => 700,
                    'height' => 20,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'This is to certify that',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 12,
                        'fontStyle' => 'italic',
                        'textAlign' => 'center',
                        'color' => '#475569',
                    ],
                ],
                [
                    'id' => 'elem_student_name',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 200,
                    'width' => 700,
                    'height' => 50,
                    'rotation' => 0,
                    'zIndex' => 6,
                    'locked' => false,
                    'content' => '{{student_name}}',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 28,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#0f172a',
                        'textDecoration' => 'underline',
                    ],
                ],
                [
                    'id' => 'elem_completion_statement',
                    'type' => 'text',
                    'x' => 71,
                    'y' => 265,
                    'width' => 700,
                    'height' => 20,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'has successfully fulfilled all academic requirements and completed',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 11,
                        'textAlign' => 'center',
                        'color' => '#475569',
                    ],
                ],
                [
                    'id' => 'elem_course_title',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 295,
                    'width' => 700,
                    'height' => 45,
                    'rotation' => 0,
                    'zIndex' => 6,
                    'locked' => false,
                    'content' => '{{course_title}}',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 20,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#1e3a8a',
                    ],
                ],
                [
                    'id' => 'elem_date',
                    'type' => 'text',
                    'x' => 71,
                    'y' => 365,
                    'width' => 220,
                    'height' => 45,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => "Issued On:\n{{completion_date}}",
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 10,
                        'textAlign' => 'center',
                        'color' => '#334155',
                        'borderTopWidth' => 1,
                        'borderTopColor' => '#94a3b8',
                    ],
                ],
                [
                    'id' => 'elem_qr',
                    'type' => 'qr',
                    'x' => 381,
                    'y' => 360,
                    'width' => 80,
                    'height' => 80,
                    'rotation' => 0,
                    'zIndex' => 8,
                    'locked' => false,
                    'content' => '{{verification_qr}}',
                    'style' => [
                        'textAlign' => 'center',
                    ],
                ],
                [
                    'id' => 'elem_signatory',
                    'type' => 'text',
                    'x' => 551,
                    'y' => 365,
                    'width' => 220,
                    'height' => 45,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => "{{signatory_name}}\n{{signatory_designation}}",
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 10,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#0f172a',
                        'borderTopWidth' => 1,
                        'borderTopColor' => '#94a3b8',
                    ],
                ],
                [
                    'id' => 'elem_cert_number',
                    'type' => 'text',
                    'x' => 71,
                    'y' => 475,
                    'width' => 700,
                    'height' => 20,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'Certificate ID: {{certificate_number}} • Verify Authenticity at {{verification_url}}',
                    'style' => [
                        'fontFamily' => 'Courier',
                        'fontSize' => 8,
                        'textAlign' => 'center',
                        'color' => '#64748b',
                    ],
                ],
            ],
        ];
    }

    protected function getModernPreset(string $academyName, string $tagline): array
    {
        return [
            'canvas' => [
                'pageSize' => 'A4',
                'orientation' => 'landscape',
                'width' => 842,
                'height' => 595,
                'backgroundColor' => '#ffffff',
                'borderDesign' => 'solid',
                'borderColor' => '#4f46e5',
                'borderWidth' => 2,
                'margin' => 20,
            ],
            'elements' => [
                [
                    'id' => 'elem_top_bar',
                    'type' => 'rectangle',
                    'x' => 25,
                    'y' => 25,
                    'width' => 792,
                    'height' => 8,
                    'rotation' => 0,
                    'zIndex' => 2,
                    'locked' => true,
                    'content' => '',
                    'style' => [
                        'backgroundColor' => '#4f46e5',
                    ],
                ],
                [
                    'id' => 'elem_academy',
                    'type' => 'heading',
                    'x' => 60,
                    'y' => 55,
                    'width' => 500,
                    'height' => 30,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => '{{academy_name}}',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 18,
                        'fontWeight' => 'bold',
                        'textAlign' => 'left',
                        'color' => '#0f172a',
                    ],
                ],
                [
                    'id' => 'elem_badge',
                    'type' => 'text',
                    'x' => 600,
                    'y' => 55,
                    'width' => 180,
                    'height' => 25,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'OFFICIAL CREDENTIAL',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 9,
                        'fontWeight' => 'bold',
                        'textAlign' => 'right',
                        'color' => '#4f46e5',
                        'letterSpacing' => 1,
                    ],
                ],
                [
                    'id' => 'elem_title',
                    'type' => 'heading',
                    'x' => 60,
                    'y' => 110,
                    'width' => 720,
                    'height' => 40,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'CERTIFICATE OF ACHIEVEMENT',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 24,
                        'fontWeight' => 'bold',
                        'textAlign' => 'left',
                        'color' => '#1e293b',
                        'letterSpacing' => 1,
                    ],
                ],
                [
                    'id' => 'elem_presented_to',
                    'type' => 'text',
                    'x' => 60,
                    'y' => 165,
                    'width' => 720,
                    'height' => 20,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'Proudly presented to',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 11,
                        'textAlign' => 'left',
                        'color' => '#64748b',
                    ],
                ],
                [
                    'id' => 'elem_student_name',
                    'type' => 'heading',
                    'x' => 60,
                    'y' => 195,
                    'width' => 720,
                    'height' => 45,
                    'rotation' => 0,
                    'zIndex' => 6,
                    'locked' => false,
                    'content' => '{{student_name}}',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 26,
                        'fontWeight' => 'bold',
                        'textAlign' => 'left',
                        'color' => '#4f46e5',
                    ],
                ],
                [
                    'id' => 'elem_course_text',
                    'type' => 'text',
                    'x' => 60,
                    'y' => 255,
                    'width' => 720,
                    'height' => 20,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'for demonstrated expertise and successful mastery of',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 11,
                        'textAlign' => 'left',
                        'color' => '#475569',
                    ],
                ],
                [
                    'id' => 'elem_course_title',
                    'type' => 'heading',
                    'x' => 60,
                    'y' => 285,
                    'width' => 720,
                    'height' => 40,
                    'rotation' => 0,
                    'zIndex' => 6,
                    'locked' => false,
                    'content' => '{{course_title}}',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 18,
                        'fontWeight' => 'bold',
                        'textAlign' => 'left',
                        'color' => '#0f172a',
                    ],
                ],
                [
                    'id' => 'elem_qr',
                    'type' => 'qr',
                    'x' => 60,
                    'y' => 365,
                    'width' => 85,
                    'height' => 85,
                    'rotation' => 0,
                    'zIndex' => 8,
                    'locked' => false,
                    'content' => '{{verification_qr}}',
                    'style' => [],
                ],
                [
                    'id' => 'elem_verification_meta',
                    'type' => 'text',
                    'x' => 160,
                    'y' => 375,
                    'width' => 300,
                    'height' => 65,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => "Credential ID: {{certificate_number}}\nIssued: {{completion_date}}\nVerified Online: {{verification_url}}",
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 9,
                        'textAlign' => 'left',
                        'color' => '#64748b',
                        'lineHeight' => 1.4,
                    ],
                ],
                [
                    'id' => 'elem_signatory',
                    'type' => 'text',
                    'x' => 540,
                    'y' => 410,
                    'width' => 240,
                    'height' => 40,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => "{{signatory_name}}\n{{signatory_designation}}",
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 10,
                        'fontWeight' => 'bold',
                        'textAlign' => 'right',
                        'color' => '#0f172a',
                        'borderTopWidth' => 1,
                        'borderTopColor' => '#cbd5e1',
                    ],
                ],
            ],
        ];
    }

    protected function getGoldPreset(string $academyName, string $tagline): array
    {
        return [
            'canvas' => [
                'pageSize' => 'A4',
                'orientation' => 'landscape',
                'width' => 842,
                'height' => 595,
                'backgroundColor' => '#fcfbf7',
                'borderDesign' => 'gold_inset',
                'borderColor' => '#d97706',
                'borderWidth' => 4,
                'margin' => 20,
            ],
            'elements' => [
                [
                    'id' => 'elem_academy',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 50,
                    'width' => 700,
                    'height' => 30,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => '{{academy_name}}',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 20,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#b45309',
                        'letterSpacing' => 2,
                    ],
                ],
                [
                    'id' => 'elem_title',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 110,
                    'width' => 700,
                    'height' => 45,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'DISTINGUISHED CERTIFICATE OF EXCELLENCE',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 22,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#78350f',
                        'letterSpacing' => 2,
                    ],
                ],
                [
                    'id' => 'elem_student_name',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 200,
                    'width' => 700,
                    'height' => 50,
                    'rotation' => 0,
                    'zIndex' => 6,
                    'locked' => false,
                    'content' => '{{student_name}}',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 28,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#1e293b',
                    ],
                ],
                [
                    'id' => 'elem_course_title',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 285,
                    'width' => 700,
                    'height' => 45,
                    'rotation' => 0,
                    'zIndex' => 6,
                    'locked' => false,
                    'content' => '{{course_title}}',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 20,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#b45309',
                    ],
                ],
                [
                    'id' => 'elem_qr',
                    'type' => 'qr',
                    'x' => 381,
                    'y' => 360,
                    'width' => 80,
                    'height' => 80,
                    'rotation' => 0,
                    'zIndex' => 8,
                    'locked' => false,
                    'content' => '{{verification_qr}}',
                    'style' => [],
                ],
                [
                    'id' => 'elem_cert_number',
                    'type' => 'text',
                    'x' => 71,
                    'y' => 470,
                    'width' => 700,
                    'height' => 20,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'Certificate # {{certificate_number}} • {{verification_url}}',
                    'style' => [
                        'fontFamily' => 'Georgia',
                        'fontSize' => 9,
                        'textAlign' => 'center',
                        'color' => '#78350f',
                    ],
                ],
            ],
        ];
    }

    protected function getMinimalistPreset(string $academyName, string $tagline): array
    {
        return [
            'canvas' => [
                'pageSize' => 'A4',
                'orientation' => 'landscape',
                'width' => 842,
                'height' => 595,
                'backgroundColor' => '#ffffff',
                'borderDesign' => 'none',
                'borderColor' => '#e2e8f0',
                'borderWidth' => 0,
                'margin' => 30,
            ],
            'elements' => [
                [
                    'id' => 'elem_academy',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 60,
                    'width' => 700,
                    'height' => 25,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => '{{academy_name}}',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 14,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#059669',
                        'letterSpacing' => 1,
                    ],
                ],
                [
                    'id' => 'elem_title',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 110,
                    'width' => 700,
                    'height' => 40,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'CERTIFICATE OF COMPLETION',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 22,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#0f172a',
                    ],
                ],
                [
                    'id' => 'elem_student_name',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 200,
                    'width' => 700,
                    'height' => 45,
                    'rotation' => 0,
                    'zIndex' => 6,
                    'locked' => false,
                    'content' => '{{student_name}}',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 28,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#059669',
                    ],
                ],
                [
                    'id' => 'elem_course_title',
                    'type' => 'heading',
                    'x' => 71,
                    'y' => 280,
                    'width' => 700,
                    'height' => 40,
                    'rotation' => 0,
                    'zIndex' => 6,
                    'locked' => false,
                    'content' => '{{course_title}}',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 18,
                        'fontWeight' => 'bold',
                        'textAlign' => 'center',
                        'color' => '#0f172a',
                    ],
                ],
                [
                    'id' => 'elem_qr',
                    'type' => 'qr',
                    'x' => 381,
                    'y' => 350,
                    'width' => 80,
                    'height' => 80,
                    'rotation' => 0,
                    'zIndex' => 8,
                    'locked' => false,
                    'content' => '{{verification_qr}}',
                    'style' => [],
                ],
                [
                    'id' => 'elem_cert_number',
                    'type' => 'text',
                    'x' => 71,
                    'y' => 460,
                    'width' => 700,
                    'height' => 20,
                    'rotation' => 0,
                    'zIndex' => 5,
                    'locked' => false,
                    'content' => 'Verification: {{certificate_number}} • {{verification_url}}',
                    'style' => [
                        'fontFamily' => 'Helvetica',
                        'fontSize' => 8,
                        'textAlign' => 'center',
                        'color' => '#94a3b8',
                    ],
                ],
            ],
        ];
    }
}
