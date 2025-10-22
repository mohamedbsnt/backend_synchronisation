<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AmazonSPApiClient
{
    protected string $lwaClientId;
    protected string $lwaClientSecret;
    protected string $refreshToken;
    protected string $awsAccessKey;
    protected string $awsSecretKey;
    protected string $region;
    protected string $endpoint; // e.g. sellingpartnerapi-na.amazon.com
    protected array $marketplaceIds;

    public function __construct()
    {
        $this->lwaClientId = env('AMAZON_LWA_CLIENT_ID');
        $this->lwaClientSecret = env('AMAZON_LWA_CLIENT_SECRET');
        $this->refreshToken = env('AMAZON_REFRESH_TOKEN');
        $this->awsAccessKey = env('AWS_ACCESS_KEY_ID');
        $this->awsSecretKey = env('AWS_SECRET_ACCESS_KEY');
        $this->region = env('AMAZON_REGION', env('AWS_DEFAULT_REGION','us-east-1'));
        $this->endpoint = env('AMAZON_ENDPOINT', 'sellingpartnerapi-na.amazon.com');
        $this->marketplaceIds = explode(',', env('AMAZON_MARKETPLACE_IDS', ''));
    }

    /**
     * Get LWA access token (OAuth) using refresh token
     */
    public function getLwaAccessToken(): string
    {
        $url = 'https://api.amazon.com/auth/o2/token';
        $response = Http::asForm()->post($url, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->lwaClientId,
            'client_secret' => $this->lwaClientSecret,
        ]);

        if (!$response->successful()) {
            Log::error('Amazon LWA token fetch failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new Exception('LWA token fetch failed: ' . $response->body());
        }

        $json = $response->json();
        return $json['access_token'] ?? throw new Exception('LWA access_token missing');
    }

    /**
     * Create a feed document (get pre-signed URL)
     */
    public function createFeedDocument(string $contentType = 'text/xml; charset=UTF-8'): array
    {
        $path = "/feeds/2021-06-30/documents";
        $body = ['contentType' => $contentType];

        $resp = $this->signedRequest('POST', $path, json_encode($body), [
            'host' => $this->endpoint,
            'content-type' => 'application/json'
        ]);

        if ($resp->getStatusCode() >= 300) {
            Log::error('createFeedDocument failed', ['status' => $resp->getStatusCode(), 'body' => $resp->getBody()->getContents()]);
            throw new Exception('createFeedDocument failed');
        }

        $json = json_decode($resp->getBody()->getContents(), true);
        return $json['payload'] ?? [];
    }

    /**
     * Upload feed content bytes to pre-signed url (returned by createFeedDocument)
     */
    public function uploadFeedContent(string $uploadUrl, string $content, string $contentType = 'text/xml; charset=UTF-8'): bool
    {
        // PUT to the pre-signed url: no signing required, just plain PUT
        $resp = Http::withHeaders(['Content-Type' => $contentType])->put($uploadUrl, $content);

        if (!$resp->successful()) {
            Log::error('uploadFeedContent failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            return false;
        }

        return true;
    }

    /**
     * Create the actual feed referencing the uploaded document
     * $feedType examples: POST_PRODUCT_DATA, POST_INVENTORY_AVAILABILITY_DATA
     */
    public function createFeed(string $feedType, array $marketplaceIds, string $feedDocumentId, array $additional = []): array
    {
        $path = "/feeds/2021-06-30/feeds";
        $body = [
            'feedType' => $feedType,
            'marketplaceIds' => $marketplaceIds,
            'inputFeedDocumentId' => $feedDocumentId
        ];

        if (!empty($additional)) $body = array_merge($body, $additional);

        $resp = $this->signedRequest('POST', $path, json_encode($body), ['content-type' => 'application/json']);

        if ($resp->getStatusCode() >= 300) {
            Log::error('createFeed failed', ['status' => $resp->getStatusCode(), 'body' => $resp->getBody()->getContents()]);
            throw new Exception('createFeed failed');
        }

        return json_decode($resp->getBody()->getContents(), true)['payload'] ?? [];
    }

    /**
     * Get feed status
     */
    public function getFeed(string $feedId): array
    {
        $path = "/feeds/2021-06-30/feeds/{$feedId}";
        $resp = $this->signedRequest('GET', $path, '', []);
        $json = json_decode($resp->getBody()->getContents(), true);
        return $json['payload'] ?? [];
    }

    /**
     * Low-level signed request (AWS SigV4 + LWA Authorization header)
     * Uses guzzle (PSR-7)
     */
    protected function signedRequest(string $method, string $path, string $body = '', array $extraHeaders = [])
    {
        // 1) get LWA token
        $accessToken = $this->getLwaAccessToken();

        // 2) Prepare host and url
        $host = $this->endpoint;
        $service = 'execute-api';
        $region = $this->region;
        $uri = "https://{$host}{$path}";

        // 3) Prepare headers used for signing
        $headers = array_merge([
            'host' => $host,
            'x-amz-access-token' => $accessToken,
            'x-amz-date' => $this->amzDatetime(),
            'content-type' => $extraHeaders['content-type'] ?? 'application/json'
        ], $extraHeaders);

        // 4) Build Canonical Request
        $canonicalHeaders = $this->buildCanonicalHeaders($headers);
        $signedHeaders = $this->buildSignedHeaders($headers);
        $hashedPayload = hash('sha256', $body ?: '');

        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $path,
            '', // query string (none here)
            $canonicalHeaders . "\n",
            $signedHeaders,
            $hashedPayload
        ]);

        // 5) String to sign
        $timestamp = $headers['x-amz-date'];
        $date = substr($timestamp, 0, 8);
        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest)
        ]);

        // 6) Calculate signature
        $signingKey = $this->getSignatureKey($this->awsSecretKey, $date, $region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        // 7) Authorization header
        $authHeader = "AWS4-HMAC-SHA256 Credential={$this->awsAccessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        // 8) Final headers for the HTTP request
        $finalHeaders = [
            'Authorization' => $authHeader,
            'x-amz-access-token' => $accessToken,
            'x-amz-date' => $timestamp,
            'Content-Type' => $headers['content-type']
        ];

        // 9) Send request with guzzle via Http facade (which uses guzzle)
        $client = Http::withHeaders($finalHeaders);

        if (strtoupper($method) === 'GET') {
            return $client->get($uri);
        } elseif (strtoupper($method) === 'POST') {
            return $client->post($uri, $body ? json_decode($body, true) : []);
        } elseif (strtoupper($method) === 'PUT') {
            return $client->put($uri, $body);
        } else {
            // other verbs if needed
            return $client->send($method, $uri, ['body' => $body]);
        }
    }

    protected function amzDatetime(): string
    {
        return gmdate('Ymd\THis\Z');
    }

    protected function buildCanonicalHeaders(array $headers): string
    {
        $lower = [];
        foreach ($headers as $k => $v) {
            $lowerKey = strtolower($k);
            $lower[$lowerKey] = trim($v);
        }
        ksort($lower);
        $lines = [];
        foreach ($lower as $k => $v) {
            $lines[] = $k . ':' . $v;
        }
        return implode("\n", $lines);
    }

    protected function buildSignedHeaders(array $headers): string
    {
        $lowerKeys = array_map('strtolower', array_keys($headers));
        sort($lowerKeys);
        return implode(';', $lowerKeys);
    }

    protected function getSignatureKey($key, $dateStamp, $regionName, $serviceName)
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return $kSigning;
    }
}
