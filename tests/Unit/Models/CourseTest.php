<?php

namespace Tests\Unit\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_model_can_be_created_with_factory(): void
    {
        $course = Course::factory()->create();

        $this->assertInstanceOf(Course::class, $course);
        $this->assertTrue(Str::isUuid((string) $course->id));
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => $course->title,
        ]);
    }

    public function test_course_has_correct_relationships(): void
    {
        $academy = Academy::factory()->create();
        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);

        $course->refresh();

        $this->assertInstanceOf(Academy::class, $course->academy);
        $this->assertSame($academy->id, $course->academy->id);
        $this->assertCount(1, $course->modules);
        $this->assertInstanceOf(Module::class, $course->modules->first());
        $this->assertSame($module->id, $course->modules->first()->id);
    }
}
