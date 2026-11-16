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
            ->assertSee('SQLite 3')
            ->assertSee('MySQL 8.4+ InnoDB');

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
}
