<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GenerateGoogleFeed extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'google:generate-feed';

    /**
     * The console command description.
     */
    protected $description = 'Génère le flux Google Merchant Center CSV';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Génération du flux Google Merchant...');

        try {
            // Récupère les produits depuis l'API Devaito
            $response = Http::timeout(30)->get(env('DEVAITO_API_URL', 'https://hanaball.devaito.com/api/fetch-all-products'));
            
            if (!$response->successful()) {
                $this->error('❌ Impossible de récupérer les produits depuis l\'API Devaito');
                return Command::FAILURE;
            }

            $products = $response->json()['products'] ?? [];
            
            if (empty($products)) {
                $this->warn('⚠️ Aucun produit trouvé');
                return Command::SUCCESS;
            }

            // Génère le CSV
            $csv = $this->generateCSV($products);
            
            // Sauvegarde dans storage
            Storage::put('feeds/google-merchant.csv', $csv);
            
            $this->info("✅ Flux généré avec succès ! Total produits: " . count($products));
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Erreur: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Génère le contenu CSV
     */
    protected function generateCSV(array $products): string
    {
        $output = fopen('php://temp', 'r+');

        // En-têtes CSV Google Merchant
        fputcsv($output, [
            'id',
            'title',
            'description',
            'link',
            'image_link',
            'availability',
            'price',
            'condition',
            'brand',
            'sale_price',
            'google_product_category'
        ]);

        foreach ($products as $product) {
            // Gère les images avec chemin relatif
            $image = $product['image'];
            if (!str_starts_with($image, 'http')) {
                $image = 'https://hanaball.devaito.com' . $image;
            }

            // Calcule le prix promotionnel
            $salePrice = '';
            if (!empty($product['discount_amount'])) {
                $discountedPrice = $product['price'] - $product['discount_amount'];
                $salePrice = number_format($discountedPrice, 2) . ' ' . ($product['devise'] ?? 'MAD');
            }

            fputcsv($output, [
                $product['id'],
                $product['name'],
                $product['name'], // ou description si disponible
                $product['url'],
                $image,
                'in stock',
                number_format($product['price'], 2) . ' ' . ($product['devise'] ?? 'MAD'),
                'new',
                $product['brand_name'] ?? 'Hanaball',
                $salePrice,
                '' // Catégorie Google (à mapper si besoin)
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
