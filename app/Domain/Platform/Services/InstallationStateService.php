<?php

namespace App\Domain\Platform\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallationStateService
{
    public const STATE_NOT_STARTED = 'not_started';
    public const STATE_REQUIREMENTS = 'requirements_check';
    public const STATE_DEPENDENCIES = 'dependencies_check';
    public const STATE_ENVIRONMENT = 'environment_setup';
    public const STATE_DATABASE_CONFIG = 'database_configuration';
    public const STATE_MIGRATION = 'migration';
    public const STATE_FILESYSTEM = 'filesystem_setup';
    public const STATE_ACADEMY = 'academy_configuration';
    public const STATE_ADMIN = 'administrator_creation';
    public const STATE_FINALIZATION = 'finalization';
    public const STATE_COMPLETED = 'completed';
    public const STATE_FAILED_RECOVERABLE = 'failed_recoverable';

    public const STEPS_ORDER = [
        self::STATE_REQUIREMENTS => 1,
        self::STATE_DEPENDENCIES => 2,
        self::STATE_ENVIRONMENT => 3,
        self::STATE_DATABASE_CONFIG => 4,
        self::STATE_MIGRATION => 5,
        self::STATE_FILESYSTEM => 6,
        self::STATE_ACADEMY => 7,
        self::STATE_ADMIN => 8,
        self::STATE_FINALIZATION => 9,
    ];

    public function __construct(
        private readonly ?string $customStatePath = null,
    ) {}

    public function stateFilePath(): string
    {
        return $this->customStatePath ?? storage_path('app/private/installer_state.json');
    }

    /**
     * Retrieve the persistent installation state.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $path = $this->stateFilePath();
        if (! file_exists($path)) {
            return $this->getDefaultState();
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return $this->getDefaultState();
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return $this->getDefaultState();
        }

        return array_merge($this->getDefaultState(), $decoded);
    }

    /**
     * Default empty state structure.
     *
     * @return array<string, mixed>
     */
    public function getDefaultState(): array
    {
        return [
            'status' => self::STATE_NOT_STARTED,
            'current_step' => self::STATE_REQUIREMENTS,
            'completed_steps' => [],
            'session_id' => null,
            'driver' => 'sqlite',
            'db_config' => [],
            'academy_config' => [],
            'last_error' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Save the updated installation state to disk safely.
     *
     * @param array<string, mixed> $state
     */
    public function saveState(array $state): void
    {
        $path = $this->stateFilePath();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        $state['updated_at'] = now()->toIso8601String();
        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Initialize or resume installation session.
     */
    public function initializeSession(string $sessionId): array
    {
        $state = $this->getState();

        if (empty($state['session_id'])) {
            $state['session_id'] = $sessionId;
            $this->saveState($state);
        }

        return $state;
    }

    /**
     * Verify if the active session is authorized to proceed with installation.
     */
    public function isSessionAuthorized(string $sessionId): bool
    {
        $state = $this->getState();
        if (empty($state['session_id'])) {
            return true;
        }

        return hash_equals((string) $state['session_id'], $sessionId);
    }

    /**
     * Record a step as completed and advance current step.
     */
    public function markStepCompleted(string $step, array $data = []): void
    {
        $state = $this->getState();

        if (! in_array($step, $state['completed_steps'], true)) {
            $state['completed_steps'][] = $step;
        }

        // Merge any step-specific data (e.g. db_config, academy_config)
        foreach ($data as $key => $val) {
            $state[$key] = $val;
        }

        $state['last_error'] = null;
        $state['status'] = 'in_progress';

        // Advance to next step
        $nextStep = $this->getNextStep($step);
        if ($nextStep !== null) {
            $state['current_step'] = $nextStep;
        }

        $this->saveState($state);
    }

    /**
     * Record a failure on a step, allowing recovery.
     */
    public function recordFailure(string $step, string $errorMessage): void
    {
        $state = $this->getState();
        $state['status'] = self::STATE_FAILED_RECOVERABLE;
        $state['current_step'] = $step;
        $state['last_error'] = $errorMessage;
        $this->saveState($state);
    }

    /**
     * Check if a specific step is completed.
     */
    public function isStepCompleted(string $step): bool
    {
        $state = $this->getState();
        return in_array($step, $state['completed_steps'], true);
    }

    /**
     * Get the next logical step in the wizard.
     */
    public function getNextStep(string $currentStep): ?string
    {
        $steps = array_keys(self::STEPS_ORDER);
        $idx = array_search($currentStep, $steps, true);

        if ($idx !== false && isset($steps[$idx + 1])) {
            return $steps[$idx + 1];
        }

        return self::STATE_FINALIZATION;
    }

    /**
     * Determine the route name corresponding to a step.
     */
    public function getRouteForStep(string $step): string
    {
        return match ($step) {
            self::STATE_REQUIREMENTS => 'install.step.requirements',
            self::STATE_DEPENDENCIES => 'install.step.dependencies',
            self::STATE_ENVIRONMENT => 'install.step.environment',
            self::STATE_DATABASE_CONFIG => 'install.step.database',
            self::STATE_MIGRATION => 'install.step.migrations',
            self::STATE_FILESYSTEM => 'install.step.storage',
            self::STATE_ACADEMY => 'install.step.academy',
            self::STATE_ADMIN => 'install.step.admin',
            self::STATE_FINALIZATION => 'install.step.finalization',
            self::STATE_COMPLETED => 'install.complete',
            default => 'install.step.requirements',
        };
    }

    /**
     * Reset installation progress (used when customer restarts wizard before completion).
     */
    public function reset(): void
    {
        $path = $this->stateFilePath();
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Finalize installation and clear transient state.
     */
    public function finalize(): void
    {
        $state = $this->getState();
        $state['status'] = self::STATE_COMPLETED;
        $state['completed_steps'] = array_keys(self::STEPS_ORDER);
        $state['current_step'] = self::STATE_COMPLETED;
        // Strip sensitive credentials before retaining audit record
        unset($state['db_config']['password']);
        $this->saveState($state);
    }
}
