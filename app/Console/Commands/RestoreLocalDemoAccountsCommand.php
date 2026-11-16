<?php

namespace App\Console\Commands;

use App\Domain\Platform\Services\LocalDemoAccountService;
use Illuminate\Console\Command;
use Throwable;

class RestoreLocalDemoAccountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:restore-demo-accounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely and idempotently restore the five approved .local demonstration accounts.';

    /**
     * Execute the console command.
     */
    public function handle(LocalDemoAccountService $service): int
    {
        $currentEnv = app()->environment();

        if (app()->environment('production')) {
            $this->error('CRITICAL: Demo accounts cannot be created or restored in a production environment.');

            return Command::FAILURE;
        }

        if (! app()->environment('local', 'development', 'testing')) {
            $this->error("Demo accounts can only be restored in local or development environments. Current environment: [{$currentEnv}]");

            return Command::FAILURE;
        }

        $this->info('Restoring approved local demo accounts...');

        try {
            $accounts = $service->restoreDemoAccounts();
        } catch (Throwable $e) {
            $this->error('Failed to restore demo accounts: '.$e->getMessage());

            return Command::FAILURE;
        }

        $tableRows = [];
        $hasNewCredentials = false;

        foreach ($accounts as $account) {
            if ($account['password'] !== null) {
                $hasNewCredentials = true;
                $passwordDisplay = $account['password'];
            } else {
                $passwordDisplay = '[Retained - Unchanged]';
            }

            $tableRows[] = [
                $account['name'],
                $account['email'],
                $account['role'],
                $account['status'],
                $passwordDisplay,
            ];
        }

        $this->newLine();
        $this->table(
            ['Account Name', 'Email Address', 'Role', 'Status', 'Initial Password (One-Time)'],
            $tableRows
        );
        $this->newLine();

        if ($hasNewCredentials) {
            $this->warn('IMPORTANT: Initial passwords displayed above are generated once.');
            $this->line('Passwords are securely hashed in the database and never stored in plaintext.');
            $this->line('Save these credentials now if you need to sign in locally.');
        } else {
            $this->info('All five demo accounts already exist. Passwords were preserved and unchanged.');
        }

        $this->newLine();
        $this->info('Successfully verified all five approved demonstration accounts.');

        return Command::SUCCESS;
    }
}
