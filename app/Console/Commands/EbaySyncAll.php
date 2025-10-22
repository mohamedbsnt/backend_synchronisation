<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Jobs\SyncProductToEbay;

class EbaySyncAll extends Command
{
    protected $signature = 'ebay:sync-all {--source=db}';
    protected $description = 'Dispatch sync jobs for all products to eBay';

    public function handle()
    {
        $this->info('Dispatching eBay sync jobs for products...');
        $products = Product::with('categories')->active()->get();

        foreach ($products as $product) {
            SyncProductToEbay::dispatch($product, 'insert');
            $this->line("Dispatched product {$product->id}");
        }

        $this->info('All dispatched — start queue worker to process them.');
    }
}
