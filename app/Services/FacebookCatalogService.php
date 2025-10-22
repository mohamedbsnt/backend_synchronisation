<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookCatalogService
{
    protected string $token;
    protected string $catalogId;
    protected string $version;

    public function __construct()
    {
        $this->token = env('FACEBOOK_ACCESS_TOKEN', '');
        $this->catalogId = env('FACEBOOK_CATALOG_ID', '');
        $this->version = env('FACEBOOK_API_VERSION', 'v16.0');

        if (empty($this->token) || empty($this->catalogId)) {
            Log::warning('FacebookCatalogService: missing token or catalog id.');
        }
    }

    public function upsertSingleProduct(array $product): array
    {
        return $this->upsertBatch([$product]);
    }

    public function upsertBatch(array $products): array
    {
        if (empty($products)) return ['success' => true, 'message' => 'No products'];

        $endpoint = "https://graph.facebook.com/{$this->version}/{$this->catalogId}/items_batch";

        $requests = [];
        foreach ($products as $p) {
            $body = $this->buildPayload($p);
            $requests[] = [
                'method' => 'POST',
                'relative_url' => "/{$this->catalogId}/products",
                'body' => http_build_query($body),
            ];
        }

        $params = [
            'requests' => json_encode($requests),
            'access_token' => $this->token,
        ];

        $resp = Http::asForm()->post($endpoint, $params);
        $result = $resp->json();

        if ($resp->successful()) {
            Log::info('Facebook items_batch success', ['count' => count($products)]);
        } else {
            Log::error('Facebook items_batch error', ['status' => $resp->status(), 'response' => $result]);
        }

        return $result;
    }

    protected function buildPayload(array $p): array
    {
        $price = isset($p['price']) ? number_format((float)$p['price'], 2, '.', '') . ' ' . ($p['devise'] ?? env('FEED_DEFAULT_CURRENCY','MAD')) : '';

        $payload = [
            'retailer_id' => (string)($p['id'] ?? $p['sku'] ?? uniqid('p_')),
            'name' => $p['name'] ?? '',
            'description' => $p['description'] ?? ($p['name'] ?? ''),
            'url' => $p['url'] ?? ($p['devaito_link'] ?? config('app.url')),
            'image_url' => $p['image'] ?? '',
            'price' => $price,
            'availability' => $p['availability'] ?? ($p['stock'] ?? 'in stock'),
            'condition' => $p['condition'] ?? 'new',
            'brand' => $p['brand_name'] ?? env('FEED_DEFAULT_BRAND',''),
        ];

        if (!empty($p['discount_amount'])) {
            $sale = (float)$p['price'] - (float)$p['discount_amount'];
            $payload['sale_price'] = number_format(max(0, $sale), 2, '.', '') . ' ' . ($p['devise'] ?? env('FEED_DEFAULT_CURRENCY','MAD'));
        }

        if (!empty($p['gtin'])) $payload['gtin'] = $p['gtin'];
        if (!empty($p['mpn'])) $payload['mpn'] = $p['mpn'];
        if (!empty($p['additional_images']) && is_array($p['additional_images'])) {
            $payload['additional_image_url'] = implode('|', $p['additional_images']);
        }
        if (!empty($p['categories']) && is_array($p['categories'])) {
            $payload['product_type'] = $p['categories'][0]['name'] ?? '';
        }

        return $payload;
    }
}
