<?php

namespace App\Domain\Certificates\Models;

use App\Domain\Academies\Models\Academy;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    use HasUuids;

    protected $table = 'certificate_templates';

    protected $fillable = [
        'academy_id',
        'name',
        'slug',
        'description',
        'is_default',
        'is_active',
        'is_archived',
        'current_version_id',
        'page_size',
        'orientation',
        'background_color',
        'background_image_path',
        'border_design',
        'design_data',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
            'design_data' => 'array',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CertificateTemplateVersion::class, 'template_id')->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplateVersion::class, 'current_version_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'certificate_template_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the active design data (current published version or draft fallback).
     */
    public function getActiveDesignData(): array
    {
        if (! empty($this->design_data)) {
            return $this->design_data;
        }

        if ($this->currentVersion && ! empty($this->currentVersion->design_data)) {
            return $this->currentVersion->design_data;
        }

        return [];
    }
}
