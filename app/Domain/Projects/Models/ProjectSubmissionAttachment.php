<?php

namespace App\Domain\Projects\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSubmissionAttachment extends Model
{
    use HasUuids;

    protected $table = 'project_submission_attachments';

    public $timestamps = false;

    public const TYPE_SEO_AUDIT_REPORT = 'seo_audit_report';

    public const TYPE_PDF_DOCUMENT = 'pdf_document';

    public const TYPE_SPREADSHEET = 'spreadsheet';

    public const TYPE_SCREENSHOT = 'screenshot';

    public const TYPE_SUPPORTING_ATTACHMENT = 'supporting_attachment';

    public const TYPES = [
        self::TYPE_SEO_AUDIT_REPORT => 'SEO Audit Report',
        self::TYPE_PDF_DOCUMENT => 'PDF Document',
        self::TYPE_SPREADSHEET => 'Spreadsheet (CSV/Excel)',
        self::TYPE_SCREENSHOT => 'Screenshot',
        self::TYPE_SUPPORTING_ATTACHMENT => 'Supporting Attachment',
    ];

    protected $fillable = [
        'submission_id',
        'attachment_type',
        'storage_disk',
        'storage_path',
        'file_name',
        'file_size_bytes',
        'mime_type',
        'checksum',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ProjectSubmission::class, 'submission_id');
    }

    public function getReadableSize(): string
    {
        $bytes = $this->file_size_bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
