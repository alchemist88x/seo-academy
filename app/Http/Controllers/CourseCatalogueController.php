<?php

namespace App\Http\Controllers;

use App\Domain\Courses\Models\Course;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CourseCatalogueController extends Controller
{
    public function show(Request $request, string $slug, TenantContext $tenantContext): View
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

        return view('catalogue.show', [
            'course' => $course,
            'isPreview' => false,
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

        return view('catalogue.show', [
            'course' => $course,
            'isPreview' => true,
        ]);
    }
}
