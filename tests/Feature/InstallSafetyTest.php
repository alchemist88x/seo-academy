<?php

namespace Tests\Feature;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Services\DatabaseInstallerService;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class InstallSafetyTest extends TestCase
{
    private string $tempDir;

    private string $tempEnvPath;

    private string $tempLockPath;

    private string $tempSqlitePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('framework/testing/safety-'.uniqid('', true));
        File::ensureDirectoryExists($this->tempDir);

        $this->tempEnvPath = $this->tempDir.DIRECTORY_SEPARATOR.'.env';
        $this->tempLockPath = $this->tempDir.DIRECTORY_SEPARATOR.'installed.lock';
        $this->tempSqlitePath = $this->tempDir.DIRECTORY_SEPARATOR.'test-safety.sqlite';

        File::put($this->tempEnvPath, "APP_NAME=\"SEO Academy\"\nDB_CONNECTION=sqlite\n");

        $lockPath = $this->tempLockPath;
        $sqlitePath = $this->tempSqlitePath;

        $this->app->instance(
            DatabaseInstallerService::class,
            new class($this->tempEnvPath, $lockPath, $sqlitePath) extends DatabaseInstallerService
            {
                public function __construct(
                    string $envPath,
                    private readonly string $customLockPath,
                    private readonly string $customSqlitePath,
                ) {
                    parent::__construct($envPath, $customLockPath, $customSqlitePath);
                }

                public function lockFilePath(): string
                {
                    return $this->customLockPath;
                }

                public function sqliteDatabasePath(): string
                {
                    return $this->customSqlitePath;
                }
            }
        );
    }

    protected function tearDown(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::purge('mysql');
        DB::setDefaultConnection('sqlite');

        if (is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * Requirement 1: Driver-specific requirements for SQLite without pdo_mysql.
     */
    public function test_sqlite_selected_does_not_require_pdo_mysql(): void
    {
        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);
        $reqs = $service->checkSystemRequirements('sqlite');

        $this->assertTrue($reqs['extensions']['pdo_sqlite']['required']);
        $this->assertFalse($reqs['extensions']['pdo_mysql']['required']);
        $this->assertTrue($reqs['extensions']['pdo_mysql']['ok']);
        $this->assertSame('sqlite', $reqs['driver']);
    }

    /**
     * Requirement 1: Driver-specific requirements for MySQL without pdo_sqlite.
     */
    public function test_mysql_selected_does_not_require_pdo_sqlite(): void
    {
        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);
        $reqs = $service->checkSystemRequirements('mysql');

        $this->assertTrue($reqs['extensions']['pdo_mysql']['required']);
        $this->assertFalse($reqs['extensions']['pdo_sqlite']['required']);
        $this->assertTrue($reqs['extensions']['pdo_sqlite']['ok']);
        $this->assertSame('mysql', $reqs['driver']);
    }

    /**
     * Test 1: An unrelated database containing coincidentally matching Laravel table names.
     */
    public function test_unrelated_database_with_coincidental_laravel_tables_is_rejected(): void
    {
        $pdo = new PDO('sqlite:'.$this->tempSqlitePath);
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration VARCHAR(255), batch INTEGER)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        $pdo->exec('CREATE TABLE cache (key VARCHAR(255) PRIMARY KEY, value TEXT)');

        // Insert foreign migrations from another generic Laravel app
        $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2014_10_12_000000_create_users_table', 1)");
        $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2019_12_14_000001_create_personal_access_tokens_table', 1)");

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);
        $inspection = $service->inspectDatabase($pdo, 'sqlite');

        $this->assertSame('unrelated_database', $inspection['status']);
        $this->assertStringContainsString('unrelated application', $inspection['message']);

        $check = $service->testSqliteConnection($this->tempSqlitePath);
        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('unrelated application', $check['message']);
    }

    /**
     * Test 2: An existing Academy record with a missing installation marker.
     */
    public function test_existing_academy_with_missing_installation_marker_is_ambiguous_and_blocks_install(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->tempSqlitePath,
        ]);
        DB::purge('sqlite');

        // Migrate and seed roles & create academy without marker
        Artisan::call('migrate', ['--force' => true]);
        DB::table('academies')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Legacy Academy',
            'slug' => 'legacy-academy',
            'support_email' => 'legacy@academy.test',
            'timezone' => 'UTC',
            'locale' => 'en',
            'is_active' => 1,
            'settings' => json_encode(['theme' => 'light']), // Notice: no installed_at marker!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);
        $status = $service->getDatabaseInstallationStatus();

        $this->assertSame('ambiguous', $status['status']);
        $this->assertStringContainsString('installation marker is missing', $status['message']);

        // Fail-closed: isInstalled must return true to block installation
        $this->assertTrue($service->isInstalled());

        // Lock file must NOT be regenerated for ambiguous state
        $this->assertFileDoesNotExist($this->tempLockPath);
    }

    /**
     * Test 3: An installation marker with a missing Academy record.
     */
    public function test_installation_marker_with_missing_academy_is_ambiguous_and_blocks_install(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->tempSqlitePath,
        ]);
        DB::purge('sqlite');

        Artisan::call('migrate', ['--force' => true]);

        // Insert marker in settings without an academy
        DB::table('settings')->insert([
            'id' => (string) Str::uuid(),
            'group' => 'system',
            'key' => 'installed_at',
            'value' => now()->toIso8601String(),
            'type' => 'string',
            'is_public' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);
        $status = $service->getDatabaseInstallationStatus();

        $this->assertSame('ambiguous', $status['status']);
        $this->assertStringContainsString('Academy record is missing', $status['message']);

        // Fail-closed
        $this->assertTrue($service->isInstalled());
        $this->assertFileDoesNotExist($this->tempLockPath);
    }

    /**
     * Test 4: An unavailable database with a missing lock file.
     */
    public function test_unavailable_configured_database_with_missing_lock_is_fail_closed(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '192.0.2.1', // Non-routable TEST-NET-1 IP
            'database.connections.mysql.port' => 3306,
            'database.connections.mysql.database' => 'unreachable_db',
            'database.connections.mysql.username' => 'user',
            'database.connections.mysql.password' => 'pass',
            'database.connections.mysql.options' => [
                PDO::ATTR_TIMEOUT => 1,
            ],
        ]);
        DB::purge('mysql');

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);
        $status = $service->getDatabaseInstallationStatus();

        $this->assertSame('database_unavailable', $status['status']);

        // Fail-closed: database connection failure must NEVER be interpreted as an uninstalled application
        $this->assertTrue($service->isInstalled());
        $this->assertFileDoesNotExist($this->tempLockPath);
    }

    /**
     * Test 5: An interrupted installation containing ambiguous administrator records.
     */
    public function test_interrupted_installation_with_ambiguous_admin_records_halts_recovery(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->tempSqlitePath,
        ]);
        DB::purge('sqlite');

        Artisan::call('migrate', ['--force' => true]);

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);
        $service->persistInstallationIdentity(new PDO('sqlite:'.$this->tempSqlitePath), 'sqlite', 'in_progress');

        // Create an existing user with a different email
        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Existing Operator',
            'email' => 'operator@other-domain.test',
            'password' => Hash::make('SecretPass123!'),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);

        // Attempting to install with an admin email that doesn't match the existing user must halt
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already contains user accounts that do not match');

        $service->executeCompleteInstallation(
            dbConfig: ['driver' => 'sqlite'],
            academyConfig: [
                'name' => 'New Academy',
                'slug' => 'new-academy',
                'support_email' => 'support@new.test',
            ],
            adminConfig: [
                'name' => 'New Administrator',
                'email' => 'different-admin@new.test',
                'password' => 'NewPass123!@#',
            ]
        );
    }

    /**
     * Test 6: Retry does not overwrite an existing administrator's credentials or status.
     */
    public function test_retry_does_not_overwrite_existing_administrator_credentials_or_status(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->tempSqlitePath,
        ]);
        DB::purge('sqlite');

        Artisan::call('migrate', ['--force' => true]);

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);
        $service->persistInstallationIdentity(new PDO('sqlite:'.$this->tempSqlitePath), 'sqlite', 'in_progress');

        // Pre-create the admin user from an interrupted installation attempt
        $userId = (string) Str::uuid();
        $originalHash = Hash::make('OriginalAdminPass123!@#');
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Original Admin Name',
            'email' => 'admin@original.test',
            'password' => $originalHash,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);

        $dbConfig = ['driver' => 'sqlite'];
        $academyConfig = [
            'name' => 'Original Academy',
            'slug' => 'original-academy',
            'support_email' => 'support@original.test',
        ];

        // Retry with same email and matching password, but different name
        $retryResult = $service->executeCompleteInstallation(
            $dbConfig,
            $academyConfig,
            [
                'name' => 'Overwritten Name Attempt',
                'email' => 'admin@original.test',
                'password' => 'OriginalAdminPass123!@#',
            ]
        );

        $this->assertTrue($retryResult['ok']);

        // Assert user credentials, name, and active status were strictly preserved and NOT overwritten
        $pdo = new PDO('sqlite:'.$this->tempSqlitePath);
        $updatedUser = $pdo->query("SELECT * FROM users WHERE email = 'admin@original.test'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Original Admin Name', $updatedUser['name']);
        $this->assertSame($originalHash, $updatedUser['password']);
        $this->assertSame(1, (int) $updatedUser['is_active']);

        // Now test that retry with wrong password halts recovery
        // Setup another interrupted test DB
        $wrongPassPath = $this->tempDir.DIRECTORY_SEPARATOR.'wrong-pass.sqlite';
        config(['database.connections.sqlite.database' => $wrongPassPath]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
        $service->persistInstallationIdentity(new PDO('sqlite:'.$wrongPassPath), 'sqlite', 'in_progress');
        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Admin Two',
            'email' => 'admin@two.test',
            'password' => Hash::make('CorrectPassword123!'),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customService = new DatabaseInstallerService(
            $this->tempEnvPath,
            $this->tempDir.DIRECTORY_SEPARATOR.'wrong.lock',
            $wrongPassPath
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('provided password does not match');

        $customService->executeCompleteInstallation(
            $dbConfig,
            $academyConfig,
            [
                'name' => 'Admin Two',
                'email' => 'admin@two.test',
                'password' => 'WrongPasswordAttempt123!',
            ]
        );
    }

    /**
     * Test 7: Retry does not overwrite an existing Academy's configuration.
     */
    public function test_retry_does_not_overwrite_existing_academy_configuration(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->tempSqlitePath,
        ]);
        DB::purge('sqlite');

        Artisan::call('migrate', ['--force' => true]);

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);
        $service->persistInstallationIdentity(new PDO('sqlite:'.$this->tempSqlitePath), 'sqlite', 'in_progress');

        // Pre-create the academy from an interrupted installation attempt
        $academyId = (string) Str::uuid();
        DB::table('academies')->insert([
            'id' => $academyId,
            'name' => 'Stable Academy',
            'slug' => 'stable-academy',
            'support_email' => 'support@stable.test',
            'timezone' => 'America/New_York',
            'locale' => 'en',
            'is_active' => 1,
            'settings' => json_encode(['theme' => 'dark']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);

        $dbConfig = ['driver' => 'sqlite'];
        $adminConfig = [
            'name' => 'Admin Name',
            'email' => 'admin@stable.test',
            'password' => 'StablePass123!@#',
        ];

        // Retry with same slug and name, but attempting to change timezone and support email
        $service->executeCompleteInstallation(
            $dbConfig,
            [
                'name' => 'Stable Academy',
                'slug' => 'stable-academy',
                'support_email' => 'different@stable.test',
                'timezone' => 'Asia/Tokyo',
                'locale' => 'ja',
            ],
            $adminConfig
        );

        $pdo = new PDO('sqlite:'.$this->tempSqlitePath);
        $postRetryAcademy = $pdo->query('SELECT * FROM academies LIMIT 1')->fetch(PDO::FETCH_ASSOC);

        // Assert academy configuration was strictly preserved and NOT overwritten
        $this->assertSame('support@stable.test', $postRetryAcademy['support_email']);
        $this->assertSame('America/New_York', $postRetryAcademy['timezone']);
        $this->assertSame('en', $postRetryAcademy['locale']);
    }

    /**
     * Test 8: The completion session cannot execute any installation action.
     */
    public function test_completion_session_cannot_execute_any_installation_action(): void
    {
        File::put($this->tempLockPath, json_encode([
            'installed_at' => now()->toIso8601String(),
            'driver' => 'sqlite',
        ]));

        // Completion page GET is allowed
        $this->withSession(['install_completed' => true])
            ->get('/install/complete')
            ->assertStatus(200);

        // Completion page POST is rejected
        $this->withSession(['install_completed' => true])
            ->post('/install/complete')
            ->assertStatus(405); // Method not allowed

        // POST /install/database is blocked
        $this->withSession(['install_completed' => true])
            ->post('/install/database', ['driver' => 'sqlite'])
            ->assertRedirect(route('login'));

        // POST /install/test-connection is blocked
        $this->withSession(['install_completed' => true])
            ->postJson('/install/test-connection', ['driver' => 'sqlite'])
            ->assertStatus(403);

        // GET /install is blocked
        $this->withSession(['install_completed' => true])
            ->get('/install')
            ->assertRedirect(route('login'));
    }

    /**
     * Test 9: Lock regeneration occurs only after positive installation verification.
     */
    public function test_lock_regeneration_occurs_only_after_positive_installation_verification(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->tempSqlitePath,
        ]);
        DB::purge('sqlite');

        Artisan::call('migrate', ['--force' => true]);

        // 1. Incomplete state: only academy exists (no admin, no marker)
        $academyId = (string) Str::uuid();
        DB::table('academies')->insert([
            'id' => $academyId,
            'name' => 'Positive Academy',
            'slug' => 'pos-academy',
            'support_email' => 'pos@academy.test',
            'timezone' => 'UTC',
            'locale' => 'en',
            'is_active' => 1,
            'settings' => json_encode(['theme' => 'light']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);

        $statusIncomplete = $service->getDatabaseInstallationStatus();
        $this->assertSame('ambiguous', $statusIncomplete['status']);
        $this->assertFileDoesNotExist($this->tempLockPath);

        // 2. Add administrator, but still missing marker
        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        $adminUser = User::create([
            'name' => 'Positive Admin',
            'email' => 'admin@pos.test',
            'password' => Hash::make('PosPass123!@#'),
            'is_active' => true,
        ]);
        $adminUser->assignRole('admin');

        $statusStillMissingMarker = $service->getDatabaseInstallationStatus();
        $this->assertSame('ambiguous', $statusStillMissingMarker['status']);
        $this->assertFileDoesNotExist($this->tempLockPath);

        // 3. Add installation marker: now positively verified!
        DB::table('settings')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $academyId,
            'group' => 'system',
            'key' => 'installed_at',
            'value' => now()->toIso8601String(),
            'type' => 'string',
            'is_public' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statusPositive = $service->getDatabaseInstallationStatus();
        $this->assertSame('installed', $statusPositive['status']);

        // Lock file MUST be regenerated now
        $this->assertFileExists($this->tempLockPath);
    }

    /**
     * Test 10: Environment configuration safely handles special characters.
     */
    public function test_environment_configuration_safely_handles_special_characters(): void
    {
        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);

        $complexPassword = 'My$uper#Secret"P@ss\\w=rd 99!';
        $service->persistEnv([
            'DB_PASSWORD' => $complexPassword,
            'CUSTOM_FLAG' => 'hello world',
        ]);

        $content = File::get($this->tempEnvPath);
        $this->assertStringContainsString('DB_PASSWORD=', $content);

        // Assert escaped characters
        $this->assertStringContainsString('\\"', $content);
        $this->assertStringContainsString('\\$', $content);
        $this->assertStringContainsString('\\\\', $content);

        // Verify formatEnvValue round-trip formatting
        $formatted = $service->formatEnvValue($complexPassword);
        $this->assertStringStartsWith('"', $formatted);
        $this->assertStringEndsWith('"', $formatted);
    }

    /**
     * Existing unrelated MySQL database is protected.
     */
    public function test_existing_unrelated_mysql_database_is_protected(): void
    {
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=seo_academy_test', 'root', '');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database unavailable: '.$e->getMessage());
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS wp_posts (id INT PRIMARY KEY, post_title VARCHAR(255)) ENGINE=InnoDB');
        $pdo->exec("INSERT INTO wp_posts (id, post_title) VALUES (1, 'Existing Blog Post') ON DUPLICATE KEY UPDATE post_title='Existing Blog Post'");

        try {
            /** @var DatabaseInstallerService $service */
            $service = $this->app->make(DatabaseInstallerService::class);
            $check = $service->testMySqlConnection([
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'seo_academy_test',
                'username' => 'root',
                'password' => '',
            ]);

            $this->assertFalse($check['ok']);
            $this->assertStringContainsString('unrelated existing tables', $check['message']);
            $this->assertStringContainsString('wp_posts', $check['message']);

            $response = $this->post('/install/database', [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'seo_academy_test',
                'username' => 'root',
                'password' => '',
                'academy_name' => 'Protected Test Academy',
                'support_email' => 'support@protect.test',
                'admin_name' => 'Admin User',
                'admin_email' => 'admin@protect.test',
                'admin_password' => 'SafePass123!@#',
                'admin_password_confirmation' => 'SafePass123!@#',
            ]);

            $response->assertSessionHasErrors('install');
            $this->assertFileDoesNotExist($this->tempLockPath);

            $postTitle = $pdo->query('SELECT post_title FROM wp_posts WHERE id = 1')->fetchColumn();
            $this->assertSame('Existing Blog Post', $postTitle);
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS wp_posts');
        }
    }

    /**
     * Existing populated SQLite database is protected.
     */
    public function test_existing_populated_sqlite_database_is_protected(): void
    {
        $pdo = new PDO('sqlite:'.$this->tempSqlitePath);
        $pdo->exec('CREATE TABLE external_customers (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO external_customers (name) VALUES ('Acme Corp')");

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);
        $check = $service->testSqliteConnection($this->tempSqlitePath);

        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('unrelated existing tables', $check['message']);
        $this->assertStringContainsString('external_customers', $check['message']);

        $response = $this->post('/install/database', [
            'driver' => 'sqlite',
            'academy_name' => 'Protected SQLite Academy',
            'support_email' => 'support@protectsqlite.test',
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@protectsqlite.test',
            'admin_password' => 'SafePass123!@#',
            'admin_password_confirmation' => 'SafePass123!@#',
        ]);

        $response->assertSessionHasErrors('install');
        $this->assertFileDoesNotExist($this->tempLockPath);

        $customerName = $pdo->query('SELECT name FROM external_customers LIMIT 1')->fetchColumn();
        $this->assertSame('Acme Corp', $customerName);
    }

    /**
     * Test 15 (Commercial 1.3 requirement):
     * A database containing all known migration names, expected table names, and a UUID users table,
     * but NO installation identity marker must be rejected as an ambiguous database rather than automatically resumed.
     */
    public function test_database_with_matching_schema_but_missing_identity_marker_is_rejected_as_ambiguous(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->tempSqlitePath,
        ]);
        DB::purge('sqlite');

        // Migrate all 70 official migrations directly without installer
        Artisan::call('migrate', ['--force' => true]);

        $pdo = new PDO('sqlite:'.$this->tempSqlitePath);

        // Verify migrations and tables exist
        $migrationCount = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
        $this->assertSame(count(DatabaseInstallerService::KNOWN_MIGRATIONS), $migrationCount);

        // Verify users.id is UUID
        $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
        $idCol = null;
        foreach ($cols as $col) {
            if ($col['name'] === 'id') {
                $idCol = $col;
                break;
            }
        }
        $this->assertNotNull($idCol);
        $this->assertSame('varchar', strtolower((string) $idCol['type']));

        // Verify installation_identity table does NOT exist
        $hasIdentityTable = (bool) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name = 'installation_identity'")->fetchColumn();
        $this->assertFalse($hasIdentityTable);

        /** @var DatabaseInstallerService $service */
        $service = $this->app->make(DatabaseInstallerService::class);

        // 1. Database inspection must reject as 'ambiguous' rather than resuming as 'partial_installation'
        $inspection = $service->inspectDatabase($pdo, 'sqlite');
        $this->assertSame('ambiguous', $inspection['status']);
        $this->assertStringContainsString('installation identity marker', $inspection['message']);

        // 2. Connection testing must fail and report ambiguous missing identity marker
        $check = $service->testSqliteConnection($this->tempSqlitePath);
        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('installation identity marker', $check['message']);

        // 3. Execution must abort with RuntimeException and refuse to resume this database
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('installation identity marker');

        $service->executeCompleteInstallation(
            dbConfig: ['driver' => 'sqlite'],
            academyConfig: [
                'name' => 'Rejected Academy',
                'slug' => 'rejected-academy',
                'support_email' => 'support@rejected.test',
            ],
            adminConfig: [
                'name' => 'Admin User',
                'email' => 'admin@rejected.test',
                'password' => 'SafePass123!@#',
            ]
        );
    }
}
