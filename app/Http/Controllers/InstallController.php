<?php

namespace App\Http\Controllers;

use App\Domain\Platform\Services\DatabaseInstallerService;
use App\Domain\Platform\Services\InstallationStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class InstallController extends Controller
{
    public function __construct(
        private readonly DatabaseInstallerService $installer,
        private readonly InstallationStateService $stateService,
    ) {}

    /**
     * Entry router: renders the professional multi-step installation wizard.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        if ($request->query('staged') === '1') {
            $sessionId = $request->session()->getId();
            $state = $this->stateService->initializeSession($sessionId);
            $currentStep = $state['current_step'] ?? InstallationStateService::STATE_REQUIREMENTS;

            return redirect()->route($this->stateService->getRouteForStep($currentStep));
        }

        return view('install.index');
    }

    /**
     * Complete single-POST installation endpoint (compatible with automated setups & direct submission).
     */
    public function store(Request $request): RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $driver = $request->input('driver', 'sqlite');
        $driver = in_array($driver, ['sqlite', 'mysql'], true) ? $driver : 'sqlite';

        // 1. Driver-specific pre-flight requirement check
        $requirements = $this->installer->checkSystemRequirements($driver);
        if (! $requirements['compatible']) {
            return back()->withInput($request->except(['password', 'admin_password', 'admin_password_confirmation']))
                ->withErrors(['install' => "Server requirements are not met for the selected driver ({$driver}). Please verify mandatory PHP extensions and directory permissions."]);
        }

        // 2. Comprehensive validation of all wizard steps
        $validated = $request->validate([
            // Database
            'driver' => ['required', 'string', 'in:sqlite,mysql'],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1024'],

            // Academy Profile
            'academy_name' => ['required', 'string', 'min:2', 'max:255'],
            'academy_slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/i'],
            'support_email' => ['required', 'email', 'max:255'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'locale' => ['nullable', 'string', 'in:en,es,fr,de'],

            // Initial Administrator
            'admin_name' => ['required', 'string', 'min:2', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        try {
            $result = $this->installer->executeCompleteInstallation(
                dbConfig: [
                    'driver' => $validated['driver'],
                    'host' => $validated['host'] ?? null,
                    'port' => $validated['port'] ?? null,
                    'database' => $validated['database'] ?? null,
                    'username' => $validated['username'] ?? null,
                    'password' => $validated['password'] ?? null,
                ],
                academyConfig: [
                    'name' => $validated['academy_name'],
                    'slug' => $validated['academy_slug'] ?? $validated['academy_name'],
                    'support_email' => $validated['support_email'],
                    'timezone' => $validated['timezone'] ?? 'UTC',
                    'locale' => $validated['locale'] ?? 'en',
                ],
                adminConfig: [
                    'name' => $validated['admin_name'],
                    'email' => $validated['admin_email'],
                    'password' => $validated['admin_password'],
                ]
            );

            $this->stateService->finalize();

            return redirect()
                ->route('install.complete')
                ->with('install_completed', true)
                ->with('academy_name', $result['academy']->name)
                ->with('admin_email', $result['admin']->email);
        } catch (Throwable $e) {
            $sanitized = $this->installer->sanitizeErrorMessage(
                $e->getMessage(),
                $validated['password'] ?? null,
                $validated['username'] ?? null,
            );

            return back()
                ->withInput($request->except(['password', 'admin_password', 'admin_password_confirmation']))
                ->withErrors(['install' => $sanitized]);
        }
    }

    /**
     * Step 1: Server Requirements Check.
     */
    public function showRequirements(Request $request): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $driver = $request->query('driver', 'sqlite');
        $requirements = $this->installer->checkSystemRequirements($driver);
        $state = $this->stateService->getState();

        return view('install.steps.requirements', [
            'requirements' => $requirements,
            'driver' => $driver,
            'state' => $state,
            'stepNumber' => 1,
            'totalSteps' => 9,
        ]);
    }

    public function storeRequirements(Request $request): RedirectResponse
    {
        $driver = $request->input('driver', 'sqlite');
        $driver = in_array($driver, ['sqlite', 'mysql'], true) ? $driver : 'sqlite';

        $requirements = $this->installer->checkSystemRequirements($driver);
        if (! $requirements['compatible']) {
            return back()->withErrors([
                'requirements' => 'Server requirements are not satisfied. Please resolve mandatory PHP extensions or directory permissions before proceeding.',
            ]);
        }

        $this->stateService->markStepCompleted(InstallationStateService::STATE_REQUIREMENTS, [
            'driver' => $driver,
        ]);

        return redirect()->route('install.step.dependencies');
    }

    /**
     * Step 2: Composer and Application Dependency Check.
     */
    public function showDependencies(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $dependencies = $this->installer->checkComposerDependencies();
        $state = $this->stateService->getState();

        return view('install.steps.dependencies', [
            'dependencies' => $dependencies,
            'state' => $state,
            'stepNumber' => 2,
            'totalSteps' => 9,
        ]);
    }

    public function storeDependencies(): RedirectResponse
    {
        $dependencies = $this->installer->checkComposerDependencies();
        if (! $dependencies['compatible']) {
            return back()->withErrors([
                'dependencies' => 'Mandatory production Composer dependencies are missing. Please deploy the release package containing vendor/ or install dependencies before continuing.',
            ]);
        }

        $this->stateService->markStepCompleted(InstallationStateService::STATE_DEPENDENCIES);

        return redirect()->route('install.step.environment');
    }

    /**
     * Step 3: Automatic Environment Setup.
     */
    public function showEnvironment(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $envSetup = $this->installer->checkEnvironmentSetup();
        $state = $this->stateService->getState();

        return view('install.steps.environment', [
            'envSetup' => $envSetup,
            'state' => $state,
            'stepNumber' => 3,
            'totalSteps' => 9,
        ]);
    }

    public function storeEnvironment(): RedirectResponse
    {
        $envSetup = $this->installer->checkEnvironmentSetup();
        if (! $envSetup['compatible']) {
            return back()->withErrors([
                'environment' => 'Environment file or application key is missing or could not be generated.',
            ]);
        }

        $this->stateService->markStepCompleted(InstallationStateService::STATE_ENVIRONMENT);

        return redirect()->route('install.step.database');
    }

    /**
     * Step 4: Database Selection and Configuration.
     */
    public function showDatabase(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $state = $this->stateService->getState();
        $driver = $state['driver'] ?? config('database.default', 'sqlite');

        return view('install.steps.database', [
            'state' => $state,
            'currentDriver' => $driver,
            'stepNumber' => 4,
            'totalSteps' => 9,
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver' => ['required', 'string', 'in:sqlite,mysql'],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1024'],
            'confirm_switch' => ['nullable', 'boolean'],
        ], [
            'driver.required' => 'The driver field is required. Please select SQLite or MySQL.',
            'driver.in' => 'The selected database driver must be either SQLite or MySQL.',
            'host.required_if' => 'The database host is required when MySQL is selected.',
            'database.required_if' => 'The database name is required when MySQL is selected.',
            'username.required_if' => 'The database username is required when MySQL is selected.',
        ]);

        $confirmSwitch = (bool) ($validated['confirm_switch'] ?? false);

        $dbConfig = [
            'driver' => $validated['driver'],
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? null,
            'database' => $validated['database'] ?? null,
            'username' => $validated['username'] ?? null,
            'password' => $validated['password'] ?? null,
        ];

        $result = $this->installer->configureDatabase($dbConfig, $confirmSwitch);

        if (! $result['ok']) {
            if (! empty($result['requires_confirmation'])) {
                return back()
                    ->withInput($request->except(['password']))
                    ->with('warning_switch', $result['message']);
            }

            return back()
                ->withInput($request->except(['password']))
                ->withErrors(['database' => $result['message']]);
        }

        $this->stateService->markStepCompleted(InstallationStateService::STATE_DATABASE_CONFIG, [
            'driver' => $validated['driver'],
            'db_config' => array_merge($dbConfig, ['password' => '***']),
        ]);

        return redirect()->route('install.step.migrations');
    }

    /**
     * Ajax connection test endpoint.
     */
    public function testConnection(Request $request): JsonResponse
    {
        if ($this->installer->isInstalled()) {
            return response()->json([
                'ok' => false,
                'message' => 'Application is already installed.',
            ], 403);
        }

        $validated = $request->validate([
            'driver' => ['required', 'string', 'in:sqlite,mysql'],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1024'],
        ]);

        if ($validated['driver'] === 'sqlite') {
            $result = $this->installer->testSqliteConnection();
        } else {
            $result = $this->installer->testMySqlConnection([
                'host' => (string) ($validated['host'] ?? '127.0.0.1'),
                'port' => $validated['port'] ?? 3306,
                'database' => (string) ($validated['database'] ?? ''),
                'username' => (string) ($validated['username'] ?? ''),
                'password' => (string) ($validated['password'] ?? ''),
            ]);
        }

        return response()->json([
            'ok' => $result['ok'],
            'driver' => $result['driver'] ?? $validated['driver'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Step 5: Database Initialization and Migrations.
     */
    public function showMigrations(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $state = $this->stateService->getState();
        $knownMigrations = DatabaseInstallerService::KNOWN_MIGRATIONS;

        return view('install.steps.migrations', [
            'state' => $state,
            'knownMigrations' => $knownMigrations,
            'stepNumber' => 5,
            'totalSteps' => 9,
        ]);
    }

    public function storeMigrations(): RedirectResponse
    {
        try {
            $result = $this->installer->runMigrationsAndSeedRoles();

            $this->stateService->markStepCompleted(InstallationStateService::STATE_MIGRATION, [
                'migrations_count' => $result['count'],
            ]);

            return redirect()->route('install.step.storage');
        } catch (Throwable $e) {
            $sanitized = $this->installer->sanitizeErrorMessage($e->getMessage());
            $this->stateService->recordFailure(InstallationStateService::STATE_MIGRATION, $sanitized);

            return back()->withErrors(['migrations' => "Database migration failed: {$sanitized}"]);
        }
    }

    /**
     * Step 6: Filesystem and Storage Setup.
     */
    public function showStorage(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $storage = $this->installer->verifyFilesystemStorage();
        $state = $this->stateService->getState();

        return view('install.steps.storage', [
            'storage' => $storage,
            'state' => $state,
            'stepNumber' => 6,
            'totalSteps' => 9,
        ]);
    }

    public function storeStorage(): RedirectResponse
    {
        $storage = $this->installer->verifyFilesystemStorage();
        if (! $storage['compatible']) {
            return back()->withErrors([
                'storage' => 'One or more storage directories are missing or not writable. Please check permissions.',
            ]);
        }

        $this->stateService->markStepCompleted(InstallationStateService::STATE_FILESYSTEM);

        return redirect()->route('install.step.academy');
    }

    /**
     * Step 7: Academy Configuration.
     */
    public function showAcademy(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $state = $this->stateService->getState();

        return view('install.steps.academy', [
            'state' => $state,
            'stepNumber' => 7,
            'totalSteps' => 9,
        ]);
    }

    public function storeAcademy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academy_name' => ['required', 'string', 'min:2', 'max:255'],
            'academy_slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/i'],
            'support_email' => ['required', 'email', 'max:255'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'locale' => ['nullable', 'string', 'in:en,es,fr,de'],
            'currency' => ['nullable', 'string', 'max:10'],
            'date_format' => ['nullable', 'string', 'in:Y-m-d,m/d/Y,d/m/Y,M j, Y'],
            'time_format' => ['nullable', 'string', 'in:H:i,h:i A'],
        ]);

        try {
            $academy = $this->installer->configureAcademyProfile([
                'name' => $validated['academy_name'],
                'slug' => $validated['academy_slug'] ?? $validated['academy_name'],
                'support_email' => $validated['support_email'],
                'timezone' => $validated['timezone'] ?? 'UTC',
                'locale' => $validated['locale'] ?? 'en',
                'currency' => $validated['currency'] ?? 'USD',
                'date_format' => $validated['date_format'] ?? 'Y-m-d',
                'time_format' => $validated['time_format'] ?? 'H:i',
            ]);

            $this->stateService->markStepCompleted(InstallationStateService::STATE_ACADEMY, [
                'academy_id' => $academy->id,
                'academy_name' => $academy->name,
                'support_email' => $academy->support_email,
            ]);

            return redirect()->route('install.step.admin');
        } catch (Throwable $e) {
            $sanitized = $this->installer->sanitizeErrorMessage($e->getMessage());

            return back()->withInput()->withErrors(['academy' => "Failed to save academy profile: {$sanitized}"]);
        }
    }

    /**
     * Step 8: First Administrator Creation.
     */
    public function showAdmin(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $state = $this->stateService->getState();

        return view('install.steps.admin', [
            'state' => $state,
            'stepNumber' => 8,
            'totalSteps' => 9,
        ]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'admin_name' => ['required', 'string', 'min:2', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        try {
            $admin = $this->installer->createFirstAdministrator([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => $validated['admin_password'],
            ]);

            $this->stateService->markStepCompleted(InstallationStateService::STATE_ADMIN, [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
            ]);

            return redirect()->route('install.step.finalization');
        } catch (Throwable $e) {
            $sanitized = $this->installer->sanitizeErrorMessage($e->getMessage());

            return back()
                ->withInput($request->except(['admin_password', 'admin_password_confirmation']))
                ->withErrors(['admin' => "Failed to establish administrator account: {$sanitized}"]);
        }
    }

    /**
     * Step 9: Finalization.
     */
    public function showFinalization(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $state = $this->stateService->getState();
        $diagnostics = $this->installer->getSystemDiagnostics();

        return view('install.steps.finalization', [
            'state' => $state,
            'diagnostics' => $diagnostics,
            'stepNumber' => 9,
            'totalSteps' => 9,
        ]);
    }

    public function storeFinalization(): RedirectResponse
    {
        try {
            $this->installer->finalizeInstallation();
            $this->stateService->finalize();

            $state = $this->stateService->getState();

            return redirect()
                ->route('install.complete')
                ->with('install_completed', true)
                ->with('academy_name', $state['academy_name'] ?? 'Your Academy')
                ->with('admin_email', $state['admin_email'] ?? 'Administrator');
        } catch (Throwable $e) {
            $sanitized = $this->installer->sanitizeErrorMessage($e->getMessage());

            return back()->withErrors(['finalization' => "Finalization failed: {$sanitized}"]);
        }
    }

    /**
     * Installation Completed Screen.
     */
    public function complete(): View|RedirectResponse
    {
        if (! $this->installer->isInstalled()) {
            return redirect()->route('install.index');
        }

        return view('install.complete', [
            'academyName' => session('academy_name', 'Your Academy'),
            'adminEmail' => session('admin_email', 'Administrator'),
        ]);
    }

    /**
     * System Diagnostics Page.
     */
    public function diagnostics(): View
    {
        $diagnostics = $this->installer->getSystemDiagnostics();
        $isInstalled = $this->installer->isInstalled();

        return view('install.diagnostics', [
            'diagnostics' => $diagnostics,
            'isInstalled' => $isInstalled,
        ]);
    }

    /**
     * Download Sanitized Diagnostics Report.
     */
    public function downloadDiagnostics(): Response
    {
        $report = $this->installer->getSanitizedDiagnosticsReport();
        $filename = 'seo-academy-diagnostics-'.date('Ymd_His').'.json';

        return response($report, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Safe reset of uncompleted installation.
     */
    public function reset(): RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $this->stateService->reset();

        return redirect()->route('install.step.requirements');
    }
}
