<?php

namespace Tests\Feature\Operations;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Models\Media;
use App\Domain\Platform\Services\FileManagementService;
use App\Livewire\Admin\FileManagementIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class FileManagementTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    private User $admin;

    private User $trainer;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('private_media');

        $this->academy = Academy::factory()->create();

        $this->admin = User::factory()->create(['name' => 'Admin User', 'is_active' => true]);
        $this->admin->assignRole('admin');
        $this->admin->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'admin', 'status' => 'active']);

        $this->trainer = User::factory()->create(['name' => 'Trainer User', 'is_active' => true]);
        $this->trainer->assignRole('trainer');
        $this->trainer->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'trainer', 'status' => 'active']);

        $this->student = User::factory()->create(['name' => 'Student User', 'is_active' => true]);
        $this->student->assignRole('student');
        $this->student->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'student', 'status' => 'active']);

        $this->course = Course::factory()->create(['academy_id' => $this->academy->id]);
    }

    /**
     * Test valid file upload and media metadata creation.
     */
    public function test_valid_file_upload_and_metadata_creation(): void
    {
        $service = app(FileManagementService::class);
        $file = UploadedFile::fake()->create('syllabus.pdf', 500, 'application/pdf');

        $media = $service->validateAndStore(
            file: $file,
            model: $this->course,
            collection: 'lesson_materials',
            disk: 'private_media',
            uploader: $this->admin,
        );

        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame('syllabus.pdf', $media->filename);
        $this->assertSame('lesson_materials', $media->collection);
        $this->assertSame('private_media', $media->disk);
        $this->assertStringStartsWith('lesson_materials/', $media->path);
        $this->assertNotSame('syllabus.pdf', basename($media->path), 'Stored path must use randomized UUID');

        Storage::disk('private_media')->assertExists($media->path);
    }

    /**
     * Test rejection of malicious script extensions (.php, .phtml, .sh, .exe).
     */
    public function test_rejection_of_dangerous_file_extensions(): void
    {
        $service = app(FileManagementService::class);

        $dangerous = [
            'exploit.php',
            'backdoor.phtml',
            'script.phar',
            'payload.sh',
            'malware.exe',
            'shell.cgi',
        ];

        foreach ($dangerous as $filename) {
            $file = UploadedFile::fake()->create($filename, 100, 'text/plain');

            $rejected = false;
            try {
                $service->validateAndStore(
                    file: $file,
                    model: $this->course,
                    collection: 'test',
                    disk: 'private_media',
                    uploader: $this->admin,
                );
            } catch (InvalidArgumentException $e) {
                $rejected = true;
                $this->assertStringContainsString('not permitted', $e->getMessage());
            }

            $this->assertTrue($rejected, "Dangerous file [{$filename}] must be rejected.");
        }
    }

    /**
     * Test storage calculation metrics.
     */
    public function test_storage_usage_reporting(): void
    {
        $service = app(FileManagementService::class);

        Media::create([
            'model_type' => Course::class,
            'model_id' => (string) $this->course->id,
            'collection' => 'lesson_materials',
            'disk' => 'private_media',
            'path' => 'lesson_materials/file1.pdf',
            'filename' => 'file1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1048576, // 1 MB
            'created_at' => now(),
        ]);

        Media::create([
            'model_type' => Course::class,
            'model_id' => (string) $this->course->id,
            'collection' => 'videos',
            'disk' => 'private_media',
            'path' => 'videos/file2.mp4',
            'filename' => 'file2.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 2097152, // 2 MB
            'created_at' => now(),
        ]);

        $stats = $service->getStorageUsage();

        $this->assertSame(3145728, $stats['total_managed_bytes']);
        $this->assertSame('3 MB', $stats['total_managed_formatted']);
        $this->assertSame(2, $stats['total_files']);
    }

    /**
     * Test admin can view files list and delete a media file.
     */
    public function test_admin_file_management_livewire_component(): void
    {
        $media = Media::create([
            'model_type' => Course::class,
            'model_id' => (string) $this->course->id,
            'collection' => 'assignments',
            'disk' => 'private_media',
            'path' => 'assignments/test.pdf',
            'filename' => 'test_assignment.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 50000,
            'created_at' => now(),
        ]);

        Storage::disk('private_media')->put('assignments/test.pdf', 'dummy content');

        $this->actingAs($this->admin);

        Livewire::test(FileManagementIndex::class)
            ->assertSee('test_assignment.pdf')
            ->assertSee('assignments')
            ->call('deleteFile', $media->id);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('private_media')->assertMissing('assignments/test.pdf');
    }

    /**
     * Test non-admin authorization boundaries (students and trainers cannot access /admin/files).
     */
    public function test_non_admin_cannot_access_file_management(): void
    {
        // Student blocked
        $this->actingAs($this->student);
        $this->get('/admin/files')->assertForbidden();

        // Trainer blocked
        $this->actingAs($this->trainer);
        $this->get('/admin/files')->assertForbidden();

        // Guest redirected to login
        auth()->logout();
        $this->get('/admin/files')->assertRedirect('/login');
    }
}
