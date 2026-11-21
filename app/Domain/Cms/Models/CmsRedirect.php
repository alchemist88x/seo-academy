<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsRedirect extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_redirects';

    protected $fillable = [
        'academy_id',
        'source_path',
        'target_path',
        'status_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }
}
