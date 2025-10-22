<?php

namespace App\Services;

use App\Models\EbayToken;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class EbayService
{
    protected Client $http;
    protected string $env;
    protected string $clientId;
    protected string $clientSecret;
    protected string $apiBase;
    protected string $identityBase;

    public function __construct()
    {
        $this->env = env('EBAY_ENVIRONMENT', 'sandbox');
        $this->clientId = env('EBAY_CLIENT_ID');
        $this->clientSecret = env('EBAY_CLIENT_SECRET');

        $this->identityBase = $this->env === 'production' ? 'https://api.ebay.com' : 'https://api.sandbox.ebay.com';
        $this->apiBase = $this->env === 'production' ? 'https://api.ebay.com' : 'https://api.sandbox.ebay.com';

        $this->http = new Client([
            'base_uri' => $this->apiBase,
            'timeout' => 30,
        ]);
    }

    /**
     * Get a valid access token (refresh if needed)
     */
    public function getAccessToken(): string
    {
        // prefer stored model
        $token = EbayToken::current($this->env);
        if ($token && $token->isAccessTokenValid()) {
            return $token->access_token;
        }

        // if we have refresh token saved
        $refreshToken = $token->refresh_token ?? env('EBAY_REFRESH_TOKEN');

        if (!$refreshToken) {
            throw new \RuntimeException('No eBay refresh token found. Obtain one via OAuth flow.');
        }

        // request new access token using refresh token
        $res = (new Client(['base_uri' => $this->identityBase]))->post('/identity/v1/oauth2/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            ],
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope' => '' // optional, not needed when using refresh token
            ],
        ]);

        $body = json_decode((string)$res->getBody(), true);

        if (empty($body['access_token'])) {
            Log::error('eBay token response', ['body' => $body]);
            throw new \RuntimeException('Failed to retrieve access token from eBay.');
        }

        $accessToken = $body['access_token'];
        $ttl = $body['expires_in'] ?? (int)env('EBAY_ACCESS_TOKEN_TTL', 3500);

        // store/update token record
        $data = [
            'environment' => $this->env,
            'refresh_token' => $refreshToken,
            'access_token' => $accessToken,
            'access_token_expires_at' => Carbon::now()->addSeconds($ttl),
            'scope' => $body['scope'] ?? null,
        ];
        EbayToken::updateOrCreate(['environment' => $this->env], $data);

        return $accessToken;
    }

    protected function authHeaders(): array
    {
        $token = $this->getAccessToken();
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Upsert inventory item (PUT /sell/inventory/v1/inventory_item/{sku})
     */
    public function upsertInventoryItem(array $payload, string $sku)
    {
        $url = "/sell/inventory/v1/inventory_item/{$sku}";
        $res = $this->http->put($url, [
            'headers' => $this->authHeaders(),
            'json' => $payload,
        ]);
        return json_decode((string)$res->getBody(), true);
    }

    /**
     * Create an offer for an inventory item (POST /sell/inventory/v1/offer)
     */
    public function createOffer(array $payload)
    {
        $url = "/sell/inventory/v1/offer";
        $res = $this->http->post($url, [
            'headers' => $this->authHeaders(),
            'json' => $payload,
        ]);
        return json_decode((string)$res->getBody(), true);
    }

    /**
     * Publish an offer (POST /sell/inventory/v1/offer/{offerId}/publish)
     */
    public function publishOffer(string $offerId)
    {
        $url = "/sell/inventory/v1/offer/{$offerId}/publish";
        $res = $this->http->post($url, [
            'headers' => $this->authHeaders(),
        ]);
        return $res->getStatusCode() === 204;
    }

    /**
     * Delete inventory item
     */
    public function deleteInventoryItem(string $sku)
    {
        $url = "/sell/inventory/v1/inventory_item/{$sku}";
        $res = $this->http->delete($url, [
            'headers' => $this->authHeaders(),
        ]);
        return $res->getStatusCode() === 204;
    }

    /**
     * High-level: create or update listing from product data map
     * - $product: array ou Model (id,name,description,price,currency,url,image,categories...)
     * - Strategy: use SKU = 'hanaball-{id}'
     */
    public function upsertProduct(array $product): array
    {
        $sku = 'hanaball-' . $product['id'];

        // Inventory item payload (simplified; extend as needed)
        $itemPayload = [
            'product' => [
                'title' => $product['name'],
                'description' => $product['description'] ?? $product['name'],
                'aspects' => new \stdClass(), // optional attributes like color/size
                'brand' => $product['brand_name'] ?? env('FEED_DEFAULT_BRAND', 'Hanaball'),
                'mpn' => $product['mpn'] ?? null,
                'gtin' => $product['gtin'] ?? null,
                'imageUrls' => isset($product['image']) && $product['image'] ? [$product['image']] : [],
            ],
            'availability' => 'IN_STOCK', // used by sellers with custom policies; keep inventory at offer level
            'condition' => 'NEW',
        ];

        // Upsert inventory item
        $this->upsertInventoryItem($itemPayload, $sku);

        // Create an offer payload
        $offerPayload = [
            'sku' => $sku,
            'marketplaceId' => env('EBAY_MARKETPLACE_ID', 'EBAY_US'),
            'format' => 'FIXED_PRICE',
            'availableQuantity' => max(1, intval($product['stock'] ?? 1)),
            'listingDescription' => $product['description'] ?? $product['name'],
            'marketplaceId' => env('EBAY_MARKETPLACE_ID', 'EBAY_US'),
            'categoryId' => $product['category_id'] ?? null,
            'pricingSummary' => [
                'price' => [
                    'value' => number_format((float)($product['price'] ?? 0), 2, '.', ''),
                    'currency' => $product['currency'] ?? ($product['devise'] ?? env('FEED_DEFAULT_CURRENCY', 'MAD')),
                ],
            ],
            'merchantLocationKey' => null, // optional
            'listingPolicies' => null, // optionally set return/fulfillment/payment policy ids
            'title' => $product['name'],
            'condition' => 'NEW',
        ];

        // eBay requires inventoryLocation and listing policies in production; in sandbox there are defaults.
        // Create offer
        $offerResp = $this->createOffer($offerPayload);
        // The response includes an offerId. Publish it:
        if (!empty($offerResp['offerId'])) {
            $this->publishOffer($offerResp['offerId']);
        }

        return [
            'sku' => $sku,
            'offer' => $offerResp,
        ];
    }

    /**
     * Delete listing (delete inventory item and optionally related offers)
     */
    public function deleteProduct(array $product): bool
    {
        $sku = 'hanaball-' . $product['id'];
        try {
            return $this->deleteInventoryItem($sku);
        } catch (\Throwable $e) {
            Log::error('eBay delete error: ' . $e->getMessage());
            return false;
        }
    }
}
