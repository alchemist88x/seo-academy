<?php

namespace App\Services\Pdf\Exceptions;

use App\Domain\Lessons\Models\Lesson;
use DomainException;

class OversizedLessonContentException extends DomainException
{
    public function __construct(
        public readonly int $pageCount,
        public readonly ?Lesson $lesson = null,
        public readonly int $maxPages = 1,
        string $message = ''
    ) {
        $lessonTitle = $lesson ? " for lesson '{$lesson->title}'" : '';
        $effectiveMessage = $message !== ''
            ? $message
            : "Lesson content{$lessonTitle} exceeds the strict one-page limit ({$pageCount} pages rendered, maximum {$maxPages} page allowed). Please shorten or simplify the material to fit on a single page.";

        parent::__construct($effectiveMessage);
    }
}
