<?php

use App\Domain\Platform\Services\SystemHealthService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('enrollments:process-expired')->hourly();
Schedule::command('assessments:finalize-expired')->everyMinute()->withoutOverlapping(10);
Schedule::command('certificates:process-pdfs --limit=25')->everyFiveMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    app(SystemHealthService::class)->touchSchedulerSentinel();
})->everyTenMinutes()->name('system:health-sentinel');
