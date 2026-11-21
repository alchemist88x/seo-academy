<?php

declare(strict_types=1);

namespace App\Livewire\Installer;

use App\Domain\Platform\Services\DatabaseInstallerService;
use App\Domain\Platform\Services\InstallationStateService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts.guest')]
#[Title('Installation Wizard — SEO Academy')]
class InstallationWizard extends Component
{
    /**
     * Active wizard step (1 to 11).
     */
    #[Url(as: 'step')]
    public int $step = 1;

    // Database Selection & Configuration State
    public string $driver = 'sqlite';

    public string $sqlitePath = '';

    public string $mysqlHostingType = 'local'; // 'local', 'rds', 'remote', 'custom'

    public string $mysqlHost = '127.0.0.1';

    public string $mysqlPort = '3306';

    public string $mysqlDatabase = 'seo_academy';

    public string $mysqlUsername = 'seo_academy_user';

    public string $mysqlPassword = '';

    public bool $showMysqlPassword = false;

    // Connection Test State
    public bool $isTestingConnection = false;

    public ?array $testResult = null;

    public bool $connectionVerified = false;

    // Database Initialization & Migrations State
    public bool $isMigrating = false;

    public bool $migrationsCompleted = false;

    public int $migrationCount = 0;

    public ?string $migrationMessage = null;

    // Academy Configuration State
    public string $academyName = 'SEO Academy';

    public string $academySlug = 'seo-academy';

    public string $supportEmail = 'admin@example.com';

    public string $timezone = 'UTC';

    public string $locale = 'en';

    public string $currency = 'INR';

    public string $dateFormat = 'YYYY-MM-DD';

    // Administrator Account State
    public string $adminName = 'System Administrator';

    public string $adminEmail = 'admin@example.com';

    public string $adminPassword = '';

    public string $adminPasswordConfirmation = '';

    public bool $showAdminPassword = false;

    // Installation Progress & Audit State
    public array $completedSteps = [];

    public array $requirements = [];

    public array $dependencies = [];

    public array $verificationItems = [];

    public bool $isFinalizing = false;

    public ?string $generalError = null;

    public function mount(
        DatabaseInstallerService $installer,
        InstallationStateService $stateService
    ): void {
        // 1. If application is already locked and installed, redirect to login
        if ($installer->isInstalled()) {
            $this->redirectRoute('login', navigate: false);

            return;
        }

        // 2. Load pre-flight runtime and dependency data
        $this->requirements = $installer->checkSystemRequirements();
        $this->dependencies = $installer->checkComposerDependencies();

        // 3. Initialize default SQLite path
        $this->sqlitePath = $installer->sqliteDatabasePath();

        // 4. Load persisted installation state
        $state = $stateService->getState();
        $this->completedSteps = $state['completed_steps'] ?? [];
        $savedDriver = $state['driver'] ?? null;
        $this->driver = in_array($savedDriver, ['sqlite', 'mysql'], true) ? $savedDriver : 'sqlite';

        if (! empty($state['db_config'])) {
            $cfg = $state['db_config'];
            $this->mysqlHostingType = $cfg['hosting_type'] ?? $this->mysqlHostingType;
            $this->mysqlHost = $cfg['host'] ?? $this->mysqlHost;
            $this->mysqlPort = (string) ($cfg['port'] ?? $this->mysqlPort);
            $this->mysqlDatabase = $cfg['database'] ?? $this->mysqlDatabase;
            $this->mysqlUsername = $cfg['username'] ?? $this->mysqlUsername;
            if (! empty($cfg['path'])) {
                $this->sqlitePath = $cfg['path'];
            }
        }

        if (! empty($state['academy_config'])) {
            $acad = $state['academy_config'];
            $this->academyName = $acad['name'] ?? $this->academyName;
            $this->academySlug = $acad['slug'] ?? $this->academySlug;
            $this->supportEmail = $acad['support_email'] ?? $this->supportEmail;
            $this->timezone = $acad['timezone'] ?? $this->timezone;
            $this->locale = $acad['locale'] ?? $this->locale;
            $this->currency = $acad['currency'] ?? $this->currency;
            $this->dateFormat = $acad['date_format'] ?? $this->dateFormat;
        }

        // Check if migrations were already executed
        if (in_array(InstallationStateService::STATE_MIGRATION, $this->completedSteps, true)) {
            $this->migrationsCompleted = true;
            $this->migrationCount = count(DatabaseInstallerService::KNOWN_MIGRATIONS);
        }

        // 5. Enforce safe step bounds based on prerequisites
        $this->validateAndClampStep();
    }

    /**
     * Prevent customer from skipping uncompleted steps via URL query tampering.
     */
    private function validateAndClampStep(): void
    {
        $maxAllowed = 1;

        if (in_array(InstallationStateService::STATE_WELCOME, $this->completedSteps, true)) {
            $maxAllowed = 2;
        }
        if (in_array(InstallationStateService::STATE_REQUIREMENTS, $this->completedSteps, true) || in_array('requirements_check', $this->completedSteps, true)) {
            $maxAllowed = 3;
        }
        if (in_array(InstallationStateService::STATE_DEPENDENCIES, $this->completedSteps, true) || in_array('dependencies_check', $this->completedSteps, true)) {
            $maxAllowed = 4;
        }
        if (in_array(InstallationStateService::STATE_DATABASE_SELECTION, $this->completedSteps, true)) {
            $maxAllowed = 5;
        }
        if (in_array(InstallationStateService::STATE_DATABASE_CONFIG, $this->completedSteps, true) || in_array('database_configuration', $this->completedSteps, true)) {
            $maxAllowed = 6;
        }
        if (in_array(InstallationStateService::STATE_DATABASE_TEST, $this->completedSteps, true)) {
            $maxAllowed = 7;
        }
        if (in_array(InstallationStateService::STATE_MIGRATION, $this->completedSteps, true) || in_array('migration', $this->completedSteps, true)) {
            $maxAllowed = 8;
        }
        if (in_array(InstallationStateService::STATE_ACADEMY, $this->completedSteps, true) || in_array('academy_configuration', $this->completedSteps, true)) {
            $maxAllowed = 9;
        }
        if (in_array(InstallationStateService::STATE_ADMIN, $this->completedSteps, true) || in_array('administrator_creation', $this->completedSteps, true)) {
            $maxAllowed = 10;
        }
        if (in_array(InstallationStateService::STATE_VERIFICATION, $this->completedSteps, true) || in_array('finalization', $this->completedSteps, true)) {
            $maxAllowed = 11;
        }

        if ($this->step > $maxAllowed) {
            $this->step = $maxAllowed;
        } elseif ($this->step < 1) {
            $this->step = 1;
        }

        if ($this->step === 10) {
            $this->compileVerificationAudit();
        }
    }

    /**
     * Step 1: Start Installation.
     */
    public function startInstallation(InstallationStateService $stateService): void
    {
        $this->generalError = null;
        $stateService->markStepCompleted(InstallationStateService::STATE_WELCOME);
        $this->completedSteps[] = InstallationStateService::STATE_WELCOME;
        $this->step = 2;
    }

    /**
     * Step 2: Proceed from Requirements Check.
     */
    public function proceedFromRequirements(
        DatabaseInstallerService $installer,
        InstallationStateService $stateService
    ): void {
        $this->generalError = null;
        $this->requirements = $installer->checkSystemRequirements($this->driver);

        if (! ($this->requirements['compatible'] ?? false)) {
            $this->generalError = 'Mandatory server requirements are not met. Please resolve the marked issues before continuing.';

            return;
        }

        $stateService->markStepCompleted(InstallationStateService::STATE_REQUIREMENTS);
        $this->completedSteps[] = InstallationStateService::STATE_REQUIREMENTS;
        $this->step = 3;
    }

    /**
     * Step 3: Proceed from Dependencies Check.
     */
    public function proceedFromDependencies(
        DatabaseInstallerService $installer,
        InstallationStateService $stateService
    ): void {
        $this->generalError = null;
        $this->dependencies = $installer->checkComposerDependencies();

        if (! ($this->dependencies['compatible'] ?? false)) {
            $this->generalError = 'Core framework dependencies are missing or incompatible. Please upload a release archive with pre-installed dependencies.';

            return;
        }

        $stateService->markStepCompleted(InstallationStateService::STATE_DEPENDENCIES);
        $this->completedSteps[] = InstallationStateService::STATE_DEPENDENCIES;
        $this->step = 4;
    }

    /**
     * Livewire updated hook for driver property.
     */
    public function updatedDriver(mixed $value): void
    {
        $this->selectDriver(is_string($value) ? $value : '');
    }

    /**
     * Step 4: Select Database Engine (SQLite or MySQL).
     */
    public function selectDriver(string $newDriver): void
    {
        if (! in_array($newDriver, ['sqlite', 'mysql'], true)) {
            $this->addError('driver', 'The selected database driver is invalid. Please select SQLite or MySQL.');

            return;
        }

        $this->resetErrorBag('driver');
        $this->generalError = null;
        $this->driver = $newDriver;
        $this->testResult = null;
        $this->connectionVerified = false;

        // Immediately persist user selection into state
        try {
            $stateService = app(InstallationStateService::class);
            $stateService->saveState(array_merge($stateService->getState(), [
                'driver' => $this->driver,
            ]));
        } catch (Throwable) {
            // Non-blocking in volatile testing contexts
        }
    }

    /**
     * Step 4: Proceed from Database Selection.
     */
    public function proceedFromDatabaseSelection(InstallationStateService $stateService): void
    {
        $this->generalError = null;

        $this->validate([
            'driver' => ['required', 'string', 'in:sqlite,mysql'],
        ], [
            'driver.required' => 'The driver field is required.',
            'driver.in' => 'The selected database driver must be either SQLite or MySQL.',
        ]);

        $stateService->markStepCompleted(InstallationStateService::STATE_DATABASE_SELECTION, [
            'driver' => $this->driver,
        ]);
        $stateService->saveState(array_merge($stateService->getState(), [
            'driver' => $this->driver,
        ]));

        if (! in_array(InstallationStateService::STATE_DATABASE_SELECTION, $this->completedSteps, true)) {
            $this->completedSteps[] = InstallationStateService::STATE_DATABASE_SELECTION;
        }

        $this->step = 5;
    }

    /**
     * Step 5: Select MySQL Hosting Type.
     */
    public function selectHostingType(string $type): void
    {
        if (! in_array($type, ['local', 'rds', 'remote', 'custom'], true)) {
            return;
        }

        $this->mysqlHostingType = $type;
        $this->connectionVerified = false;
        $this->testResult = null;

        if ($type === 'local') {
            $this->mysqlHost = '127.0.0.1';
            $this->mysqlPort = '3306';
        } elseif ($type === 'rds') {
            if ($this->mysqlHost === '127.0.0.1' || empty($this->mysqlHost)) {
                $this->mysqlHost = '';
            }
            $this->mysqlPort = '3306';
        } elseif ($type === 'remote') {
            if ($this->mysqlHost === '127.0.0.1' || empty($this->mysqlHost)) {
                $this->mysqlHost = '';
            }
            $this->mysqlPort = '3306';
        }
    }

    public function updatedMysqlHost(): void
    {
        $this->connectionVerified = false;
        $this->testResult = null;
    }

    public function updatedMysqlPort(): void
    {
        $this->connectionVerified = false;
        $this->testResult = null;
    }

    public function updatedMysqlDatabase(): void
    {
        $this->connectionVerified = false;
        $this->testResult = null;
    }

    public function updatedMysqlUsername(): void
    {
        $this->connectionVerified = false;
        $this->testResult = null;
    }

    public function updatedMysqlPassword(): void
    {
        $this->connectionVerified = false;
        $this->testResult = null;
    }

    public function updatedSqlitePath(): void
    {
        $this->connectionVerified = false;
        $this->testResult = null;
    }

    /**
     * Step 5: Save Database Configuration.
     */
    public function saveDatabaseConfiguration(
        DatabaseInstallerService $installer,
        InstallationStateService $stateService
    ): void {
        $this->generalError = null;

        $this->validate([
            'driver' => ['required', 'string', 'in:sqlite,mysql'],
        ], [
            'driver.required' => 'The driver field is required.',
            'driver.in' => 'The selected database driver must be either SQLite or MySQL.',
        ]);

        if ($this->driver === 'sqlite') {
            $this->validate([
                'sqlitePath' => ['required', 'string', 'max:1024'],
            ]);

            $dbConfig = [
                'driver' => 'sqlite',
                'path' => $this->sqlitePath,
            ];
        } else {
            $this->validate([
                'mysqlHost' => ['required', 'string', 'max:255'],
                'mysqlPort' => ['required', 'numeric', 'min:1', 'max:65535'],
                'mysqlDatabase' => ['required', 'string', 'max:255'],
                'mysqlUsername' => ['required', 'string', 'max:255'],
                'mysqlPassword' => ['nullable', 'string', 'max:1024'],
            ]);

            $dbConfig = [
                'driver' => 'mysql',
                'hosting_type' => $this->mysqlHostingType,
                'host' => $this->mysqlHost,
                'port' => (int) $this->mysqlPort,
                'database' => $this->mysqlDatabase,
                'username' => $this->mysqlUsername,
                'password' => $this->mysqlPassword,
            ];
        }

        // Persist DB configuration to .env and runtime config
        $configResult = $installer->configureDatabase($dbConfig, true);
        if (! ($configResult['ok'] ?? false)) {
            $this->generalError = $configResult['message'] ?? 'Failed to apply database configuration.';

            return;
        }

        $stateService->markStepCompleted(InstallationStateService::STATE_DATABASE_CONFIG, [
            'driver' => $this->driver,
            'db_config' => $dbConfig,
        ]);
        $stateService->saveState(array_merge($stateService->getState(), [
            'driver' => $this->driver,
            'db_config' => $dbConfig,
        ]));

        if (! in_array(InstallationStateService::STATE_DATABASE_CONFIG, $this->completedSteps, true)) {
            $this->completedSteps[] = InstallationStateService::STATE_DATABASE_CONFIG;
        }

        $this->testResult = null;
        $this->connectionVerified = false;
        $this->step = 6;
    }

    /**
     * Step 6: Dedicated Database Connection Test.
     */
    public function testConnection(DatabaseInstallerService $installer): void
    {
        $this->isTestingConnection = true;
        $this->testResult = null;
        $this->generalError = null;

        try {
            if ($this->driver === 'sqlite') {
                $res = $installer->testSqliteConnection($this->sqlitePath);
            } else {
                $res = $installer->testMySqlConnection([
                    'host' => $this->mysqlHost,
                    'port' => (int) $this->mysqlPort,
                    'database' => $this->mysqlDatabase,
                    'username' => $this->mysqlUsername,
                    'password' => $this->mysqlPassword,
                ]);
            }

            $this->testResult = $res;
            $this->connectionVerified = (bool) ($res['ok'] ?? false);
        } catch (Throwable $e) {
            $this->testResult = [
                'ok' => false,
                'message' => 'Connection test encountered an error: '.$e->getMessage(),
            ];
            $this->connectionVerified = false;
        } finally {
            $this->isTestingConnection = false;
        }
    }

    /**
     * Step 6: Proceed from Connection Test.
     */
    public function proceedFromConnectionTest(
        DatabaseInstallerService $installer,
        InstallationStateService $stateService
    ): void {
        $this->generalError = null;

        if (! $this->connectionVerified) {
            // Re-attempt test automatically
            $this->testConnection($installer);

            if (! $this->connectionVerified) {
                $this->generalError = 'Please verify that your database connection test succeeds before proceeding to migrations.';

                return;
            }
        }

        $stateService->markStepCompleted(InstallationStateService::STATE_DATABASE_TEST);
        $this->completedSteps[] = InstallationStateService::STATE_DATABASE_TEST;
        $this->step = 7;
    }

    /**
     * Step 7: Run Migrations and Seed Roles.
     */
    public function runMigrations(
        DatabaseInstallerService $installer,
        InstallationStateService $stateService
    ): void {
        $this->isMigrating = true;
        $this->generalError = null;

        try {
            $res = $installer->runMigrationsAndSeedRoles();
            $this->migrationsCompleted = true;
            $this->migrationCount = $res['count'] ?? count(DatabaseInstallerService::KNOWN_MIGRATIONS);
            $this->migrationMessage = $res['message'] ?? 'All database migrations and role definitions executed successfully.';

            $stateService->markStepCompleted(InstallationStateService::STATE_MIGRATION);
            $this->completedSteps[] = InstallationStateService::STATE_MIGRATION;
        } catch (Throwable $e) {
            $this->generalError = 'Database migration failed: '.$e->getMessage();
            $this->migrationsCompleted = false;
        } finally {
            $this->isMigrating = false;
        }
    }

    /**
     * Step 7: Proceed to Academy Configuration.
     */
    public function proceedFromMigrations(): void
    {
        $this->generalError = null;

        if (! $this->migrationsCompleted) {
            $this->generalError = 'Please execute database migrations before proceeding.';

            return;
        }

        $this->step = 8;
    }

    /**
     * Step 8: Save Academy Configuration.
     */
    public function saveAcademy(
        DatabaseInstallerService $installer,
        InstallationStateService $stateService
    ): void {
        $this->generalError = null;

        $this->validate([
            'academyName' => ['required', 'string', 'min:3', 'max:100'],
            'supportEmail' => ['required', 'email', 'max:255'],
            'timezone' => ['required', 'string', 'max:100'],
            'locale' => ['required', 'string', 'in:en,es,fr,de'],
            'currency' => ['required', 'string', 'max:10'],
            'dateFormat' => ['required', 'string', 'max:20'],
        ]);

        try {
            $installer->configureAcademyProfile([
                'name' => $this->academyName,
                'slug' => $this->academySlug ?: $this->academyName,
                'support_email' => $this->supportEmail,
                'timezone' => $this->timezone,
                'locale' => $this->locale,
                'currency' => $this->currency,
                'date_format' => $this->dateFormat,
            ]);

            $stateService->markStepCompleted(InstallationStateService::STATE_ACADEMY, [
                'academy_config' => [
                    'name' => $this->academyName,
                    'slug' => $this->academySlug,
                    'support_email' => $this->supportEmail,
                    'timezone' => $this->timezone,
                    'locale' => $this->locale,
                    'currency' => $this->currency,
                    'date_format' => $this->dateFormat,
                ],
            ]);
            $this->completedSteps[] = InstallationStateService::STATE_ACADEMY;

            $this->step = 9;
        } catch (Throwable $e) {
            $this->generalError = 'Failed to save academy profile: '.$e->getMessage();
        }
    }

    /**
     * Step 9: Create Administrator Account.
     */
    public function createAdministrator(
        DatabaseInstallerService $installer,
        InstallationStateService $stateService
    ): void {
        $this->generalError = null;

        $this->validate([
            'adminName' => ['required', 'string', 'min:2', 'max:100'],
            'adminEmail' => ['required', 'email', 'max:255'],
            'adminPassword' => ['required', 'string', 'min:8', 'max:1024'],
            'adminPasswordConfirmation' => ['required', 'same:adminPassword'],
        ], [
            'adminPassword.min' => 'The administrator password must be at least 8 characters long.',
            'adminPasswordConfirmation.same' => 'The password confirmation does not match.',
        ]);

        try {
            $installer->createFirstAdministrator([
                'name' => $this->adminName,
                'email' => $this->adminEmail,
                'password' => $this->adminPassword,
            ]);

            $stateService->markStepCompleted(InstallationStateService::STATE_ADMIN, [
                'admin_email' => $this->adminEmail,
            ]);
            $this->completedSteps[] = InstallationStateService::STATE_ADMIN;

            $this->compileVerificationAudit();
            $this->step = 10;
        } catch (Throwable $e) {
            $this->generalError = 'Failed to create administrator account: '.$e->getMessage();
        }
    }

    /**
     * Step 10: Compile Final Verification Audit.
     */
    public function compileVerificationAudit(): void
    {
        $hasKey = (bool) config('app.key');
        $this->verificationItems = [
            [
                'title' => 'Environment & Cryptographic Key',
                'description' => 'AES-256-CBC active encryption key protecting session tokens and passwords.',
                'status' => $hasKey ? 'Verified' : 'Pending',
                'ok' => $hasKey,
            ],
            [
                'title' => 'Production Dependencies',
                'description' => 'Laravel 13, Livewire 4, Dompdf, and Spatie Permission active.',
                'status' => 'Verified',
                'ok' => true,
            ],
            [
                'title' => 'Database Schema & Security Roles',
                'description' => strtoupper($this->driver).' schema migrated with 4 standard security roles.',
                'status' => $this->migrationsCompleted ? 'Active' : 'Pending',
                'ok' => $this->migrationsCompleted,
            ],
            [
                'title' => 'Private Storage & Framework Cache',
                'description' => 'Private media, backups, and framework session paths verified writable.',
                'status' => 'Ready',
                'ok' => true,
            ],
            [
                'title' => 'Academy Organization Profile',
                'description' => $this->academyName.' ('.$this->supportEmail.', Timezone: '.$this->timezone.').',
                'status' => 'Configured',
                'ok' => true,
            ],
            [
                'title' => 'Super Administrator Account',
                'description' => $this->adminName.' <'.$this->adminEmail.'> with admin role assigned.',
                'status' => 'Provisioned',
                'ok' => true,
            ],
        ];
    }

    /**
     * Step 10: Finalize Installation and Lock System.
     */
    public function finalizeInstallation(
        DatabaseInstallerService $installer,
        InstallationStateService $stateService
    ): void {
        $this->isFinalizing = true;
        $this->generalError = null;

        try {
            $installer->finalizeInstallation();
            $stateService->finalize();

            $this->completedSteps[] = InstallationStateService::STATE_VERIFICATION;
            $this->step = 11;
        } catch (Throwable $e) {
            $this->generalError = 'Finalization error: '.$e->getMessage();
        } finally {
            $this->isFinalizing = false;
        }
    }

    /**
     * Navigate backward to preceding step.
     */
    public function previousStep(): void
    {
        $this->generalError = null;

        if ($this->step > 1) {
            $this->step--;
        }
    }

    /**
     * Jump directly to a previously completed step.
     */
    public function goToStep(int $targetStep): void
    {
        if ($targetStep < 1 || $targetStep > 11) {
            return;
        }

        // Only allow visiting step 1 or any completed step + 1
        $this->step = $targetStep;
        $this->validateAndClampStep();
    }

    /**
     * Toggle password visibility for MySQL password or Admin password.
     */
    public function togglePasswordVisibility(string $field): void
    {
        if ($field === 'mysql') {
            $this->showMysqlPassword = ! $this->showMysqlPassword;
        } elseif ($field === 'admin') {
            $this->showAdminPassword = ! $this->showAdminPassword;
        }
    }

    public function render(): View
    {
        return view('livewire.installer.wizard');
    }
}
