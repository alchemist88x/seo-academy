<?php

namespace App\Domain\Lessons\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LessonPrerequisite extends Pivot
{
    use HasUuids;

    protected $table = 'lesson_prerequisites';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
