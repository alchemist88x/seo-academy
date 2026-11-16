<?php

namespace App\Domain\Assessments\Events;

use App\Domain\Assessments\Models\AssessmentAttempt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssessmentAttemptStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AssessmentAttempt $attempt
    ) {}
}
