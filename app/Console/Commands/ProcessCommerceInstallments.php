<?php

namespace App\Console\Commands;

use App\Domain\Commerce\Services\InstallmentMilestoneService;
use Illuminate\Console\Command;

class ProcessCommerceInstallments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commerce:process-installments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process course completion milestones and update due/overdue installment payments';

    /**
     * Execute the console command.
     */
    public function handle(InstallmentMilestoneService $milestoneService): int
    {
        $this->info('Scanning commerce installments for milestones and overdue dates...');

        $results = $milestoneService->processAllActiveInstallments();

        $this->info("Completed. Milestones triggered: {$results['milestones_triggered']}, Marked overdue: {$results['marked_overdue']}");

        return Command::SUCCESS;
    }
}
