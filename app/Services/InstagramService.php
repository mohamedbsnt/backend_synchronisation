<?php

namespace App\Services;

use App\Services\FacebookCatalogService;
use Illuminate\Support\Facades\Log;

class InstagramService
{
    protected FacebookCatalogService $fb;

    public function __construct(FacebookCatalogService $fb)
    {
        $this->fb = $fb;
    }

    /**
     * Upsert single product into Facebook Catalog (makes it available on Instagram)
     */
    public function upsertProduct(array $product): array
    {
        try {
            return $this->fb->upsertSingleProduct($product);
        } catch (\Throwable $e) {
            Log::error('InstagramService upsertProduct error: ' . $e->getMessage(), ['product' => $product]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Upsert batch of products
     */
    public function upsertBatch(array $products): array
    {
        try {
            return $this->fb->upsertBatch($products);
        } catch (\Throwable $e) {
            Log::error('InstagramService upsertBatch error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
