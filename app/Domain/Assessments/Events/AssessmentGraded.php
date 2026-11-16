<?php

namespace App\Domain\Assessments\Events;

use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssessmentGraded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AssessmentAttempt $attempt,
        public readonly ?User $evaluator = null
    ) {}
}
