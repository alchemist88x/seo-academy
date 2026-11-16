<?php

use App\Domain\Platform\Services\DatabaseImmutabilityService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = new DatabaseImmutabilityService;
        $service->installTriggers();
    }

    public function down(): void
    {
        $service = new DatabaseImmutabilityService;
        $service->dropTriggers();
    }
};
