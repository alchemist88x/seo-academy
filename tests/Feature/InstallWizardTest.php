<?php

namespace Tests\Feature;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Services\DatabaseInstallerService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PDO;
use Tests\TestCase;

class InstallWizardTest extends TestCase
{
    private string $tempDir;

    private string $tempEnvPath;

    private string $tempLockPath;

    private string $tempSqlitePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('framework/testing/installer-'.uniqid('', true));
        File::ensureDirectoryExists($this->tempDir);

        $this->tempEnvPath = $this->tempDir.DIRECTORY_SEPARATOR.'.env';
        $this->tempLockPath = $this->tempDir.DIRECTORY_SEPARATOR.'installed.lock';
        $this->tempSqlitePath = $this->tempDir.DIRECTORY_SEPARATOR.'test-database.sqlite';

        File::put($this->tempEnvPath, "APP_NAME=\"SEO Academy\"\nDB_CONNECTION=sqlite\n");

        try {
            $mysqlPdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=seo_academy_test', 'root', '');
            $mysqlPdo->exec('SET FOREIGN_KEY_CHECKS=0;');
            $tables = $mysqlPdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $t) {
                $mysqlPdo->exec("DROP TABLE IF EXISTS `{$t}`");
            }
            $mysqlPdo->exec('SET FOREIGN_KEY_CHECKS=1;');
        } catch (\Throwable) {
            // MySQL test DB not available in this environment
        }

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

    public function test_install_page_returns_200_and_shows_all_wizard_steps(): void
    {
        $response = $this->get('/install');

        $response->assertStatus(200);
        $response->assertSee('Customer Installation Wizard');
        $response->assertSee('Step 1 — Welcome');
        $response->assertSee('Step 2 — Server Requirements');
        $response->assertSee('Step 3 — Database Engine');
        $response->assertSee('Step 4 — Academy Profile');
        $response->assertSee('Step 5 — Initial Administrator');
        $response->assertSee('Step 6 — System Defaults');
        $response->assertSee('SQLite 3');
        $response->assertSee('MySQL 8');
    }

    public function test_connection_endpoint_validates_sqlite_and_mysql_without_leaking_passwords(): void
    {
        $sqliteResponse = $this->postJson('/install/test-connection', [
            'driver' => 'sqlite',
        ]);

        $sqliteResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'driver' => 'sqlite',
            ]);

        $mysqlOkResponse = $this->postJson('/install/test-connection', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'seo_academy_test',
            'username' => 'root',
            'password' => '',
        ]);

        $mysqlOkResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'driver' => 'mysql',
            ]);

        $secretPassword = 'SuperSecretPassword!99';
        $mysqlFailResponse = $this->postJson('/install/test-connection', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'nonexistent_db_for_test',
            'username' => 'invalid_user_for_test',
            'password' => $secretPassword,
        ]);

        $mysqlFailResponse->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'driver' => 'mysql',
            ]);

        $this->assertStringNotContainsString($secretPassword, $mysqlFailResponse->getContent());
    }

    public function test_complete_fresh_sqlite_installation(): void
    {
        $response = $this->post('/install/database', [
            'driver' => 'sqlite',
            'academy_name' => 'Apex SEO Academy',
            'academy_slug' => 'apex-seo',
            'support_email' => 'support@apexseo.local',
            'timezone' => 'UTC',
            'locale' => 'en',
            'admin_name' => 'Primary Administrator',
            'admin_email' => 'admin@apexseo.local',
            'admin_password' => 'SecurePass123!@#',
            'admin_password_confirmation' => 'SecurePass123!@#',
        ]);

        $response->assertRedirect(route('install.complete'));

        // Assert database file and lock file created
        $this->assertFileExists($this->tempSqlitePath);
        $this->assertFileExists($this->tempLockPath);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', File::get($this->tempEnvPath));

        // Connect to newly created database and assert schema, academy, and admin user
        $pdo = new PDO('sqlite:'.$this->tempSqlitePath);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('users', $tables);
        $this->assertContains('academies', $tables);
        $this->assertContains('roles', $tables);

        // Assert single academy created
        $academies = $pdo->query('SELECT * FROM academies')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $academies);
        $this->assertSame('Apex SEO Academy', $academies[0]['name']);
        $this->assertSame('apex-seo', $academies[0]['slug']);
        $this->assertSame('support@apexseo.local', $academies[0]['support_email']);

        // Assert administrator created and password securely hashed
        $users = $pdo->query("SELECT * FROM users WHERE email = 'admin@apexseo.local'")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $users);
        $this->assertSame('Primary Administrator', $users[0]['name']);
        $this->assertTrue(Hash::check('SecurePass123!@#', $users[0]['password']));
        $this->assertNotEmpty($users[0]['email_verified_at']);

        // Assert admin role attached
        $userRoles = $pdo->query("SELECT r.name FROM roles r JOIN model_has_roles mhr ON r.id = mhr.role_id WHERE mhr.model_id = '{$users[0]['id']}'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('admin', $userRoles);
    }

    public function test_complete_fresh_mysql_installation(): void
    {
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=seo_academy_test', 'root', '');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database unavailable: '.$e->getMessage());
        }

        // Wipe tables in seo_academy_test
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');

        $response = $this->post('/install/database', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'seo_academy_test',
            'username' => 'root',
            'password' => '',
            'academy_name' => 'MySQL SEO Academy',
            'academy_slug' => 'mysql-seo',
            'support_email' => 'support@mysqlseo.local',
            'timezone' => 'UTC',
            'locale' => 'en',
            'admin_name' => 'MySQL Administrator',
            'admin_email' => 'admin@mysqlseo.local',
            'admin_password' => 'StrongAdminPass99!',
            'admin_password_confirmation' => 'StrongAdminPass99!',
        ]);

        $response->assertRedirect(route('install.complete'));

        // Assert env updated and lock created
        $this->assertFileExists($this->tempLockPath);
        $this->assertStringContainsString('DB_CONNECTION=mysql', File::get($this->tempEnvPath));

        $academyCount = (int) $pdo->query("SELECT COUNT(*) FROM academies WHERE slug = 'mysql-seo'")->fetchColumn();
        $this->assertSame(1, $academyCount);

        $adminUser = $pdo->query("SELECT * FROM users WHERE email = 'admin@mysqlseo.local'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($adminUser);
        $this->assertSame('MySQL Administrator', $adminUser['name']);
        $this->assertTrue(Hash::check('StrongAdminPass99!', $adminUser['password']));
    }

    public function test_install_requires_strong_passwords_and_valid_academy_fields(): void
    {
        $response = $this->post('/install/database', [
            'driver' => 'sqlite',
            'academy_name' => '', // Required
            'support_email' => 'invalid-email', // Invalid email
            'admin_name' => '', // Required
            'admin_email' => 'admin-email-invalid',
            'admin_password' => 'short', // Too short
            'admin_password_confirmation' => 'mismatch',
        ]);

        $response->assertSessionHasErrors([
            'academy_name',
            'support_email',
            'admin_name',
            'admin_email',
            'admin_password',
        ]);

        $this->assertFileDoesNotExist($this->tempLockPath);
    }

    public function test_install_routes_are_blocked_when_installed_lock_exists(): void
    {
        File::put($this->tempLockPath, json_encode([
            'installed_at' => now()->toIso8601String(),
            'driver' => 'sqlite',
        ]));

        $this->get('/install')->assertRedirect(route('login'));
        $this->post('/install/database', ['driver' => 'sqlite'])->assertRedirect(route('login'));

        $this->getJson('/install')->assertStatus(403);
        $this->postJson('/install/database', ['driver' => 'sqlite'])->assertStatus(403);
    }
}
