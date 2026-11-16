<?php

namespace App\Console\Commands;

use App\Domain\Platform\Services\LocalDemoAccountService;
use Illuminate\Console\Command;
use Throwable;

class ResetDemoAccountPasswordsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-demo-account-passwords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset and generate cryptographically secure fresh passwords for the five approved .local demonstration accounts.';

    /**
     * Execute the console command.
     */
    public function handle(LocalDemoAccountService $service): int
    {
        $currentEnv = app()->environment();

        if (app()->environment('production')) {
            $this->error('CRITICAL: Demo account passwords cannot be reset in a production environment.');

            return Command::FAILURE;
        }

        if (! app()->environment('local', 'development', 'testing')) {
            $this->error("Demo account passwords can only be reset in local or development environments. Current environment: [{$currentEnv}]");

            return Command::FAILURE;
        }

        $this->info('Resetting demonstration account credentials...');

        try {
            $accounts = $service->resetDemoAccountPasswords();
        } catch (Throwable $e) {
            $this->error('Failed to reset demo account passwords: '.$e->getMessage());

            return Command::FAILURE;
        }

        $tableRows = [];

        foreach ($accounts as $account) {
            $tableRows[] = [
                $account['name'],
                $account['email'],
                $account['role'],
                $account['status'],
                $account['password'],
            ];
        }

        $this->newLine();
        $this->table(
            ['Account Name', 'Email Address', 'Role', 'Status', 'New Password (Display Once)'],
            $tableRows
        );
        $this->newLine();

        $this->warn('IMPORTANT: The new passwords above are displayed exactly once.');
        $this->line('All passwords are cryptographically hashed in the database using bcrypt.');
        $this->line('Plaintext passwords are not stored in any table or log file.');
        $this->newLine();
        $this->info('Successfully reset passwords for all five approved demonstration accounts.');

        return Command::SUCCESS;
    }
}
