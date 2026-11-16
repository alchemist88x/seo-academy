<?php

namespace App\Domain\Courses\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CourseTrainer extends Pivot
{
    use HasUuids;

    protected $table = 'course_trainers';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public function getUpdatedAtColumn()
    {
        return null;
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
