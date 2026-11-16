<?php

namespace App\Domain\Courses\Models;

use Database\Factories\CourseLevelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLevel extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'name',
        'display_order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    protected static function newFactory(): CourseLevelFactory
    {
        return CourseLevelFactory::new();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
