<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\AmazonSpApiService;
use App\Models\Product;

class SyncAllProductsToAmazon implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(AmazonSpApiService $amazon)
    {
        $products = Product::active()->inStock()->get()->map(function($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price' => number_format($p->final_price, 2, '.', ''),
                'currency' => $p->currency,
                'image' => $p->full_image_url,
                'url' => $p->devaito_link,
                'brand_name' => $p->brand_name ?: env('FEED_DEFAULT_BRAND'),
                'amazon_sku' => $p->amazon_sku ?: 'HANABALL-' . $p->id,
                'product_type' => $p->google_product_category ?: 'miscellaneous',
            ];
        })->toArray();

        // chunk to avoid huge feed sizes
        $chunks = array_chunk($products, 200);
        foreach ($chunks as $chunk) {
            $amazon->submitListingsFeed($chunk);
            sleep(1); // small pause between submissions
        }
    }
}
