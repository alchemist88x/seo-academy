<?php

namespace App\Http\Controllers;

use App\Domain\Platform\Services\DatabaseInstallerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class InstallController extends Controller
{
    public function __construct(
        private readonly DatabaseInstallerService $installer,
    ) {}

    public function index(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('login');
        }

        $requirements = $this->installer->checkSystemRequirements();

        return view('install.database', [
            'requirements' => $requirements,
            'isInstalled' => false,
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        if ($this->installer->isInstalled()) {
            return response()->json([
                'ok' => false,
                'driver' => $request->input('driver', 'sqlite'),
                'message' => 'Application is already installed or installation is locked.',
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
                'host' => (string) ($validated['host'] ?? ''),
                'port' => $validated['port'] ?? 3306,
                'database' => (string) ($validated['database'] ?? ''),
                'username' => (string) ($validated['username'] ?? ''),
                'password' => (string) ($validated['password'] ?? ''),
            ]);
        }

        return response()->json([
            'ok' => $result['ok'],
            'driver' => $result['driver'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

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

    public function complete(): View|RedirectResponse
    {
        // Completion screen viewable immediately after installation
        if (! $this->installer->isInstalled()) {
            return redirect()->route('install.index');
        }

        return view('install.complete', [
            'academyName' => session('academy_name', 'Your Academy'),
            'adminEmail' => session('admin_email', 'Administrator'),
        ]);
    }
}
