<?php

namespace App\Http\Controllers;

use App\Domain\Courses\Models\Course;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\CourseNavigationService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CourseCatalogueController extends Controller
{
    public function show(Request $request, string $slug, TenantContext $tenantContext, CourseNavigationService $navigationService): View
    {
        $academy = $tenantContext->getAcademy();

        $query = Course::query()
            ->with([
                'academy',
                'category',
                'trainers',
                'prerequisites',
                'modules' => fn ($q) => $q->orderBy('display_order')->with([
                    'lessons' => fn ($lq) => $lq->orderBy('display_order'),
                ]),
            ])
            ->where('slug', $slug);

        if ($academy !== null) {
            $query->forAcademy($academy);
        } else {
            $query->where('visibility', 'public');
        }

        $course = $query->firstOrFail();

        if (! $course->isPublished()) {
            abort(404);
        }

        if (! in_array($course->visibility, ['public', 'academy_only'], true)) {
            abort(404);
        }

        if ($course->visibility === 'academy_only') {
            $user = $request->user();

            if ($user === null || (! $user->hasRole('admin') && ! $user->belongsToAcademy($course->academy_id))) {
                abort(404);
            }
        }

        $user = $request->user();
        $enrollment = null;
        $progressPercent = 0;
        $completedLessonIds = [];
        $resumeLesson = null;

        if ($user && $user->hasRole('student')) {
            $studentProfile = $user->studentProfile;
            if ($studentProfile) {
                $enrollment = $studentProfile->enrollments()
                    ->where('course_id', $course->id)
                    ->first();

                if ($enrollment) {
                    $totalLessons = $course->modules->flatMap->lessons->count();
                    $completedLessons = LessonProgress::query()
                        ->where('enrollment_id', $enrollment->id)
                        ->where('status', 'completed')
                        ->count();

                    $progressPercent = $totalLessons > 0 ? (int) round(($completedLessons / $totalLessons) * 100) : 0;
                    if ($enrollment->status === 'completed') {
                        $progressPercent = 100;
                    }

                    $completedLessonIds = LessonProgress::query()
                        ->where('enrollment_id', $enrollment->id)
                        ->where('status', 'completed')
                        ->pluck('lesson_id')
                        ->map(fn ($id) => (string) $id)
                        ->all();

                    $resumeLesson = $navigationService->resolveResumeLesson($course, $user);
                }
            }
        }

        $totalDurationSeconds = (int) $course->modules->flatMap->lessons->sum('duration_seconds');

        return view('catalogue.show', [
            'course' => $course,
            'isPreview' => false,
            'enrollment' => $enrollment,
            'progressPercent' => $progressPercent,
            'completedLessonIds' => $completedLessonIds,
            'resumeLesson' => $resumeLesson,
            'totalDurationSeconds' => $totalDurationSeconds,
        ]);
    }

    public function preview(Course $course, TenantContext $tenantContext): View
    {
        $academy = $tenantContext->getAcademy();
        if ($academy !== null && $course->academy_id !== $academy->getKey()) {
            abort(404);
        }

        Gate::authorize('preview', $course);

        $course->load([
            'academy',
            'category',
            'trainers',
            'prerequisites',
            'modules' => fn ($q) => $q->orderBy('display_order')->with([
                'lessons' => fn ($lq) => $lq->orderBy('display_order'),
            ]),
        ]);

        $totalDurationSeconds = (int) $course->modules->flatMap->lessons->sum('duration_seconds');

        return view('catalogue.show', [
            'course' => $course,
            'isPreview' => true,
            'enrollment' => null,
            'progressPercent' => 0,
            'completedLessonIds' => [],
            'resumeLesson' => null,
            'totalDurationSeconds' => $totalDurationSeconds,
        ]);
    }
}
