<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\SyncGoogleMerchantProducts::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Synchronisation quotidienne à 3h00 du matin
        $schedule->command('google:sync-products')
                 ->dailyAt('03:00')
                 ->withoutOverlapping()
                 ->emailOutputOnFailure('admin@hanaball.devaito.com');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}