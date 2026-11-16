<?php

namespace Tests\Feature\Media;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Models\Media;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivateMediaTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $createdPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $path) {
            Storage::disk('private_media')->delete($path);
        }

        parent::tearDown();
    }

    /**
     * @return array{0: Media, 1: Course}
     */
    private function createPrivateMediaForAcademy(
        Academy $academy,
        string $content = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        string $mimeType = 'video/mp4',
        string $extension = 'mp4',
    ): array {
        $path = 'tests/'.Str::uuid().'.'.$extension;
        Storage::disk('private_media')->put($path, $content);
        $this->createdPaths[] = $path;

        $course = Course::factory()->create(['academy_id' => $academy->getKey()]);

        $media = Media::create([
            'model_type' => Course::class,
            'model_id' => $course->getKey(),
            'collection' => 'videos',
            'disk' => 'private_media',
            'path' => $path,
            'filename' => 'lesson-sample.'.$extension,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($content),
            'created_at' => now(),
        ]);

        return [$media, $course];
    }

    private function createStudentInAcademy(Academy $academy, ?Course $enrollInCourse = null): User
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $student->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => 'student',
        ]);

        $profile = StudentProfile::factory()->create([
            'academy_id' => $academy->getKey(),
            'user_id' => $student->getKey(),
        ]);

        if ($enrollInCourse !== null) {
            Enrollment::create([
                'student_profile_id' => $profile->getKey(),
                'course_id' => $enrollInCourse->getKey(),
                'enrolled_at' => now()->toDateString(),
                'status' => 'Active',
            ]);
        }

        return $student;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $academy = Academy::factory()->create();
        [$media] = $this->createPrivateMediaForAcademy($academy);

        $response = $this->get(route('media.show', $media));

        $response->assertRedirect('/login');
    }

    public function test_suspended_user_receives_403_forbidden(): void
    {
        $academy = Academy::factory()->create();
        [$media] = $this->createPrivateMediaForAcademy($academy);

        $suspendedUser = $this->createStudentInAcademy($academy);
        $suspendedUser->update(['is_active' => false]);

        $response = $this->actingAs($suspendedUser)->get(route('media.show', $media));

        $response->assertForbidden();
    }

    public function test_non_enrolled_student_in_same_academy_receives_403_forbidden(): void
    {
        $academy = Academy::factory()->create();
        [$media] = $this->createPrivateMediaForAcademy($academy);

        $nonEnrolledStudent = $this->createStudentInAcademy($academy);

        $response = $this->actingAs($nonEnrolledStudent)->get(route('media.show', $media));

        $response->assertForbidden();
    }

    public function test_enrolled_student_can_stream_full_file_and_byte_ranges(): void
    {
        $academy = Academy::factory()->create();
        $content = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        [$media, $course] = $this->createPrivateMediaForAcademy($academy, $content);

        $student = $this->createStudentInAcademy($academy, $course);

        // Full stream (200 OK)
        $fullResponse = $this->actingAs($student)->get(route('media.show', $media));
        $fullResponse->assertOk();
        $fullResponse->assertHeader('Accept-Ranges', 'bytes');
        $fullResponse->assertHeader('Content-Length', (string) strlen($content));
        $this->assertSame($content, $fullResponse->streamedContent());

        // Byte-range stream (206 Partial Content)
        $rangeResponse = $this->actingAs($student)
            ->withHeaders(['Range' => 'bytes=0-9'])
            ->get(route('media.show', $media));

        $rangeResponse->assertStatus(206);
        $rangeResponse->assertHeader('Content-Range', 'bytes 0-9/'.strlen($content));
        $rangeResponse->assertHeader('Content-Length', '10');
        $this->assertSame('0123456789', $rangeResponse->streamedContent());
    }

    public function test_invalid_and_overlapping_range_headers_return_416(): void
    {
        $academy = Academy::factory()->create();
        [$media, $course] = $this->createPrivateMediaForAcademy($academy, '0123456789');
        $student = $this->createStudentInAcademy($academy, $course);

        foreach (['bytes=0-5,10-15', 'bytes=999999-', 'bytes=10-5'] as $rangeHeader) {
            $response = $this->actingAs($student)
                ->withHeaders(['Range' => $rangeHeader])
                ->get(route('media.show', $media));

            $response->assertStatus(416);
        }
    }

    public function test_path_traversal_returns_404(): void
    {
        $academy = Academy::factory()->create();
        $course = Course::factory()->create(['academy_id' => $academy->getKey()]);
        $student = $this->createStudentInAcademy($academy, $course);

        $media = Media::create([
            'model_type' => Course::class,
            'model_id' => $course->getKey(),
            'collection' => 'videos',
            'disk' => 'private_media',
            'path' => '../../.env',
            'filename' => '.env',
            'mime_type' => 'text/plain',
            'size_bytes' => 10,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('media.show', $media));

        $response->assertNotFound();
    }

    public function test_unsafe_mime_type_forces_attachment_and_octet_stream(): void
    {
        $academy = Academy::factory()->create();
        [$media, $course] = $this->createPrivateMediaForAcademy(
            $academy,
            '<script>alert(1)</script>',
            'text/html',
            'html',
        );
        $student = $this->createStudentInAcademy($academy, $course);

        $response = $this->actingAs($student)->get(route('media.show', $media));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/octet-stream');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_private_file_is_not_accessible_via_public_storage_url(): void
    {
        $academy = Academy::factory()->create();
        [$media] = $this->createPrivateMediaForAcademy($academy, 'secret-content');

        $response = $this->get('/storage/'.$media->path);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_completed_enrollment_student_can_stream_private_media(): void
    {
        $academy = Academy::factory()->create();
        $content = 'COMPLETED_COURSE_MEDIA_STREAM';
        [$media, $course] = $this->createPrivateMediaForAcademy($academy, $content);

        $student = $this->createStudentInAcademy($academy, $course);
        $student->studentProfile->enrollments()->where('course_id', $course->id)->update([
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('media.show', $media));

        $response->assertOk();
        $this->assertSame($content, $response->streamedContent());
    }

    public function test_expired_enrollment_student_receives_403_forbidden(): void
    {
        $academy = Academy::factory()->create();
        [$media, $course] = $this->createPrivateMediaForAcademy($academy, 'EXPIRED_CONTENT');

        $student = $this->createStudentInAcademy($academy, $course);
        $student->studentProfile->enrollments()->where('course_id', $course->id)->update([
            'status' => Enrollment::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($student)->get(route('media.show', $media));

        $response->assertForbidden();
    }

    public function test_cancelled_enrollment_student_receives_403_forbidden(): void
    {
        $academy = Academy::factory()->create();
        [$media, $course] = $this->createPrivateMediaForAcademy($academy, 'CANCELLED_CONTENT');

        $student = $this->createStudentInAcademy($academy, $course);
        $student->studentProfile->enrollments()->where('course_id', $course->id)->update([
            'status' => Enrollment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('media.show', $media));

        $response->assertForbidden();
    }

    public function test_suspended_enrollment_student_receives_403_forbidden(): void
    {
        $academy = Academy::factory()->create();
        [$media, $course] = $this->createPrivateMediaForAcademy($academy, 'SUSPENDED_CONTENT');

        $student = $this->createStudentInAcademy($academy, $course);
        $student->studentProfile->enrollments()->where('course_id', $course->id)->update([
            'status' => Enrollment::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('media.show', $media));

        $response->assertForbidden();
    }
}
