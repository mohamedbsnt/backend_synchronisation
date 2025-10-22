<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Jobs\SyncProductsBatchToInstagram;
use Illuminate\Support\Facades\Log;

class InstagramSyncAll extends Command
{
    protected $signature = 'instagram:sync-all {--source=api}';
    protected $description = 'Fetch all products from FEED_SOURCE_API and push to Instagram (via Facebook Catalog)';

    public function handle()
    {
        $api = env('FEED_SOURCE_API');
        if (empty($api)) {
            $this->error('FEED_SOURCE_API not configured in .env');
            return 1;
        }

        $resp = Http::timeout(60)->get($api);
        if (!$resp->successful()) {
            $this->error('Failed to fetch API: ' . $resp->status());
            Log::error('InstagramSyncAll failed: ' . $resp->status());
            return 1;
        }

        $data = $resp->json();
        $products = $data['products'] ?? [];
        if (empty($products)) {
            $this->warn('No products found');
            return 0;
        }

        // normalize mapping if needed (we pass array items as is, InstagramService will map)
        SyncProductsBatchToInstagram::dispatch($products, 50);
        $this->info('Dispatched SyncProductsBatchToInstagram job.');
        return 0;
    }
}
