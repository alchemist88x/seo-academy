<?php

namespace App\Domain\Learning\Events;

use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Course $course,
        public readonly Enrollment $enrollment
    ) {}
}
