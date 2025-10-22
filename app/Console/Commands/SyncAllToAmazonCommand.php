<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SyncAllProductsToAmazon;

class SyncAllToAmazonCommand extends Command
{
    protected $signature = 'amazon:sync-all';
    protected $description = 'Push all (active,in stock) products to Amazon SP-API as JSON_LISTINGS_FEED';

    public function handle()
    {
        $this->info('Dispatching SyncAllProductsToAmazon job...');
        SyncAllProductsToAmazon::dispatch();
        $this->info('Job dispatched.');
        return 0;
    }
}
