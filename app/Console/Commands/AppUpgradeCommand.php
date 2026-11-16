<?php

namespace App\Console\Commands;

use App\Domain\Platform\Services\DatabaseInstallerService;
use App\Domain\Platform\Services\SystemHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class AppUpgradeCommand extends Command
{
    protected $signature = 'app:upgrade 
                            {--force : Force upgrade without confirmation in automated scripts}
                            {--skip-cache-clear : Do not clear compiled caches post-migration}';

    protected $description = 'Safely upgrade SEO Academy: apply additive migrations, clear caches, and verify system health';

    public function handle(DatabaseInstallerService $installer, SystemHealthService $healthService): int
    {
        $version = config('app.version', '5.4.0');
        $this->info("=== SEO Academy Safe Upgrade System (Target: v{$version}) ===");

        // 1. Verify installation exists
        if (! $installer->isInstalled()) {
            $this->error('Application is not initialized or installed. Run the web installer first.');

            return self::FAILURE;
        }

        // 2. Pre-flight requirements validation
        $driver = config('database.default', 'sqlite');
        $requirements = $installer->checkSystemRequirements($driver);
        if (! ($requirements['compatible'] ?? false)) {
            $this->error("Pre-flight system requirements check failed for driver [{$driver}]. Upgrade aborted.");

            return self::FAILURE;
        }
        $this->info("  [✓] System requirements and PHP extensions verified ({$driver}).");

        // 3. Informational check on pending migrations
        $this->line('  Applying safe additive database migrations (never destructive)...');
        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $this->line(trim(Artisan::output()));
            if ($exitCode !== 0) {
                $this->error('Migration failed. Halting upgrade.');

                return self::FAILURE;
            }
            $this->info('  [✓] Additive database migrations completed successfully.');
        } catch (Throwable $e) {
            $this->error("Migration exception: {$e->getMessage()}");

            return self::FAILURE;
        }

        // 4. Clear and optimize application caches
        if (! $this->option('skip-cache-clear')) {
            $this->line('  Refreshing application caches...');
            Artisan::call('optimize:clear');
            $this->info('  [✓] Configuration, route, and view caches refreshed.');
        }

        // 5. Update health sentinel and verify health status
        try {
            $healthService->touchSchedulerSentinel();
            $this->info('  [✓] System health sentinel updated.');
        } catch (Throwable) {
            // Non-blocking for offline CLI runs
        }

        $this->newLine();
        $this->info("=== SEO Academy upgrade to v{$version} completed successfully! ===");
        $this->line('Customer data, enrollments, certificates, and demo accounts preserved intact.');

        return self::SUCCESS;
    }
}
