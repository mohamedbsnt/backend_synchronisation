<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GenerateFacebookFeed extends Command
{
    protected $signature = 'feed:generate-facebook {--source=api}';
    protected $description = 'Generate Facebook CSV feed from API or DB and save to public/feed';

    public function handle()
    {
        $this->info('Generating Facebook feed...');
        $source = $this->option('source');
        $apiUrl = env('FEED_SOURCE_API');

        if ($source === 'api' && empty($apiUrl)) {
            $this->error('FEED_SOURCE_API not configured in .env');
            return 1;
        }

        try {
            $resp = Http::timeout(30)->get($apiUrl);
            if (!$resp->successful()) {
                $this->error('API fetch failed: ' . $resp->status());
                return 1;
            }
            $data = $resp->json();
            $products = $data['products'] ?? [];
        } catch (\Throwable $e) {
            $this->error('API exception: ' . $e->getMessage());
            Log::error('GenerateFacebookFeed API exception: '.$e->getMessage());
            return 1;
        }

        if (empty($products)) {
            $this->warn('No products found.');
            return 0;
        }

        $dir = public_path(env('FEED_OUTPUT_DIR','feed'));
        if (!File::isDirectory($dir)) File::makeDirectory($dir, 0755, true);
        $fileName = env('FEED_CSV_FILENAME','facebook-products.csv');
        $path = $dir . DIRECTORY_SEPARATOR . $fileName;

        $headers = ['id','title','description','link','image_link','additional_image_link','price','availability','condition','brand','gtin','mpn','product_type'];

        $fp = fopen($path, 'w');
        fwrite($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fp, $headers);

        foreach ($products as $p) {
            $price = (isset($p['price']) ? number_format((float)$p['price'], 2, '.', '') : '') . ' ' . ($p['devise'] ?? env('FEED_DEFAULT_CURRENCY','MAD'));
            $row = [
                $p['id'] ?? '',
                $p['name'] ?? '',
                $p['description'] ?? ($p['name'] ?? ''),
                $p['url'] ?? '',
                $p['image'] ?? '',
                '',
                $price,
                $p['availability'] ?? env('FEED_DEFAULT_AVAILABILITY','in stock'),
                'new',
                $p['brand_name'] ?? '',
                $p['gtin'] ?? '',
                $p['mpn'] ?? '',
                $p['categories'][0]['name'] ?? ''
            ];
            fputcsv($fp, $row);
        }

        fclose($fp);

        $publicUrl = rtrim(env('FEED_BASE_URL', env('APP_URL')), '/') . '/' . env('FEED_OUTPUT_DIR','feed') . '/' . $fileName;
        $this->info("Feed written to: {$path}");
        $this->info("Public URL: {$publicUrl}");
        Log::info("Facebook feed generated: {$path}");

        return 0;
    }
}
