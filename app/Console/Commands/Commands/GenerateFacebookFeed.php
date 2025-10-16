<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GenerateFacebookFeed extends Command
{
    protected $signature = 'feed:generate-facebook {--source=api}';
    protected $description = 'Génère / actualise le CSV pour Facebook Catalog depuis l\'API source ou DB';

    public function handle()
    {
        $this->info('Génération du feed Facebook démarrée...');

        $source = $this->option('source');
        $apiUrl = env('FEED_SOURCE_API');

        if ($source === 'api' && empty($apiUrl)) {
            $this->error('FEED_SOURCE_API non configuré dans .env');
            return 1;
        }

        // Récupère produits depuis API
        $products = [];
        if ($source === 'api') {
            try {
                $resp = Http::timeout(30)->get($apiUrl);
                if (!$resp->successful()) {
                    $this->error('Erreur API: ' . $resp->status());
                    Log::error('Feed API fetch failed: ' . $resp->status());
                    return 1;
                }
                $json = $resp->json();
                $products = $json['products'] ?? [];
            } catch (\Exception $e) {
                $this->error('Exception API: ' . $e->getMessage());
                Log::error('Feed API exception: ' . $e->getMessage());
                return 1;
            }
        } else {
            // TODO: Implémenter si tu veux depuis DB
            $this->error('Source non supportée pour l\'instant: ' . $source);
            return 1;
        }

        if (empty($products)) {
            $this->warn('Aucun produit trouvé.');
            return 0;
        }

        // Dossier et fichier
        $dir = public_path(env('FEED_OUTPUT_DIR', 'feed'));
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $path = $dir . '/' . env('FEED_CSV_FILENAME', 'facebook-products.csv');

        // En-tête (conforme Meta)
        $headers = [
            'id','title','description','link','image_link','additional_image_link',
            'price','availability','condition','brand','gtin','mpn','product_type'
        ];

        $fp = fopen($path, 'w');
        // BOM pour Excel
        fwrite($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fp, $headers);

        foreach ($products as $p) {
            $price = (isset($p['price']) ? number_format((float)$p['price'], 2, '.', '') : '') . ' ' . ($p['devise'] ?? 'MAD');
            $sale_price = '';
            if (!empty($p['discount_amount'])) {
                $sale_price = number_format(max(0, (float)$p['price'] - (float)$p['discount_amount']), 2, '.', '') . ' ' . ($p['devise'] ?? 'MAD');
            }
            $row = [
                $p['id'] ?? '', // id unique
                $p['name'] ?? '',
                $p['description'] ?? ($p['name'] ?? ''),
                $p['url'] ?? '',
                $p['image'] ?? '',
                '' , // additional_image_link (si tu as autres images: pipe-separated)
                $price,
                ($p['availability'] ?? 'in stock'),
                'new',
                $p['brand_name'] ?? (env('FEED_DEFAULT_BRAND','')),
                $p['gtin'] ?? '',
                $p['mpn'] ?? '',
                isset($p['categories'][0]['name']) ? $p['categories'][0]['name'] : ''
            ];
            fputcsv($fp, $row);
        }
        fclose($fp);

        $publicUrl = rtrim(env('FEED_BASE_URL', env('APP_URL')), '/') . '/' . env('FEED_OUTPUT_DIR', 'feed') . '/' . env('FEED_CSV_FILENAME', 'facebook-products.csv');

        $this->info("Feed généré : {$path}");
        $this->info("URL publique : {$publicUrl}");

        Log::info("Facebook feed generated at {$path}");

        return 0;
    }
}
