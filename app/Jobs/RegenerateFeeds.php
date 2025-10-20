<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RegenerateFeeds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function handle(): void
    {
        try {
            Artisan::call('feed:generate-google', ['--source' => 'api']);
            Artisan::call('feed:generate-facebook', ['--source' => 'api']);
            Log::info('🧠 Job RegenerateFeeds exécuté : feeds régénérés.');
        } catch (\Throwable $e) {
            Log::error('❌ Erreur job RegenerateFeeds : ' . $e->getMessage());
        }
    }
}
