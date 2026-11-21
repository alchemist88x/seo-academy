<?php

namespace App\Domain\Projects\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPortfolioItem extends Model
{
    use HasUuids;

    protected $table = 'student_portfolio_items';

    protected $fillable = [
        'portfolio_id',
        'submission_id',
        'title',
        'case_study',
        'skills_demonstrated',
        'display_order',
        'is_featured',
        'is_visible',
        'included_attachments',
    ];

    protected function casts(): array
    {
        return [
            'skills_demonstrated' => 'array',
            'included_attachments' => 'array',
            'display_order' => 'integer',
            'is_featured' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(StudentPortfolio::class, 'portfolio_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ProjectSubmission::class, 'submission_id');
    }
}
