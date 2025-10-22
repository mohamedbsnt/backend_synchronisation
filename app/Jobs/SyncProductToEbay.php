<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\EbayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToEbay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Product|array $product;
    public string $action;

    public $tries = 3;
    public $timeout = 120;

    public function __construct($product, string $action = 'insert')
    {
        $this->product = $product;
        $this->action = $action;
    }

    public function handle(EbayService $ebay)
    {
        try {
            $p = $this->product instanceof Product
                ? $this->mapProduct($this->product)
                : $this->product;

            if ($this->action === 'delete') {
                $ebay->deleteProduct($p);
                Log::info('SyncProductToEbay deleted', ['id' => $p['id']]);
                return;
            }

            $res = $ebay->upsertProduct($p);
            Log::info('SyncProductToEbay upserted', ['id' => $p['id'], 'res' => $res]);
        } catch (\Throwable $e) {
            Log::error('SyncProductToEbay error: '.$e->getMessage(), ['id' => $this->product instanceof Product ? $this->product->id : ($this->product['id'] ?? null)]);
            throw $e;
        }
    }

    protected function mapProduct(Product $product): array
    {
        return [
            'id' => (string)$product->id,
            'name' => $product->name,
            'description' => $product->description ?? $product->name,
            'price' => number_format($product->final_price ?? $product->price ?? 0, 2, '.', ''),
            'currency' => $product->currency ?? ($product->devise ?? env('FEED_DEFAULT_CURRENCY','MAD')),
            'stock' => $product->stock ?? 1,
            'image' => $product->full_image_url ?? $product->image ?? null,
            'brand_name' => $product->brand_name ?? env('FEED_DEFAULT_BRAND','Hanaball'),
            'mpn' => $product->mpn ?? null,
            'gtin' => $product->gtin ?? null,
            'categories' => $product->categories->map(fn($c)=>['id'=>$c->id,'name'=>$c->name])->toArray(),
            'category_id' => $product->categories->first()->id ?? null,
        ];
    }
}
