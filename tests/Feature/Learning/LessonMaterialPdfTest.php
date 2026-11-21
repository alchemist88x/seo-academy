<?php

namespace Tests\Feature\Learning;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Livewire\Student\ClassroomPlayer;
use App\Models\User;
use App\Services\Pdf\Exceptions\OversizedLessonContentException;
use App\Services\Pdf\LessonMaterialPdfService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LessonMaterialPdfTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    private LessonMaterialPdfService $pdfService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create([
            'name' => 'SEO Mastery Academy',
            'subdomain' => 'seo-mastery',
            'primary_color' => '#4f46e5',
            'secondary_color' => '#06b6d4',
            'website_url' => 'https://seomastery.example.com',
            'settings' => ['tagline' => 'Advanced Digital Marketing'],
            'is_active' => true,
        ]);

        $this->pdfService = app(LessonMaterialPdfService::class);
    }

    private function createStudentWithEnrollment(Course $course, string $status = 'active'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('student');
        $user->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $profile = StudentProfile::factory()->create([
            'academy_id' => $this->academy->id,
            'user_id' => $user->id,
        ]);

        Enrollment::create([
            'academy_id' => $this->academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => $status,
            'enrolled_at' => now()->toDateString(),
        ]);

        return $user;
    }

    private function createCourseWithModuleAndLesson(array $lessonAttributes = []): array
    {
        $course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'title' => 'Technical SEO Fundamentals',
            'status' => 'Published',
            'difficulty' => 'Intermediate',
        ]);

        $module = Module::factory()->create([
            'academy_id' => $this->academy->id,
            'course_id' => $course->id,
            'title' => 'Crawlability and Indexation',
            'display_order' => 1,
            'status' => 'active',
        ]);

        $lesson = Lesson::factory()->create(array_merge([
            'academy_id' => $this->academy->id,
            'module_id' => $module->id,
            'title' => 'Understanding Robots.txt & Canonical Tags',
            'type' => 'video',
            'status' => 'active',
            'visibility' => 'enrolled',
            'duration_seconds' => 720,
            'description' => 'Master robots.txt directives and canonicalization strategies for enterprise websites.',
            'content' => "## Overview\nRobots.txt directs search crawler access.\n\n### Key Principles\n- Disallow sensitive paths\n- Use rel=canonical for duplicates\n\n```html\n<link rel=\"canonical\" href=\"https://example.com/page\" />\n```",
        ], $lessonAttributes));

        return [$course, $module, $lesson];
    }

    /** 1. PDF Generation produces valid binary */
    public function test_1_pdf_generation_produces_valid_pdf_binary(): void
    {
        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson();

        $pdfBinary = $this->pdfService->renderPdf($lesson, $course, $this->academy);

        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);
        $this->assertGreaterThan(1000, strlen($pdfBinary));
    }

    /** 2. Exactly one A4 page for supported content */
    public function test_2_pdf_renders_exactly_one_a4_page_for_supported_content(): void
    {
        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson();

        $pageCount = $this->pdfService->getPageCount($lesson, $course, $this->academy);
        $this->assertSame(1, $pageCount);
        $this->assertTrue($this->pdfService->fitsOnSinglePage($lesson, $course, $this->academy));
    }

    /** 3. Dynamic academy branding */
    public function test_3_dynamic_academy_branding_is_used(): void
    {
        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson();

        $html = $this->pdfService->renderHtml($lesson, $course, $this->academy);

        $this->assertStringContainsString('SEO Mastery Academy', $html);
        $this->assertStringContainsString('Advanced Digital Marketing', $html);
        $this->assertStringContainsString('#4f46e5', $html);
        $this->assertStringContainsString('https://seomastery.example.com', $html);
    }

    /** 4. Course and lesson metadata rendered */
    public function test_4_course_and_lesson_metadata_rendered(): void
    {
        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson();

        $html = $this->pdfService->renderHtml($lesson, $course, $this->academy);

        $this->assertStringContainsString('Technical SEO Fundamentals', $html);
        $this->assertStringContainsString('Crawlability and Indexation', $html);
        $this->assertStringContainsString('Understanding Robots.txt &amp; Canonical Tags', $html);
        $this->assertStringContainsString('Video', $html);
        $this->assertStringContainsString('12 mins', $html);
    }

    /** 5. Markdown heading rendering */
    public function test_5_markdown_heading_rendering(): void
    {
        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson([
            'content' => "# Main Subject\n## Secondary Section\n### Deep Dive",
        ]);

        $html = $this->pdfService->renderHtml($lesson, $course, $this->academy);

        $this->assertStringContainsString('<h1>Main Subject</h1>', $html);
        $this->assertStringContainsString('<h2>Secondary Section</h2>', $html);
        $this->assertStringContainsString('<h3>Deep Dive</h3>', $html);
        $this->assertStringNotContainsString('## Secondary Section', $html);
    }

    /** 6. Lists, tables and code blocks */
    public function test_6_markdown_lists_tables_and_code_blocks_rendered(): void
    {
        $content = "### Best Practices\n- Item One\n- Item Two\n\n| Directive | Behavior |\n|---|---|\n| Allow | Permits crawler |\n| Disallow | Blocks crawler |\n\n> Always verify with Search Console.\n\n```python\nimport requests\nprint('SEO Check')\n```";

        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson([
            'content' => $content,
        ]);

        $html = $this->pdfService->renderHtml($lesson, $course, $this->academy);

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Item One</li>', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>Directive</th>', $html);
        $this->assertStringContainsString('<td>Permits crawler</td>', $html);
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<pre><code', $html);
        $this->assertStringContainsString('class="language-python"', $html);
        $this->assertStringContainsString('import requests', $html);
        $this->assertStringNotContainsString('|---|', $html);
    }

    /** 7. HTML sanitization */
    public function test_7_html_sanitization_removes_dangerous_tags_and_scripts(): void
    {
        $dangerousContent = "Safe content.\n<script>alert('xss');</script>\n<img src=\"x\" onerror=\"alert(1)\" />\n<a href=\"javascript:evil()\">Link</a>\n<iframe src=\"https://malicious.com\"></iframe>";

        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson([
            'content' => $dangerousContent,
        ]);

        $html = $this->pdfService->renderHtml($lesson, $course, $this->academy);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:evil', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('Safe content.', $html);
    }

    /** 8. Oversized content validation */
    public function test_8_oversized_content_validation(): void
    {
        // Generate content that clearly overflows one A4 portrait page (e.g. 70 paragraphs with headers)
        $oversizedContent = "# Exhaustive SEO Reference\n\n";
        for ($i = 1; $i <= 60; $i++) {
            $oversizedContent .= "### Chapter {$i} Detailed Technical Analysis\n";
            $oversizedContent .= "Detailed technical paragraph {$i} providing long-form explanations of website architecture, crawl budgets, pagination directives, structured data validation, and internationalization schemas.\n\n";
        }

        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson([
            'content' => $oversizedContent,
        ]);

        $pageCount = $this->pdfService->getPageCount($lesson, $course, $this->academy);
        $this->assertGreaterThan(1, $pageCount);
        $this->assertFalse($this->pdfService->fitsOnSinglePage($lesson, $course, $this->academy));

        $this->expectException(OversizedLessonContentException::class);
        $this->pdfService->renderPdf($lesson, $course, $this->academy, true);
    }

    /** 9. Unauthorized PDF downloads blocked */
    public function test_9_unauthorized_pdf_downloads_blocked(): void
    {
        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson();

        // Guest access blocked
        $guestResponse = $this->get(route('student.courses.lessons.material-pdf', [$course, $lesson]));
        $guestResponse->assertRedirect(); // redirected to login

        // Unenrolled student blocked
        $unenrolledStudent = User::factory()->create(['is_active' => true]);
        $unenrolledStudent->assignRole('student');
        $unenrolledStudent->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);
        StudentProfile::factory()->create([
            'academy_id' => $this->academy->id,
            'user_id' => $unenrolledStudent->id,
        ]);

        $this->actingAs($unenrolledStudent);
        $studentResponse = $this->get(route('student.courses.lessons.material-pdf', [$course, $lesson]));
        $studentResponse->assertStatus(403);
    }

    /** 10. Enrollment-protected materials accessible only to valid students, assigned trainers and admins */
    public function test_10_enrollment_protected_materials_accessible_to_valid_students_and_staff(): void
    {
        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson();

        // Enrolled active student can download
        $student = $this->createStudentWithEnrollment($course, 'active');
        $this->actingAs($student);

        $response = $this->get(route('student.courses.lessons.material-pdf', [$course, $lesson]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Suspended student blocked
        $suspendedStudent = $this->createStudentWithEnrollment($course, 'suspended');
        $this->actingAs($suspendedStudent);

        $suspendedResponse = $this->get(route('student.courses.lessons.material-pdf', [$course, $lesson]));
        $suspendedResponse->assertStatus(403);

        // Assigned Trainer can download
        $trainer = User::factory()->create(['is_active' => true]);
        $trainer->assignRole('trainer');
        $trainer->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $course->trainers()->attach($trainer->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'created_at' => now(),
        ]);

        $this->actingAs($trainer);
        $trainerResponse = $this->get(route('student.courses.lessons.material-pdf', [$course, $lesson]));
        $trainerResponse->assertStatus(200);

        // Administrator can download
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $admin->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->actingAs($admin);
        $adminResponse = $this->get(route('student.courses.lessons.material-pdf', [$course, $lesson]));
        $adminResponse->assertStatus(200);
    }

    /** 11. Correct PDF filename and content type */
    public function test_11_correct_pdf_filename_and_content_type(): void
    {
        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson();

        $student = $this->createStudentWithEnrollment($course, 'active');
        $this->actingAs($student);

        $response = $this->get(route('student.courses.lessons.material-pdf', [$course, $lesson]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('technical-seo-fundamentals', $disposition);
        $this->assertStringContainsString('Notes.pdf', $disposition);
    }

    /** 12. Existing classroom functionality preserved */
    public function test_12_existing_classroom_player_functionality_preserved(): void
    {
        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson();

        $student = $this->createStudentWithEnrollment($course, 'active');
        $this->actingAs($student);

        Livewire::test(ClassroomPlayer::class, [
            'course' => $course,
            'lesson' => $lesson,
        ])
            ->assertSee($course->title)
            ->assertSee($lesson->title)
            ->assertSee('Overview')
            ->assertSee('Downloads')
            ->assertSee('Lesson PDF')
            ->call('setActiveTab', 'materials')
            ->assertSet('activeTab', 'materials')
            ->assertSee('Official Lesson Study Notes (1-Page PDF)')
            ->call('completeLesson')
            ->assertSet('isLessonCompleted', true);
    }

    /** 13. Responsive classroom layout */
    public function test_13_responsive_classroom_layout_elements(): void
    {
        [$course, $module, $lesson] = $this->createCourseWithModuleAndLesson();

        $student = $this->createStudentWithEnrollment($course, 'active');
        $this->actingAs($student);

        Livewire::test(ClassroomPlayer::class, [
            'course' => $course,
            'lesson' => $lesson,
        ])
            ->assertSet('mobileCurriculumOpen', false)
            ->call('toggleMobileCurriculum')
            ->assertSet('mobileCurriculumOpen', true)
            ->assertSee('Course Curriculum');
    }

    /** 14. Light classroom isolation */
    public function test_14_light_classroom_isolation(): void
    {
        $classroomLayout = file_get_contents(resource_path('views/layouts/classroom.blade.php'));

        $this->assertStringContainsString('class="light"', $classroomLayout);
        $this->assertStringContainsString("document.documentElement.classList.remove('dark')", $classroomLayout);
        $this->assertStringContainsString("document.documentElement.classList.add('light')", $classroomLayout);
    }
}
