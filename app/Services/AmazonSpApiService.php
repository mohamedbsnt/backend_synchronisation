<?php

namespace App\Services;

use SellingPartnerApi\SellingPartnerApi;
use SellingPartnerApi\Enums\Endpoint;
use SellingPartnerApi\Api\FeedsV20210630Api as FeedsApi;
use SellingPartnerApi\Configuration;
use SellingPartnerApi\Model\FeedsV20210630 as FeedsModels;
use SellingPartnerApi\Document;
use App\Models\AmazonFeed;

class AmazonSpApiService
{
    protected Configuration $config;
    protected SellingPartnerApi $connector;
    protected array $marketplaceIds;

    public function __construct()
    {
        $endpoint = Endpoint::NA;
        $ep = strtoupper(env('SPAPI_ENDPOINT', 'NA'));
        if ($ep === 'EU') $endpoint = Endpoint::EU;
        if ($ep === 'FE') $endpoint = Endpoint::FE;

        $this->connector = SellingPartnerApi::seller(
            clientId: env('SPAPI_CLIENT_ID'),
            clientSecret: env('SPAPI_CLIENT_SECRET'),
            refreshToken: env('SPAPI_REFRESH_TOKEN'),
            endpoint: $endpoint,
            // roleArn optional: env('SPAPI_ROLE_ARN')
        );

        $this->marketplaceIds = array_filter(array_map('trim', explode(',', env('SPAPI_MARKETPLACE_IDS', ''))));
    }

    /**
     * Submit a JSON_LISTINGS_FEED to Amazon for a set of products.
     *
     * @param array $products  Array of product arrays (id,name,price,image,url,brand,...)
     * @return array ['feedId'=>..., 'feedDocumentId'=>..., 'response'=>...]
     */
    public function submitListingsFeed(array $products): array
    {
        // Build minimal JSON_LISTINGS_FEED payload.
        // IMPORTANT: In production you must build payloads according to productType schemas
        $items = [];
        foreach ($products as $p) {
            $sku = $p['amazon_sku'] ?? ('HANABALL-' . ($p['id'] ?? uniqid()));
            $title = $p['name'] ?? $sku;
            $description = $p['description'] ?? $title;
            $brand = $p['brand_name'] ?? env('FEED_DEFAULT_BRAND', 'Hanaball');

            $items[] = [
                'sku' => $sku,
                'productType' => $p['product_type'] ?? 'miscellaneous',
                'attributes' => [
                    'title' => [
                        ['value' => $title, 'language' => 'en_US']
                    ],
                    'brand' => $brand,
                    'description' => [
                        ['value' => $description, 'language' => 'en_US']
                    ],
                    'main_image' => $p['image'] ?? $p['image_link'] ?? null,
                    'price' => [
                        'amount' => number_format((float)($p['price'] ?? 0), 2, '.', ''),
                        'currency' => $p['currency'] ?? ($p['devise'] ?? env('FEED_DEFAULT_CURRENCY','MAD'))
                    ]
                ],
            ];
        }

        $feedPayload = json_encode(['items' => $items], JSON_UNESCAPED_SLASHES);

        // Use Feeds API
        $feedsApi = new FeedsApi($this->connector->getConfig());

        // 1) createFeedDocument (content_type)
        $createFeedDocumentSpec = new FeedsModels\CreateFeedDocumentSpecification([
            'content_type' => 'application/json; charset=UTF-8'
        ]);

        $docInfo = $feedsApi->createFeedDocument($createFeedDocumentSpec);
        $feedDocumentId = $docInfo->getFeedDocumentId();

        // 2) upload feed contents to the document (Document helper)
        $document = new Document($docInfo, 'application/json; charset=UTF-8');
        $document->upload($feedPayload);

        // 3) createFeed
        $createFeedSpec = new FeedsModels\CreateFeedSpecification();
        $createFeedSpec->setMarketplaceIds($this->marketplaceIds ?: null);
        $createFeedSpec->setInputFeedDocumentId($feedDocumentId);
        $createFeedSpec->setFeedType('JSON_LISTINGS_FEED'); // feed type

        $response = $feedsApi->createFeed($createFeedSpec);
        $feedId = $response->getFeedId() ?? null;

        // Store to amazon_feeds table (simple logging)
        $record = \App\Models\AmazonFeed::create([
            'feed_id' => $feedId,
            'feed_type' => 'JSON_LISTINGS_FEED',
            'status' => 'SUBMITTED',
            'request_payload' => $feedPayload,
            'response' => json_encode($response->json() ?? $response),
            'submitted_at' => now(),
        ]);

        return [
            'feedId' => $feedId,
            'feedDocumentId' => $feedDocumentId,
            'response' => $response->json() ?? $response
        ];
    }

    /**
     * Check feed status
     */
    public function getFeedStatus(string $feedId)
    {
        $feedsApi = new FeedsApi($this->connector->getConfig());
        $resp = $feedsApi->getFeed($feedId);
        return $resp->json();
    }
}
