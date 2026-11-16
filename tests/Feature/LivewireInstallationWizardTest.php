<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Services\DatabaseInstallerService;
use App\Domain\Platform\Services\InstallationStateService;
use App\Livewire\Installer\InstallationWizard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireInstallationWizardTest extends TestCase
{
    private string $tempDir;

    private string $tempEnvPath;

    private string $tempLockPath;

    private string $tempSqlitePath;

    private string $tempStatePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('framework/testing/livewire-wizard-'.uniqid('', true));
        File::ensureDirectoryExists($this->tempDir);

        $this->tempEnvPath = $this->tempDir.DIRECTORY_SEPARATOR.'.env';
        $this->tempLockPath = $this->tempDir.DIRECTORY_SEPARATOR.'installed.lock';
        $this->tempSqlitePath = $this->tempDir.DIRECTORY_SEPARATOR.'test-wizard.sqlite';
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

    public function test_wizard_mounts_on_step_1_with_welcome_screen(): void
    {
        Livewire::test(InstallationWizard::class)
            ->assertSet('step', 1)
            ->assertSee('Step 1 — Welcome & Architecture')
            ->assertSee('Start Installation')
            ->assertDontSee('Official Support Email');
    }

    public function test_wizard_step_by_step_advancement(): void
    {
        $component = Livewire::test(InstallationWizard::class);

        // Step 1 -> Step 2
        $component->call('startInstallation')
            ->assertSet('step', 2)
            ->assertSee('Step 2 — Server & PHP Requirements');

        // Step 2 -> Step 3
        $component->call('proceedFromRequirements')
            ->assertSet('step', 3)
            ->assertSee('Step 3 — Application & Composer Dependencies');

        // Step 3 -> Step 4
        $component->call('proceedFromDependencies')
            ->assertSet('step', 4)
            ->assertSee('Step 4 — Database Selection')
            ->assertSee('SQLite')
            ->assertSee('MySQL / MariaDB');

        // Step 4: Switch driver and proceed
        $component->call('selectDriver', 'sqlite')
            ->assertSet('driver', 'sqlite')
            ->call('proceedFromDatabaseSelection')
            ->assertSet('step', 5)
            ->assertSee('Step 5 — SQLite Database Configuration');

        // Step 5: Configure and proceed
        $component->set('sqlitePath', $this->tempSqlitePath)
            ->call('saveDatabaseConfiguration')
            ->assertSet('step', 6)
            ->assertSee('Step 6 — Database Connection Verification');

        // Step 6: Test connection
        $component->call('testConnection')
            ->assertSet('connectionVerified', true)
            ->assertSee('Connection Verified')
            ->call('proceedFromConnectionTest')
            ->assertSet('step', 7)
            ->assertSee('Step 7 — Database Initialization & Schema Migrations');

        // Step 7: Run migrations
        $component->call('runMigrations')
            ->assertSet('migrationsCompleted', true)
            ->assertSee('All application database migrations and role definitions executed successfully.')
            ->call('proceedFromMigrations')
            ->assertSet('step', 8)
            ->assertSee('Step 8 — Academy Organization Profile');

        // Step 8: Configure Academy
        $component->set('academyName', 'Apex Livewire Academy')
            ->set('supportEmail', 'admin@apexacademy.local')
            ->set('timezone', 'UTC')
            ->set('locale', 'en')
            ->set('currency', 'USD')
            ->set('dateFormat', 'YYYY-MM-DD')
            ->call('saveAcademy')
            ->assertSet('step', 9)
            ->assertSee('Step 9 — Initial Administrator Account');

        // Step 9: Create Administrator
        $component->set('adminName', 'Apex SuperAdmin')
            ->set('adminEmail', 'superadmin@apexacademy.local')
            ->set('adminPassword', 'StrongPass123!@#')
            ->set('adminPasswordConfirmation', 'StrongPass123!@#')
            ->call('createAdministrator')
            ->assertSet('step', 10)
            ->assertSee('Step 10 — Final System Verification');

        // Step 10: Finalize and Lock
        $component->call('finalizeInstallation')
            ->assertSet('step', 11)
            ->assertSee('Installation Successful!')
            ->assertSee('Apex Livewire Academy')
            ->assertSee('superadmin@apexacademy.local');

        // Verify lockfile was created
        $this->assertFileExists($this->tempLockPath);
    }

    public function test_wizard_prevents_jumping_ahead_via_url(): void
    {
        // Attempt to mount on step 9 directly without prerequisites
        Livewire::withQueryParams(['step' => 9])
            ->test(InstallationWizard::class)
            ->assertSet('step', 1);
    }

    public function test_wizard_previous_step_navigation(): void
    {
        $component = Livewire::test(InstallationWizard::class)
            ->call('startInstallation')
            ->assertSet('step', 2);

        $component->call('previousStep')
            ->assertSet('step', 1)
            ->assertSee('Step 1 — Welcome & Architecture');
    }

    public function test_mysql_validation_on_step_5(): void
    {
        $component = Livewire::test(InstallationWizard::class)
            ->call('startInstallation')
            ->call('proceedFromRequirements')
            ->call('proceedFromDependencies')
            ->call('selectDriver', 'mysql')
            ->call('proceedFromDatabaseSelection')
            ->assertSet('step', 5);

        // Missing required fields
        $component->set('mysqlHost', '')
            ->set('mysqlDatabase', '')
            ->set('mysqlUsername', '')
            ->call('saveDatabaseConfiguration')
            ->assertHasErrors(['mysqlHost', 'mysqlDatabase', 'mysqlUsername'])
            ->assertSet('step', 5);
    }

    public function test_database_engine_selection_and_persistence(): void
    {
        $component = Livewire::test(InstallationWizard::class)
            ->call('startInstallation')
            ->call('proceedFromRequirements')
            ->call('proceedFromDependencies')
            ->assertSet('step', 4)
            ->assertSet('driver', 'sqlite')
            ->assertSee('SQLite')
            ->assertSee('MySQL / MariaDB');

        // Select MySQL
        $component->call('selectDriver', 'mysql')
            ->assertSet('driver', 'mysql')
            ->assertHasNoErrors('driver');

        // Verify state persistence
        $stateService = app(InstallationStateService::class);
        $this->assertSame('mysql', $stateService->getState()['driver']);

        // Switch back to SQLite
        $component->call('selectDriver', 'sqlite')
            ->assertSet('driver', 'sqlite')
            ->assertHasNoErrors('driver');
        $this->assertSame('sqlite', $stateService->getState()['driver']);
    }

    public function test_invalid_driver_selection_rejected_with_validation_error(): void
    {
        Livewire::test(InstallationWizard::class)
            ->call('startInstallation')
            ->call('proceedFromRequirements')
            ->call('proceedFromDependencies')
            ->assertSet('step', 4)
            ->call('selectDriver', 'oracle')
            ->assertHasErrors(['driver'])
            ->assertSet('driver', 'sqlite'); // remains unchanged
    }

    public function test_step_5_immediate_engine_toggle_without_page_reload(): void
    {
        $component = Livewire::test(InstallationWizard::class)
            ->call('startInstallation')
            ->call('proceedFromRequirements')
            ->call('proceedFromDependencies')
            ->call('selectDriver', 'sqlite')
            ->call('proceedFromDatabaseSelection')
            ->assertSet('step', 5)
            ->assertSee('SQLite Database Configuration')
            ->assertDontSee('Database Server Host');

        // Toggle to MySQL on Step 5
        $component->call('selectDriver', 'mysql')
            ->assertSet('step', 5)
            ->assertSee('Database Hosting & Connection Credentials')
            ->assertSee('Database Server Host');

        // Toggle back to SQLite on Step 5
        $component->call('selectDriver', 'sqlite')
            ->assertSet('step', 5)
            ->assertSee('SQLite Database Configuration')
            ->assertDontSee('Database Server Host');
    }

    public function test_mysql_hosting_types_and_cloudpanel_guide(): void
    {
        $component = Livewire::test(InstallationWizard::class)
            ->call('startInstallation')
            ->call('proceedFromRequirements')
            ->call('proceedFromDependencies')
            ->call('selectDriver', 'mysql')
            ->call('proceedFromDatabaseSelection')
            ->assertSet('step', 5)
            ->assertSet('mysqlHostingType', 'local')
            ->assertSee('CloudPanel & Local VPS Setup Guide')
            ->assertSet('mysqlHost', '127.0.0.1')
            ->assertSet('mysqlPort', '3306');

        // Select Amazon RDS
        $component->call('selectHostingType', 'rds')
            ->assertSet('mysqlHostingType', 'rds')
            ->assertDontSee('CloudPanel & Local VPS Setup Guide')
            ->assertSet('mysqlPort', '3306');

        // Select Remote MySQL
        $component->call('selectHostingType', 'remote')
            ->assertSet('mysqlHostingType', 'remote')
            ->assertSet('mysqlPort', '3306');

        // Select Custom
        $component->call('selectHostingType', 'custom')
            ->assertSet('mysqlHostingType', 'custom');

        // Switch back to Local and verify CloudPanel guide returns
        $component->call('selectHostingType', 'local')
            ->assertSet('mysqlHostingType', 'local')
            ->assertSee('CloudPanel & Local VPS Setup Guide')
            ->assertSet('mysqlHost', '127.0.0.1');
    }

    public function test_changing_credentials_invalidates_connection_verification(): void
    {
        $component = Livewire::test(InstallationWizard::class)
            ->call('startInstallation')
            ->call('proceedFromRequirements')
            ->call('proceedFromDependencies')
            ->call('selectDriver', 'sqlite')
            ->call('proceedFromDatabaseSelection')
            ->set('sqlitePath', $this->tempSqlitePath)
            ->call('saveDatabaseConfiguration')
            ->assertSet('step', 6)
            ->call('testConnection')
            ->assertSet('connectionVerified', true);

        // Go back to Step 5 and modify sqlite path
        $component->call('previousStep')
            ->assertSet('step', 5)
            ->set('sqlitePath', $this->tempSqlitePath.'.modified');

        // Verify connection status is invalidated
        $this->assertFalse($component->get('connectionVerified'));
        $this->assertNull($component->get('testResult'));
    }

    public function test_mysql_live_connection_and_compatibility_detection(): void
    {
        $component = Livewire::test(InstallationWizard::class)
            ->call('startInstallation')
            ->call('proceedFromRequirements')
            ->call('proceedFromDependencies')
            ->call('selectDriver', 'mysql')
            ->call('proceedFromDatabaseSelection')
            ->set('mysqlHost', '127.0.0.1')
            ->set('mysqlPort', '3306')
            ->set('mysqlDatabase', 'seo_academy_wizard_test')
            ->set('mysqlUsername', 'root')
            ->set('mysqlPassword', '')
            ->call('saveDatabaseConfiguration')
            ->assertSet('step', 6)
            ->call('testConnection')
            ->assertSet('connectionVerified', true);

        $result = $component->get('testResult');
        $this->assertTrue($result['ok']);
        $this->assertSame('mysql', $result['driver']);
        $this->assertContains($result['engine'], ['MySQL', 'MariaDB']);
        $this->assertTrue($result['innodb']);
        $this->assertNotEmpty($result['version']);
    }

    public function test_mysql_incorrect_credentials_and_nonexistent_database(): void
    {
        $component = Livewire::test(InstallationWizard::class)
            ->call('startInstallation')
            ->call('proceedFromRequirements')
            ->call('proceedFromDependencies')
            ->call('selectDriver', 'mysql')
            ->call('proceedFromDatabaseSelection')
            ->set('mysqlHost', '127.0.0.1')
            ->set('mysqlPort', '3306')
            ->set('mysqlDatabase', 'nonexistent_database_'.uniqid())
            ->set('mysqlUsername', 'invalid_user_'.uniqid())
            ->set('mysqlPassword', 'wrong_pass')
            ->call('saveDatabaseConfiguration')
            ->assertSet('step', 5) // Stays on step 5 because connection test fails
            ->assertSet('connectionVerified', false);

        $error = $component->get('generalError');
        $this->assertNotEmpty($error);
        $this->assertStringNotContainsString('wrong_pass', $error);
    }

    public function test_mysql_unreachable_host_returns_clean_error(): void
    {
        $component = Livewire::test(InstallationWizard::class)
            ->call('startInstallation')
            ->call('proceedFromRequirements')
            ->call('proceedFromDependencies')
            ->call('selectDriver', 'mysql')
            ->call('proceedFromDatabaseSelection')
            ->set('mysqlHost', '192.0.2.1') // reserved TEST-NET unreachable IP
            ->set('mysqlPort', '3306')
            ->set('mysqlDatabase', 'seo_academy')
            ->set('mysqlUsername', 'dbuser')
            ->set('mysqlPassword', 'secret')
            ->call('saveDatabaseConfiguration')
            ->assertSet('step', 5) // Stays on step 5 because connection fails
            ->assertSet('connectionVerified', false);

        $error = $component->get('generalError');
        $this->assertNotEmpty($error);
        $this->assertStringNotContainsString('secret', $error);
    }
}
