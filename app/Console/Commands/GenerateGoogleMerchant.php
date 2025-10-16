<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateGoogleMerchant extends Command
{
    protected $signature = 'feed:generate-google {--source=database}';
    protected $description = 'Génère les fichiers CSV et XML pour Google Merchant (statiques dans public/feed/)';

    public function handle()
    {
        $this->info('Génération du feed Google Merchant démarrée...');
        $outputDir = public_path(env('FEED_OUTPUT_DIR', 'feed'));
        $csvFilename = env('FEED_CSV_FILENAME', 'google-products.csv');
        $xmlFilename = env('FEED_XML_FILENAME', 'google-products.xml');

        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $source = $this->option('source') ?: 'database';

        // Either fetch from DB or external API
        if ($source === 'api') {
            $apiUrl = env('FEED_SOURCE_API');
            if (empty($apiUrl)) {
                $this->error('FEED_SOURCE_API not configured in .env');
                return 1;
            }
            try {
                $resp = Http::timeout(20)->get($apiUrl);
                if (!$resp->successful()) {
                    $this->error('API fetch failed: ' . $resp->status());
                    return 1;
                }
                $data = $resp->json();
                $products = collect($data['products'] ?? []);
            } catch (\Exception $e) {
                $this->error('API fetch exception: ' . $e->getMessage());
                Log::error('Feed API fetch exception: ' . $e->getMessage());
                return 1;
            }
        } else {
            $products = Product::with('categories')->active()->get()->map(function($p){
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                    'image' => $p->full_image_url,
                    'price' => number_format($p->final_price, 2, '.', ''),
                    'currency' => $p->currency,
                    'url' => $p->devaito_link,
                    'discount_amount' => $p->discount_amount,
                    'brand_name' => $p->brand_name,
                    'categories' => $p->categories->map(function($c){ return ['id'=>$c->id,'name'=>$c->name]; })->toArray(),
                    'availability' => $p->availability,
                    'google_product_category' => $p->google_product_category
                ];
            });
        }

        // Build CSV
        $csvPath = $outputDir . DIRECTORY_SEPARATOR . $csvFilename;
        $fh = fopen($csvPath, 'w');
        if (!$fh) {
            $this->error('Cannot create CSV file at ' . $csvPath);
            return 1;
        }
        // BOM
        fwrite($fh, chr(0xEF).chr(0xBB).chr(0xBF));
        $headers = ['id','title','description','link','image_link','price','sale_price','availability','condition','brand','google_product_category','product_type','mpn','gtin'];
        fputcsv($fh, $headers);
        $count = 0;
        foreach ($products as $p) {
            // p may be array (if from API) or model mapping above
            $id = $p['id'] ?? '';
            $title = $p['name'] ?? '';
            $description = $p['description'] ?? $title;
            $link = $p['url'] ?? '';
            $image_link = $p['image'] ?? '';
            $priceVal = $p['price'] ?? '';
            $currency = $p['currency'] ?? env('FEED_DEFAULT_CURRENCY','MAD');
            $price = ($priceVal !== '') ? ($priceVal . ' ' . $currency) : '';
            $sale_price = '';
            if (!empty($p['discount_amount'])) {
                $sale_price = (number_format(max(0, (float)$priceVal - (float)$p['discount_amount']), 2, '.', '') . ' ' . $currency);
            }
            $availability = $p['availability'] ?? env('FEED_DEFAULT_AVAILABILITY', 'in stock');
            $brand = $p['brand_name'] ?? env('FEED_DEFAULT_BRAND', 'HANABALL');
            $google_product_category = $p['google_product_category'] ?? '';
            $product_type = (!empty($p['categories']) && is_array($p['categories']) && isset($p['categories'][0]['name'])) ? $p['categories'][0]['name'] : '';
            $row = [$id,$title,$description,$link,$image_link,$price,$sale_price,$availability,'new',$brand,$google_product_category,$product_type,'',''];
            fputcsv($fh,$row);
            $count++;
        }
        fclose($fh);
        $this->info("CSV generated at {$csvPath} ({$count} items)");

        // Build XML
        $xmlPath = $outputDir . DIRECTORY_SEPARATOR . $xmlFilename;
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL;
        $xml .= '<channel>' . PHP_EOL;
        $xml .= '<title>Products</title>' . PHP_EOL;
        $xml .= '<link>' . rtrim(env('FEED_BASE_URL',''), '/') . '</link>' . PHP_EOL;
        $xml .= '<description>Products feed</description>' . PHP_EOL;

        foreach ($products as $p) {
            $priceVal = $p['price'] ?? '';
            $currency = $p['currency'] ?? env('FEED_DEFAULT_CURRENCY','MAD');
            $finalPrice = ($priceVal !== '') ? number_format((float)$priceVal, 2, '.', '') . ' ' . $currency : '';
            $xml .= '<item>' . PHP_EOL;
            $xml .= '<g:id>' . htmlspecialchars($p['id'] ?? '') . '</g:id>' . PHP_EOL;
            $xml .= '<g:title><![CDATA[' . ($p['name'] ?? '') . ']]></g:title>' . PHP_EOL;
            $xml .= '<g:description><![CDATA[' . ($p['description'] ?? $p['name'] ?? '') . ']]></g:description>' . PHP_EOL;
            $xml .= '<g:link>' . htmlspecialchars($p['url'] ?? '') . '</g:link>' . PHP_EOL;
            $xml .= '<g:image_link>' . htmlspecialchars($p['image'] ?? '') . '</g:image_link>' . PHP_EOL;
            $xml .= '<g:price>' . $finalPrice . '</g:price>' . PHP_EOL;
            $xml .= '<g:availability>' . htmlspecialchars($p['availability'] ?? env('FEED_DEFAULT_AVAILABILITY','in stock')) . '</g:availability>' . PHP_EOL;
            $xml .= '<g:condition>new</g:condition>' . PHP_EOL;
            $xml .= '<g:brand>' . htmlspecialchars($p['brand_name'] ?? env('FEED_DEFAULT_BRAND','HANABALL')) . '</g:brand>' . PHP_EOL;
            $xml .= '<g:google_product_category>' . htmlspecialchars($p['google_product_category'] ?? '') . '</g:google_product_category>' . PHP_EOL;
            if (!empty($p['categories']) && isset($p['categories'][0]['name'])) {
                $xml .= '<g:product_type>' . htmlspecialchars($p['categories'][0]['name']) . '</g:product_type>' . PHP_EOL;
            }
            $xml .= '</item>' . PHP_EOL;
        }

        $xml .= '</channel>' . PHP_EOL;
        $xml .= '</rss>' . PHP_EOL;

        File::put($xmlPath, $xml);
        $this->info("XML generated at {$xmlPath}");

        // Log success
        Log::info("Google feed generated: CSV={$csvPath}, XML={$xmlPath}, items={$count}");

        return 0;
    }
}
