<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\AmazonSpApiService;
use App\Models\Product;

class SyncProductToAmazon implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected Product $product;
    protected string $action;

    public function __construct(Product $product, string $action = 'update')
    {
        $this->product = $product;
        $this->action = $action;
    }

    public function handle(AmazonSpApiService $amazon)
    {
        $p = $this->product;
        $payload = [
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

        // For single product, send as a single-item feed
        $res = $amazon->submitListingsFeed([$payload]);

        // You could save response / feed id on the product if needed
        if (!empty($res['feedId'])) {
            $p->update(['amazon_status' => 'submitted']);
        }
    }
}
