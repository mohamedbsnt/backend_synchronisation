<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\GenerateFacebookFeed::class,
        \App\Console\Commands\GenerateGoogleFeed::class, // if exists
        \App\Console\Commands\RegisterFacebookFeed::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('feed:generate-facebook --source=api')->dailyAt('03:00')->withoutOverlapping();
        // If you have Google feed command
        $schedule->command('feed:generate-google --source=api')->dailyAt('03:10')->withoutOverlapping();

        $schedule->command('devaito:sync')->hourly();
        $schedule->command('google:generate-feed')->hourly()->withoutOverlapping();

    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
