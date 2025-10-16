<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterFacebookFeed extends Command
{
    protected $signature = 'facebook:register-feed';
    protected $description = 'Créer/Mettre à jour product_feed dans Facebook Catalog pointant vers le CSV public';

    public function handle()
    {
        $token = env('FACEBOOK_ACCESS_TOKEN');
        $catalogId = env('FACEBOOK_CATALOG_ID');
        $apiVersion = env('FACEBOOK_API_VERSION','v16.0');
        $feedUrl = rtrim(env('FEED_BASE_URL', env('APP_URL')), '/') . '/' . env('FEED_OUTPUT_DIR', 'feed') . '/' . env('FEED_CSV_FILENAME','facebook-products.csv');

        if (!$token || !$catalogId) {
            $this->error('FACEBOOK_ACCESS_TOKEN et/ou FACEBOOK_CATALOG_ID manquants dans .env');
            return 1;
        }

        $endpoint = "https://graph.facebook.com/{$apiVersion}/{$catalogId}/product_feeds";

        // schedule as JSON per docs: interval DAILY, url=...
        $schedule = [
            'interval' => 'DAILY',
            'url' => $feedUrl,
            'hour' => 3  // heure serveur (optionnel)
        ];

        $params = [
            'name' => 'Feed from Devaito',
            'schedule' => json_encode($schedule),
            'access_token' => $token
        ];

        $resp = Http::asForm()->post($endpoint, $params);
        $body = $resp->json();

        if ($resp->successful()) {
            $this->info('Feed créé dans Facebook : ' . json_encode($body));
            Log::info('FB feed created: ' . json_encode($body));
            return 0;
        } else {
            $this->error('Erreur API FB: ' . $resp->status() . ' ' . json_encode($body));
            Log::error('FB feed create error: ' . json_encode($body));
            return 1;
        }
    }
}
