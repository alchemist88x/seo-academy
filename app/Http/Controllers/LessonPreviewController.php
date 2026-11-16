<?php

namespace App\Http\Controllers;

use App\Domain\Courses\Models\Course;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LessonPreviewController extends Controller
{
    public function show(Course $course, Lesson $lesson, TenantContext $tenantContext): View
    {
        $academy = $tenantContext->getAcademy();
        if ($academy !== null && $course->academy_id !== $academy->getKey()) {
            abort(404);
        }

        $lesson->load(['module.course', 'media', 'materials.media', 'prerequisites']);

        if ($lesson->module === null || $lesson->module->course_id !== $course->getKey()) {
            abort(404);
        }

        Gate::authorize('preview', $lesson);

        return view('lessons.preview', [
            'course' => $course,
            'lesson' => $lesson,
        ]);
    }
}
