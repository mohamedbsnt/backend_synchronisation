<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Artisan;

class RegenerateGoogleFeed implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle()
    {
        Artisan::call('feed:generate-google');
    }
}
