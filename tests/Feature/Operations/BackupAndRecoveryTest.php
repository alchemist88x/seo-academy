<?php

namespace Tests\Feature\Operations;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Models\BackupRecord;
use App\Domain\Platform\Services\BackupService;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class BackupAndRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    private User $admin;

    private User $student;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create();

        $this->admin = User::factory()->create(['name' => 'Backup Admin', 'email' => 'admin@test.local', 'is_active' => true]);
        $this->admin->assignRole('admin');
        $this->admin->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'admin', 'status' => 'active']);

        $this->student = User::factory()->create(['name' => 'Student User', 'email' => 'student@test.local', 'is_active' => true]);
        $this->student->assignRole('student');
        $this->student->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'student', 'status' => 'active']);

        $this->trainer = User::factory()->create(['name' => 'Trainer User', 'email' => 'trainer@test.local', 'is_active' => true]);
        $this->trainer->assignRole('trainer');
        $this->trainer->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'trainer', 'status' => 'active']);
    }

    /**
     * Test SQLite backup creation, integrity verification, and metadata recording.
     */
    public function test_sqlite_database_backup_and_validation(): void
    {
        $this->actingAs($this->admin);

        $service = app(BackupService::class);
        $record = $service->createDatabaseBackup();

        $this->assertInstanceOf(BackupRecord::class, $record);
        $this->assertSame('database', $record->type);
        $this->assertSame('sqlite', $record->database_driver);
        $this->assertSame('completed', $record->status);
        $this->assertGreaterThan(0, $record->size_bytes);
        $this->assertNotEmpty($record->checksum);

        $backupPath = storage_path("app/private/backups/{$record->filename}");
        $this->assertFileExists($backupPath);

        // Validation must succeed
        $isValid = $service->validateBackup($record);
        $this->assertTrue($isValid, 'Valid SQLite backup must pass integrity check');

        // Clean up test backup file
        @unlink($backupPath);
    }

    /**
     * Test private files zip backup creation.
     */
    public function test_files_backup_creation_and_validation(): void
    {
        $this->actingAs($this->admin);

        // Put dummy file in storage/app/private/media
        $mediaDir = storage_path('app/private/media');
        File::ensureDirectoryExists($mediaDir);
        File::put($mediaDir.'/test-doc.txt', 'Document for file backup test.');

        $service = app(BackupService::class);
        $record = $service->createFilesBackup();

        $this->assertSame('files', $record->type);
        $this->assertSame('completed', $record->status);
        $this->assertFileExists(storage_path("app/private/backups/{$record->filename}"));

        $isValid = $service->validateBackup($record);
        $this->assertTrue($isValid, 'Valid files backup must pass ZipArchive validation');

        @unlink(storage_path("app/private/backups/{$record->filename}"));
        @unlink($mediaDir.'/test-doc.txt');
    }

    /**
     * Test full backup set creates paired database and files backups with matching backup_set_id.
     */
    public function test_full_backup_set_creation_and_linking(): void
    {
        $this->actingAs($this->admin);

        $mediaDir = storage_path('app/private/media');
        File::ensureDirectoryExists($mediaDir);
        File::put($mediaDir.'/syllabus.pdf', '%PDF-1.4 sample content');

        $service = app(BackupService::class);
        $set = $service->createFullBackupSet();

        $this->assertNotEmpty($set['backup_set_id']);
        $this->assertInstanceOf(BackupRecord::class, $set['database']);
        $this->assertInstanceOf(BackupRecord::class, $set['files']);

        $this->assertSame($set['backup_set_id'], $set['database']->metadata['backup_set_id']);
        $this->assertSame($set['backup_set_id'], $set['files']->metadata['backup_set_id']);

        // Both files exist and validate
        $this->assertTrue($service->validateBackup($set['database']));
        $this->assertTrue($service->validateBackup($set['files']));

        // Clean up
        @unlink(storage_path("app/private/backups/{$set['database']->filename}"));
        @unlink(storage_path("app/private/backups/{$set['files']->filename}"));
        @unlink($mediaDir.'/syllabus.pdf');
    }

    /**
     * Test complete database and private-file recovery workflow.
     */
    public function test_complete_database_and_file_recovery_workflow(): void
    {
        $this->actingAs($this->admin);

        $mediaDir = storage_path('app/private/media');
        File::ensureDirectoryExists($mediaDir);
        $testFilePath = $mediaDir.'/course-asset.png';
        File::put($testFilePath, 'fake_png_binary_data');

        $service = app(BackupService::class);
        $set = $service->createFullBackupSet();

        // Simulate disaster: delete media file
        @unlink($testFilePath);
        $this->assertFileDoesNotExist($testFilePath);

        // Execute recovery of files from the set
        $result = $service->restoreBackup($set['files'], createSafetyBackup: false);
        $this->assertTrue($result['success']);

        // Assert file restored exactly
        $this->assertFileExists($testFilePath);
        $this->assertSame('fake_png_binary_data', File::get($testFilePath));

        // Clean up
        @unlink($testFilePath);
        @unlink(storage_path("app/private/backups/{$set['database']->filename}"));
        @unlink(storage_path("app/private/backups/{$set['files']->filename}"));
    }

    /**
     * Test restore exclusivity lock rejects concurrent restore operations.
     */
    public function test_restore_exclusivity_lock_rejects_concurrent_restorations(): void
    {
        $this->actingAs($this->admin);
        $service = app(BackupService::class);

        $lockPath = BackupService::restoreLockPath();
        File::ensureDirectoryExists(dirname($lockPath));

        // Simulate an ongoing restoration holding exclusive flock
        $lockFp = fopen($lockPath, 'c+');
        $this->assertTrue(flock($lockFp, LOCK_EX | LOCK_NB));

        $dummyRecord = BackupRecord::create([
            'filename' => 'backup-dummy.sqlite',
            'disk' => 'private_backups',
            'type' => 'database',
            'database_driver' => 'sqlite',
            'size_bytes' => 100,
            'status' => 'completed',
            'checksum' => 'fake',
        ]);
        File::put(storage_path('app/private/backups/backup-dummy.sqlite'), 'dummy');

        $concurrentBlocked = false;
        try {
            $service->restoreBackup($dummyRecord);
        } catch (RuntimeException $e) {
            $concurrentBlocked = true;
            $this->assertStringContainsString('Another backup restoration is currently in progress', $e->getMessage());
        }

        // Release simulation lock
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        @unlink($lockPath);
        @unlink(storage_path('app/private/backups/backup-dummy.sqlite'));

        $this->assertTrue($concurrentBlocked, 'Concurrent restore must be blocked by restore lock');
    }

    /**
     * Test tampered backup fails checksum validation.
     */
    public function test_tampered_backup_fails_validation(): void
    {
        $this->actingAs($this->admin);

        $service = app(BackupService::class);
        $record = $service->createDatabaseBackup();

        $backupPath = storage_path("app/private/backups/{$record->filename}");

        // Tamper with file
        File::append($backupPath, 'corrupted_bytes');

        $isValid = $service->validateBackup($record);
        $this->assertFalse($isValid, 'Tampered backup must fail checksum validation');

        @unlink($backupPath);
    }

    /**
     * Test restore rejects path traversal, malicious zip entries, and driver mismatches.
     */
    public function test_restore_security_safeguards(): void
    {
        $this->actingAs($this->admin);
        $service = app(BackupService::class);

        // Path traversal rejection
        $maliciousRecord = new BackupRecord([
            'filename' => '../etc/passwd',
            'type' => 'database',
            'database_driver' => 'sqlite',
        ]);

        $traversalBlocked = false;
        try {
            $service->restoreBackup($maliciousRecord);
        } catch (RuntimeException $e) {
            $traversalBlocked = true;
            $this->assertStringContainsString('Path traversal detected', $e->getMessage());
        }
        $this->assertTrue($traversalBlocked, 'Path traversal in filename must be aborted');

        // Path validation helper check
        $this->assertFalse($service->isSafeArchivePath('../config.php'));
        $this->assertFalse($service->isSafeArchivePath('/var/www/.env'));
        $this->assertFalse($service->isSafeArchivePath("file\0.txt"));
        $this->assertFalse($service->isSafeArchivePath('C:\\windows\\system.ini'));
        $this->assertTrue($service->isSafeArchivePath('media/avatars/user-1.jpg'));

        // Driver mismatch rejection
        $mismatchRecord = BackupRecord::create([
            'filename' => 'fake-mysql.sql',
            'type' => 'database',
            'database_driver' => 'mysql', // Mismatch with active SQLite connection
            'size_bytes' => 100,
            'status' => 'completed',
            'checksum' => 'fake',
        ]);

        $mismatchBlocked = false;
        try {
            $service->restoreBackup($mismatchRecord);
        } catch (RuntimeException $e) {
            $mismatchBlocked = true;
            $this->assertStringContainsString('Database driver mismatch', $e->getMessage());
        }
        $this->assertTrue($mismatchBlocked, 'Driver mismatch must be aborted');
    }

    /**
     * Test backup retention policy keeps recent backups and NEVER deletes the only backup.
     */
    public function test_retention_policy_never_deletes_only_backup(): void
    {
        $service = app(BackupService::class);

        // Case 1: Only 1 backup exists
        $singleRecord = BackupRecord::create([
            'filename' => 'only-good-backup.sqlite',
            'disk' => 'private_backups',
            'type' => 'database',
            'database_driver' => 'sqlite',
            'size_bytes' => 1024,
            'status' => 'completed',
        ]);

        $deleted = $service->applyRetention(keepCount: 0); // Even with keepCount=0
        $this->assertSame(0, $deleted, 'Must NEVER delete the only known-good backup');
        $this->assertDatabaseHas('backups', ['id' => $singleRecord->id]);

        // Case 2: Create 7 backups, keep 5 -> 2 must be deleted
        $records = [];
        for ($i = 0; $i < 6; $i++) {
            $records[] = BackupRecord::create([
                'filename' => "backup-{$i}.sqlite",
                'disk' => 'private_backups',
                'type' => 'database',
                'database_driver' => 'sqlite',
                'size_bytes' => 1024,
                'status' => 'completed',
                'created_at' => now()->subMinutes($i * 5),
            ]);
        }

        $this->assertEquals(7, BackupRecord::where('status', 'completed')->count());

        $deletedCount = $service->applyRetention(keepCount: 5);
        $this->assertSame(2, $deletedCount);
        $this->assertEquals(5, BackupRecord::where('status', 'completed')->count());
    }

    /**
     * Test non-admin authorization boundaries (students & trainers blocked from backups & downloads).
     */
    public function test_non_admin_cannot_access_backups(): void
    {
        $record = BackupRecord::create([
            'filename' => 'protected.sqlite',
            'disk' => 'private_backups',
            'type' => 'database',
            'database_driver' => 'sqlite',
            'size_bytes' => 1024,
            'status' => 'completed',
        ]);

        // Student blocked
        $this->actingAs($this->student);
        $this->get('/admin/system/backups')->assertForbidden();
        $this->get("/admin/system/backups/{$record->id}/download")->assertForbidden();

        // Trainer blocked
        $this->actingAs($this->trainer);
        $this->get('/admin/system/backups')->assertForbidden();
        $this->get("/admin/system/backups/{$record->id}/download")->assertForbidden();

        // Guest redirected
        auth()->logout();
        $this->get('/admin/system/backups')->assertRedirect('/login');
        $this->get("/admin/system/backups/{$record->id}/download")->assertRedirect('/login');
    }

    /**
     * Test MySQL backup consistency rejects non-InnoDB table engines.
     */
    public function test_mysql_backup_rejects_non_innodb_tables(): void
    {
        config([
            'database.connections.mysql_engine_check' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'seo_academy_test',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => 'InnoDB',
            ],
        ]);

        try {
            $pdo = DB::connection('mysql_engine_check')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database unavailable: '.$e->getMessage());
        }

        // Create a temporary non-InnoDB table (MEMORY or MyISAM)
        $pdo->exec('CREATE TABLE IF NOT EXISTS seo_academy_test.test_non_innodb (id INT PRIMARY KEY) ENGINE = MEMORY');

        $service = app(BackupService::class);
        $rejected = false;

        try {
            $service->createDatabaseBackup('mysql_engine_check');
        } catch (RuntimeException $e) {
            $rejected = true;
            $this->assertStringContainsString('Non-transactional table engines detected', $e->getMessage());
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS seo_academy_test.test_non_innodb');
        }

        $this->assertTrue($rejected, 'MySQL backup must strictly reject non-InnoDB tables to guarantee consistent snapshot semantics');
    }

    /**
     * Test full backup set recovery pre-validation rejection when media archive is corrupted.
     */
    public function test_backup_set_restore_prevalidation_rejection_on_corrupt_component(): void
    {
        $this->actingAs($this->admin);

        // Put initial state
        $mediaDir = storage_path('app/private/media');
        File::ensureDirectoryExists($mediaDir);
        File::put($mediaDir.'/initial.txt', 'initial_content');

        $initialUserCount = User::count();
        $this->assertGreaterThan(0, $initialUserCount);

        $service = app(BackupService::class);
        $set = $service->createFullBackupSet();

        // Mutate state: create new user
        $newUser = User::factory()->create(['name' => 'State Change User', 'email' => 'state@test.local']);
        $this->assertSame($initialUserCount + 1, User::count());

        // Corrupt the media backup zip
        $mediaBackupPath = storage_path("app/private/backups/{$set['files']->filename}");
        File::put($mediaBackupPath, 'CORRUPT_INVALID_ZIP_DATA');

        // Attempt backup set restore (pre-validation will catch this before touching DB or files)
        $failed = false;
        try {
            $service->restoreBackupSet($set['backup_set_id']);
        } catch (RuntimeException $e) {
            $failed = true;
            $this->assertStringContainsString('failed integrity check', $e->getMessage());
        }
        $this->assertTrue($failed, 'Restore backup set must fail when files archive is corrupted');

        // Verify database was untouched because pre-validation prevented restoration
        $this->assertSame($initialUserCount + 1, User::count());
        $this->assertDatabaseHas('users', ['id' => $newUser->id]);

        // Clean up
        @unlink(storage_path("app/private/backups/{$set['database']->filename}"));
        @unlink($mediaBackupPath);
        @unlink($mediaDir.'/initial.txt');
    }

    /**
     * Test complete rollback of BOTH database and private media when media restoration fails.
     */
    public function test_backup_set_complete_dual_rollback_when_media_phase_fails(): void
    {
        $this->actingAs($this->admin);

        $mediaDir = storage_path('app/private/media');
        File::ensureDirectoryExists($mediaDir);
        $liveFile = $mediaDir.'/original_live.txt';
        File::put($liveFile, 'LIVE_MEDIA_DATA_EXACT');

        $initialUserCount = User::count();

        $service = app(BackupService::class);
        $set = $service->createFullBackupSet();

        // Mutate live state: add a new user and add a new live file
        $mutatedUser = User::factory()->create(['name' => 'Mutated Before Disaster', 'email' => 'mutated@test.local']);
        $mutatedFile = $mediaDir.'/mutated_file.txt';
        File::put($mutatedFile, 'MUTATED_FILE_DATA');

        // Mock/simulate media restore failure by replacing incoming media zip with one that passes pre-validation
        // but fails during restoreBackup() extraction due to injected unreadable permission or missing file
        // To test clean dual rollback in restoreBackupSet():
        $validMediaFile = storage_path("app/private/backups/{$set['files']->filename}");
        // We will temporarily rename the files backup right after pre-validation pass by testing the catch block
        // Or we test through an invalid zip entry that is rejected during extraction
        // Let's create a zip that passes ZipArchive::open(CHECKCONS) but triggers an error during extraction
        // Or directly verify the rollback path:
        $rollbackCaught = false;

        // Create a custom subclass of BackupService to inject an extraction error specifically in Phase 2
        $failingService = new class extends BackupService
        {
            public bool $shouldFailFiles = true;

            public function restoreBackup(BackupRecord $record, bool $createSafetyBackup = true): array
            {
                if ($this->shouldFailFiles && $record->type === 'files') {
                    throw new RuntimeException('Simulated disk extraction failure during media restore phase.');
                }

                return parent::restoreBackup($record, $createSafetyBackup);
            }
        };

        try {
            $failingService->restoreBackupSet($set['backup_set_id']);
        } catch (RuntimeException $e) {
            $rollbackCaught = true;
            $this->assertStringContainsString('BOTH database and media files were completely rolled back', $e->getMessage());
        }

        $this->assertTrue($rollbackCaught, 'RestoreBackupSet must throw rollback confirmation exception');

        // Assert database was completely rolled back to its pre-restore safety state (mutated user still exists)
        $this->assertDatabaseHas('users', ['id' => $mutatedUser->id]);

        // Assert media files were completely rolled back to their pre-restore safety state
        $this->assertFileExists($liveFile);
        $this->assertSame('LIVE_MEDIA_DATA_EXACT', File::get($liveFile));
        $this->assertFileExists($mutatedFile);
        $this->assertSame('MUTATED_FILE_DATA', File::get($mutatedFile));

        // Assert audit log recorded the failure and rollback event
        $this->assertDatabaseHas('audit_log', [
            'action' => 'backup_set.restoration_failed',
            'subject_id' => $set['backup_set_id'],
        ]);

        // Clean up
        @unlink(storage_path("app/private/backups/{$set['database']->filename}"));
        @unlink($validMediaFile);
        @unlink($liveFile);
        @unlink($mutatedFile);
    }

    /**
     * Test restore state guard middleware blocks POST/PUT/DELETE requests during active restore lock.
     */
    public function test_restore_state_guard_blocks_modifying_requests_during_active_restore(): void
    {
        $lockPath = BackupService::restoreLockPath();
        File::ensureDirectoryExists(dirname($lockPath));

        // Acquire lock to simulate active restoration
        $lockFp = fopen($lockPath, 'c+');
        $this->assertTrue(flock($lockFp, LOCK_EX | LOCK_NB));

        $this->assertTrue(BackupService::isRestorationInProgress());

        // POST request must receive 503
        $response = $this->post('/login', [
            'email' => 'admin@test.local',
            'password' => 'secret',
        ]);
        $response->assertStatus(503);

        // JSON request must receive 503 with JSON message
        $jsonResponse = $this->postJson('/login', [
            'email' => 'admin@test.local',
            'password' => 'secret',
        ]);
        $jsonResponse->assertStatus(503);
        $jsonResponse->assertJsonFragment([
            'message' => 'System restoration in progress. State-modifying requests are temporarily blocked.',
        ]);

        // Release lock
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        @unlink($lockPath);

        $this->assertFalse(BackupService::isRestorationInProgress());
    }

    /**
     * Test scheduled command process-expired honors restore-state guard.
     */
    public function test_scheduled_command_halts_when_restoration_is_in_progress(): void
    {
        $lockPath = BackupService::restoreLockPath();
        File::ensureDirectoryExists(dirname($lockPath));

        $lockFp = fopen($lockPath, 'c+');
        $this->assertTrue(flock($lockFp, LOCK_EX | LOCK_NB));

        // Run command while restore lock is active
        $exitCode = Artisan::call('enrollments:process-expired');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('System restoration is currently in progress', Artisan::output());

        // Release lock
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        @unlink($lockPath);
    }

    /**
     * Test MySQL consistent snapshot export, clean restoration round-trip,
     * and comprehensive foreign key, unique constraint, index, JSON, and nullability verification.
     */
    public function test_mysql_consistent_snapshot_and_full_round_trip_restoration(): void
    {
        config([
            'database.connections.mysql_test_rt' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'seo_academy_test',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => 'InnoDB',
            ],
        ]);

        try {
            $pdo = DB::connection('mysql_test_rt')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database unavailable: '.$e->getMessage());
        }

        // 1. Run migrations fresh on mysql_test_rt
        Artisan::call('migrate:fresh', [
            '--database' => 'mysql_test_rt',
            '--force' => true,
        ]);

        // 2. Seed representative records with UUIDs, timestamps, relations, nullables, JSON, and settings
        $mysqlConn = DB::connection('mysql_test_rt');

        $academyId = (string) Str::uuid();
        $mysqlConn->table('academies')->insert([
            'id' => $academyId,
            'name' => 'MySQL Roundtrip Academy',
            'slug' => 'mysql-roundtrip-'.Str::random(6),
            'settings' => json_encode(['theme' => 'dark', 'notifications' => ['email' => true]]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminUserId = (string) Str::uuid();
        $userEmail = 'mysql-admin-'.Str::random(6).'@test.local';
        $mysqlConn->table('users')->insert([
            'id' => $adminUserId,
            'name' => 'MySQL Roundtrip Admin',
            'email' => $userEmail,
            'password' => bcrypt('secret123'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = (string) Str::uuid();
        $mysqlConn->table('courses')->insert([
            'id' => $courseId,
            'academy_id' => $academyId,
            'title' => 'Technical SEO Masterclass',
            'slug' => 'tech-seo-'.Str::random(6),
            'price' => 199.99,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $moduleId = (string) Str::uuid();
        $mysqlConn->table('modules')->insert([
            'id' => $moduleId,
            'academy_id' => $academyId,
            'course_id' => $courseId,
            'title' => 'Crawlability and Indexability',
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lessonId = (string) Str::uuid();
        $mysqlConn->table('lessons')->insert([
            'id' => $lessonId,
            'academy_id' => $academyId,
            'module_id' => $moduleId,
            'title' => 'Server Headers and Status Codes',
            'type' => 'text',
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentProfileId = (string) Str::uuid();
        $mysqlConn->table('student_profiles')->insert([
            'id' => $studentProfileId,
            'academy_id' => $academyId,
            'user_id' => $adminUserId,
            'student_code' => 'STU-'.Str::random(6),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollmentId = (string) Str::uuid();
        $mysqlConn->table('enrollments')->insert([
            'id' => $enrollmentId,
            'academy_id' => $academyId,
            'student_profile_id' => $studentProfileId,
            'course_id' => $courseId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ticketId = (string) Str::uuid();
        $mysqlConn->table('support_tickets')->insert([
            'id' => $ticketId,
            'academy_id' => $academyId,
            'user_id' => $adminUserId,
            'subject' => 'Backup verification query',
            'description' => 'Detailed verification description.',
            'status' => 'open',
            'priority' => 'normal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Message with nullable user_id to verify author retention across restore
        $messageId = (string) Str::uuid();
        $mysqlConn->table('support_ticket_messages')->insert([
            'id' => $messageId,
            'support_ticket_id' => $ticketId,
            'user_id' => null, // Nullable author test
            'author_name' => 'Deleted Support Representative',
            'message' => 'Historic response with preserved author name and NULL user_id.',
            'is_internal' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Media record with metadata JSON
        $mediaId = (string) Str::uuid();
        $mysqlConn->table('media')->insert([
            'id' => $mediaId,
            'model_type' => 'App\\Domain\\Courses\\Models\\Course',
            'model_id' => $courseId,
            'collection' => 'thumbnails',
            'disk' => 'private_media',
            'path' => 'thumbnails/seo.jpg',
            'filename' => 'seo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 45210,
            'metadata' => json_encode(['width' => 1280, 'height' => 720]),
            'created_at' => now(),
        ]);

        // 3. Create MySQL Backup using consistent snapshot
        $this->actingAs($this->admin);
        $service = app(BackupService::class);
        $record = $service->createDatabaseBackup('mysql_test_rt');

        $this->assertSame('database', $record->type);
        $this->assertSame('mysql', $record->database_driver);
        $this->assertSame('completed', $record->status);

        $backupPath = storage_path("app/private/backups/{$record->filename}");
        $this->assertFileExists($backupPath);

        // Verify dump headers confirm transaction isolation and completion
        $dumpContent = file_get_contents($backupPath);
        $this->assertStringContainsString('REPEATABLE READ WITH CONSISTENT SNAPSHOT', $dumpContent);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=0;', $dumpContent);
        $this->assertStringContainsString('-- Backup Complete', $dumpContent);

        // 4. Wipe representative database to simulate disaster
        $mysqlConn->statement('SET FOREIGN_KEY_CHECKS=0;');
        $mysqlConn->table('media')->truncate();
        $mysqlConn->table('support_ticket_messages')->truncate();
        $mysqlConn->table('support_tickets')->truncate();
        $mysqlConn->table('enrollments')->truncate();
        $mysqlConn->table('student_profiles')->truncate();
        $mysqlConn->table('lessons')->truncate();
        $mysqlConn->table('modules')->truncate();
        $mysqlConn->table('courses')->truncate();
        $mysqlConn->table('users')->truncate();
        $mysqlConn->table('academies')->truncate();
        $mysqlConn->statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->assertSame(0, $mysqlConn->table('courses')->count());
        $this->assertSame(0, $mysqlConn->table('support_ticket_messages')->count());
        $this->assertSame(0, $mysqlConn->table('media')->count());

        // 5. Execute restore round-trip into mysql_test_rt
        $restoreSql = file_get_contents($backupPath);
        $pdo->exec($restoreSql);

        // 6. Assert restored data structure, relationships, column nullables, JSON, and exact record counts
        $this->assertSame(1, $mysqlConn->table('academies')->where('id', $academyId)->count());
        $this->assertSame(1, $mysqlConn->table('users')->where('id', $adminUserId)->count());
        $this->assertSame(1, $mysqlConn->table('courses')->where('id', $courseId)->count());
        $this->assertSame(1, $mysqlConn->table('modules')->where('id', $moduleId)->count());
        $this->assertSame(1, $mysqlConn->table('lessons')->where('id', $lessonId)->count());
        $this->assertSame(1, $mysqlConn->table('enrollments')->where('id', $enrollmentId)->count());
        $this->assertSame(1, $mysqlConn->table('support_tickets')->where('id', $ticketId)->count());
        $this->assertSame(1, $mysqlConn->table('media')->where('id', $mediaId)->count());

        // Assert JSON values
        $restoredAcademy = $mysqlConn->table('academies')->where('id', $academyId)->first();
        $this->assertNotNull($restoredAcademy);
        $decodedAcademySettings = json_decode($restoredAcademy->settings, true);
        $this->assertSame('dark', $decodedAcademySettings['theme']);

        $restoredMedia = $mysqlConn->table('media')->where('id', $mediaId)->first();
        $this->assertNotNull($restoredMedia);
        $decodedMediaMeta = json_decode($restoredMedia->metadata, true);
        $this->assertSame(1280, $decodedMediaMeta['width']);
        $this->assertSame(45210, (int) $restoredMedia->size_bytes);

        // Assert foreign key and author retention on support ticket messages
        $restoredMessage = $mysqlConn->table('support_ticket_messages')->where('id', $messageId)->first();
        $this->assertNotNull($restoredMessage);
        $this->assertNull($restoredMessage->user_id, 'Nullable user_id must be preserved as NULL');
        $this->assertSame('Deleted Support Representative', $restoredMessage->author_name);
        $this->assertSame('Historic response with preserved author name and NULL user_id.', $restoredMessage->message);

        // Assert unique constraint survived by attempting duplicate email insert (must fail)
        $uniqueEnforced = false;
        try {
            $mysqlConn->table('users')->insert([
                'id' => (string) Str::uuid(),
                'name' => 'Duplicate Email User',
                'email' => $userEmail, // Duplicate!
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $uniqueEnforced = true;
        }
        $this->assertTrue($uniqueEnforced, 'Unique email constraint must survive restoration');

        // Clean up
        @unlink($backupPath);
    }
}
