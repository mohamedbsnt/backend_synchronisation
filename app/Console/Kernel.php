<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * If a command class listed here does not exist, remove it or create it.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        \App\Console\Commands\GenerateFacebookFeed::class,
        \App\Console\Commands\GenerateGoogleFeed::class,
        \App\Console\Commands\SyncAllProductsToFacebook::class,
        \App\Console\Commands\SyncAllToAmazonCommand::class,
        \App\Console\Commands\InstagramSyncAll::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Génération des feeds
        $schedule->command('feed:generate-facebook --source=api')
                 ->dailyAt('03:00')
                 ->withoutOverlapping();

        $schedule->command('feed:generate-google --source=api')
                 ->dailyAt('03:10')
                 ->withoutOverlapping();

        // Synchronisations complètes
        $schedule->command('facebook:sync-all')
                 ->dailyAt('02:50')
                 ->withoutOverlapping();

        $schedule->command('amazon:sync-all')
                 ->dailyAt('02:30')
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
