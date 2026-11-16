<?php

namespace Tests\Feature;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Services\DatabaseInstallerService;
use App\Domain\Platform\Services\InstallationStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class StagedInstallationTest extends TestCase
{
    private string $tempDir;

    private string $tempEnvPath;

    private string $tempLockPath;

    private string $tempSqlitePath;

    private string $tempStatePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('framework/testing/staged-'.uniqid('', true));
        File::ensureDirectoryExists($this->tempDir);

        $this->tempEnvPath = $this->tempDir.DIRECTORY_SEPARATOR.'.env';
        $this->tempLockPath = $this->tempDir.DIRECTORY_SEPARATOR.'installed.lock';
        $this->tempSqlitePath = $this->tempDir.DIRECTORY_SEPARATOR.'staged-test.sqlite';
        $this->tempStatePath = $this->tempDir.DIRECTORY_SEPARATOR.'installer_state.json';

        File::put($this->tempEnvPath, "APP_NAME=\"SEO Academy\"\nDB_CONNECTION=sqlite\nAPP_KEY=base64:".base64_encode(random_bytes(32))."\n");

        $lockPath = $this->tempLockPath;
        $sqlitePath = $this->tempSqlitePath;
        $statePath = $this->tempStatePath;

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

        $this->app->instance(
            InstallationStateService::class,
            new InstallationStateService($statePath)
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

    public function test_uninstalled_application_redirects_normal_routes_to_installer(): void
    {
        $response = $this->get('/login');
        $response->assertRedirect(route('install.index'));
    }

    public function test_staged_wizard_step_by_step_complete_flow(): void
    {
        // 1. Step 1: Requirements Check
        $reqGet = $this->get(route('install.step.requirements'));
        $reqGet->assertStatus(200);
        $reqGet->assertSee('System Environment Verification');
        $reqGet->assertSee('PHP');

        $reqPost = $this->post(route('install.step.requirements.store'), ['driver' => 'sqlite']);
        $reqPost->assertRedirect(route('install.step.dependencies'));

        // 2. Step 2: Dependencies Check
        $depGet = $this->get(route('install.step.dependencies'));
        $depGet->assertStatus(200);
        $depGet->assertSee('Production Packages & Autoloader');
        $depGet->assertSee('laravel/framework');

        $depPost = $this->post(route('install.step.dependencies.store'));
        $depPost->assertRedirect(route('install.step.environment'));

        // 3. Step 3: Environment Setup
        $envGet = $this->get(route('install.step.environment'));
        $envGet->assertStatus(200);
        $envGet->assertSee('Environment File & Encryption Key');

        $envPost = $this->post(route('install.step.environment.store'));
        $envPost->assertRedirect(route('install.step.database'));

        // 4. Step 4: Database Selection (SQLite)
        $dbGet = $this->get(route('install.step.database'));
        $dbGet->assertStatus(200);
        $dbGet->assertSee('Choose Your Database Engine');

        $dbPost = $this->post(route('install.step.database.store'), [
            'driver' => 'sqlite',
        ]);
        $dbPost->assertRedirect(route('install.step.migrations'));

        // 5. Step 5: Migrations
        $migGet = $this->get(route('install.step.migrations'));
        $migGet->assertStatus(200);
        $migGet->assertSee('Schema Initialization & Security Roles');

        $migPost = $this->post(route('install.step.migrations.store'));
        $migPost->assertRedirect(route('install.step.storage'));

        // Assert tables created in test sqlite DB
        $pdo = new PDO('sqlite:'.$this->tempSqlitePath);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('users', $tables);
        $this->assertContains('academies', $tables);
        $this->assertContains('roles', $tables);

        // 6. Step 6: Storage
        $storeGet = $this->get(route('install.step.storage'));
        $storeGet->assertStatus(200);
        $storeGet->assertSee('Private Filesystem & Cache Storage');

        $storePost = $this->post(route('install.step.storage.store'));
        $storePost->assertRedirect(route('install.step.academy'));

        // 7. Step 7: Academy Configuration
        $acadGet = $this->get(route('install.step.academy'));
        $acadGet->assertStatus(200);
        $acadGet->assertSee('Academy Identity & Regional Settings');

        $acadPost = $this->post(route('install.step.academy.store'), [
            'academy_name' => 'Staged SEO Academy',
            'academy_slug' => 'staged-seo',
            'support_email' => 'support@staged.test',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
        ]);
        $acadPost->assertRedirect(route('install.step.admin'));

        // 8. Step 8: Administrator Creation
        $adminGet = $this->get(route('install.step.admin'));
        $adminGet->assertStatus(200);
        $adminGet->assertSee('Establish Primary Administrator Credentials');

        $adminPost = $this->post(route('install.step.admin.store'), [
            'admin_name' => 'Staged Admin',
            'admin_email' => 'admin@staged.test',
            'admin_password' => 'StagedPassword123!',
            'admin_password_confirmation' => 'StagedPassword123!',
        ]);
        $adminPost->assertRedirect(route('install.step.finalization'));

        // 9. Step 9: Finalization
        $finalGet = $this->get(route('install.step.finalization'));
        $finalGet->assertStatus(200);
        $finalGet->assertSee('System Verification & Security Lock');

        $finalPost = $this->post(route('install.step.finalization.store'));
        $finalPost->assertRedirect(route('install.complete'));

        // Verify lock file is created
        $this->assertFileExists($this->tempLockPath);

        // Verify completion page loads
        $completeGet = $this->withSession([
            'install_completed' => true,
            'academy_name' => 'Staged SEO Academy',
            'admin_email' => 'admin@staged.test',
        ])->get(route('install.complete'));
        $completeGet->assertStatus(200);
        $completeGet->assertSee('Installation Successful!');
        $completeGet->assertSee('Staged SEO Academy');
        $completeGet->assertSee('admin@staged.test');
    }

    public function test_resumability_and_state_persistence_after_interruption(): void
    {
        /** @var InstallationStateService $stateService */
        $stateService = $this->app->make(InstallationStateService::class);

        // Complete steps 1 and 2
        $stateService->markStepCompleted(InstallationStateService::STATE_REQUIREMENTS);
        $stateService->markStepCompleted(InstallationStateService::STATE_DEPENDENCIES);

        // Check persisted state
        $state = $stateService->getState();
        $this->assertTrue(in_array($state['current_step'], [InstallationStateService::STATE_ENVIRONMENT, InstallationStateService::STATE_DATABASE_SELECTION], true));
        $this->assertTrue($stateService->isStepCompleted(InstallationStateService::STATE_REQUIREMENTS));
        $this->assertTrue($stateService->isStepCompleted(InstallationStateService::STATE_DEPENDENCIES));
        $this->assertFalse($stateService->isStepCompleted(InstallationStateService::STATE_DATABASE_CONFIG));

        // When customer accesses index with ?staged=1, it resumes at current step
        $response = $this->get('/install?staged=1');
        $this->assertTrue($response->isRedirect());
    }

    public function test_system_diagnostics_sanitization(): void
    {
        $response = $this->get(route('install.diagnostics'));
        $response->assertStatus(200);
        $response->assertSee('System Diagnostics');
        $response->assertSee('PHP');
        $response->assertSee('Download Sanitized Report');

        // Test JSON download
        $dlResponse = $this->get(route('install.diagnostics.download'));
        $dlResponse->assertStatus(200);
        $dlResponse->assertHeader('Content-Type', 'application/json');

        $content = $dlResponse->getContent();
        $this->assertJson($content);
        $this->assertStringNotContainsString('password', strtolower($content));
        $this->assertStringNotContainsString(base_path(), $content);
    }

    public function test_locked_installer_blocks_access_permanently(): void
    {
        File::put($this->tempLockPath, json_encode([
            'installed_at' => now()->toIso8601String(),
            'driver' => 'sqlite',
        ]));

        $this->get(route('install.index'))->assertRedirect(route('login'));
        $this->get(route('install.step.requirements'))->assertRedirect(route('login'));
        $this->get(route('install.step.database'))->assertRedirect(route('login'));
        $this->get(route('install.step.migrations'))->assertRedirect(route('login'));
        $this->get(route('install.step.finalization'))->assertRedirect(route('login'));
    }

    public function test_database_store_validates_driver_and_mysql_fields(): void
    {
        // 1. Missing driver produces validation error
        $missingDriver = $this->post(route('install.step.database.store'), []);
        $missingDriver->assertSessionHasErrors(['driver']);

        // 2. Invalid driver produces validation error
        $invalidDriver = $this->post(route('install.step.database.store'), [
            'driver' => 'postgres',
        ]);
        $invalidDriver->assertSessionHasErrors(['driver']);

        // 3. MySQL driver without host/database/username fails validation
        $incompleteMysql = $this->post(route('install.step.database.store'), [
            'driver' => 'mysql',
        ]);
        $incompleteMysql->assertSessionHasErrors(['host', 'database', 'username']);
    }
}
