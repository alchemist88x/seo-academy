<?php

namespace Database\Seeders;

use App\Domain\Platform\Services\LocalDemoAccountService;
use Illuminate\Database\Seeder;

class LocalDemoAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = app(LocalDemoAccountService::class);
        $service->restoreDemoAccounts();
    }
}
