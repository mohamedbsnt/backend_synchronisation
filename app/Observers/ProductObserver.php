<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

// Jobs (peuvent être absents, on vérifie avec class_exists)
use App\Jobs\SyncProductToAmazon;
use App\Jobs\SyncAllProductsToAmazon;
use App\Jobs\SyncProductToFacebook;
use App\Jobs\SyncProductToGoogleMerchant;
use App\Jobs\SyncProductToInstagram;

class ProductObserver
{
    public function created(Product $product): void
    {
        Log::info('ProductObserver: created', ['id' => $product->id]);

        $payload = $this->mapProduct($product);

        // Facebook / Instagram expect array payload
        if (class_exists(SyncProductToFacebook::class)) {
            SyncProductToFacebook::dispatch($payload);
        }

        if (class_exists(SyncProductToInstagram::class)) {
            SyncProductToInstagram::dispatch($payload);
        }

        // Google & Amazon jobs often accept the Product model and an action string
        if (class_exists(SyncProductToGoogleMerchant::class)) {
            SyncProductToGoogleMerchant::dispatch($product, 'insert');
        }

        if (class_exists(SyncProductToAmazon::class)) {
            SyncProductToAmazon::dispatch($product, 'insert');
        }
    }

    public function updated(Product $product): void
    {
        Log::info('ProductObserver: updated', ['id' => $product->id]);

        $payload = $this->mapProduct($product);

        if (class_exists(SyncProductToFacebook::class)) {
            SyncProductToFacebook::dispatch($payload);
        }

        if (class_exists(SyncProductToInstagram::class)) {
            SyncProductToInstagram::dispatch($payload);
        }

        if (class_exists(SyncProductToGoogleMerchant::class)) {
            SyncProductToGoogleMerchant::dispatch($product, 'update');
        }

        if (class_exists(SyncProductToAmazon::class)) {
            SyncProductToAmazon::dispatch($product, 'update');
        }
    }

    public function deleted(Product $product): void
    {
        Log::info('ProductObserver: deleted', ['id' => $product->id]);

        $payload = $this->mapProduct($product);
        // For feeds, mark as out of stock / unavailable so platforms remove/hide
        $payload['availability'] = 'out of stock';

        if (class_exists(SyncProductToFacebook::class)) {
            SyncProductToFacebook::dispatch($payload);
        }

        if (class_exists(SyncProductToInstagram::class)) {
            SyncProductToInstagram::dispatch($payload);
        }

        if (class_exists(SyncProductToGoogleMerchant::class)) {
            SyncProductToGoogleMerchant::dispatch($product, 'delete');
        }

        if (class_exists(SyncProductToAmazon::class)) {
            SyncProductToAmazon::dispatch($product, 'delete');
        }
    }

    /**
     * Map Product model to a normalized array payload for Facebook/Instagram feeds.
     */
    protected function mapProduct(Product $product): array
    {
        // Use accessors if present, fallback to attributes
        $image = $product->full_image_url ?? ($product->image ?? null);
        $url = $product->devaito_link ?? ($product->url ?? url('/product/' . ($product->slug ?? $product->id)));
        $price = isset($product->final_price) ? number_format($product->final_price, 2, '.', '') : (isset($product->price) ? number_format($product->price, 2, '.', '') : '');

        return [
            'id' => $product->id,
            'retailer_id' => $product->id,                 // utile pour idempotence sur FB
            'title' => $product->name,
            'name' => $product->name,
            'description' => $product->description ?: $product->name,
            'link' => $url,
            'url' => $url,
            'image' => $image,
            'image_link' => $image,
            'additional_image_link' => '',                 // si tu as plusieurs images, join par '|'
            'price' => $price,
            'currency' => $product->currency ?? ($product->devise ?? env('FEED_DEFAULT_CURRENCY', 'MAD')),
            'availability' => $product->availability ?? ((int)($product->stock ?? 0) > 0 ? 'in stock' : 'out of stock'),
            'condition' => 'new',
            'brand_name' => $product->brand_name ?: env('FEED_DEFAULT_BRAND', 'Hanaball'),
            'gtin' => $product->gtin ?? null,
            'mpn' => $product->mpn ?? null,
            'discount_amount' => isset($product->discount_amount) ? number_format($product->discount_amount, 2, '.', '') : null,
            'categories' => $product->categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray(),
        ];
    }
}
