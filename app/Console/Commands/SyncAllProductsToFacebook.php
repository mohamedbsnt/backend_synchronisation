<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Jobs\SyncProductsBatchToFacebook;
use Illuminate\Support\Facades\Log;

class SyncAllProductsToFacebook extends Command
{
    protected $signature = 'facebook:sync-all {--source=api}';
    protected $description = 'Fetch all products from FEED_SOURCE_API and push to Facebook (batch)';

    public function handle()
    {
        $api = env('FEED_SOURCE_API');
        if (empty($api)) {
            $this->error('FEED_SOURCE_API not configured');
            return 1;
        }

        $resp = Http::timeout(60)->get($api);
        if (!$resp->successful()) {
            $this->error('Failed to fetch API: ' . $resp->status());
            Log::error('SyncAllProductsToFacebook failed: ' . $resp->status());
            return 1;
        }

        $data = $resp->json();
        $products = $data['products'] ?? [];
        if (empty($products)) {
            $this->warn('No products found');
            return 0;
        }

        SyncProductsBatchToFacebook::dispatch($products, 50);
        $this->info('Dispatched SyncProductsBatchToFacebook job.');
        return 0;
    }
}
