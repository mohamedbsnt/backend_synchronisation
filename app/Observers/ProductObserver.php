<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\RegenerateFeeds; // Job pour générer les feeds sans bloquer

class ProductObserver
{
    /**
     * Appelé quand un produit est créé
     */
    public function created(Product $product): void
    {
        Log::info('🆕 Nouveau produit créé', [
            'product_id' => $product->id,
            'name' => $product->name
        ]);

        $this->syncFeeds();
    }

    /**
     * Appelé quand un produit est mis à jour
     */
    public function updated(Product $product): void
    {
        Log::info('✏️ Produit mis à jour', [
            'product_id' => $product->id,
            'name' => $product->name
        ]);

        $this->syncFeeds();
    }

    /**
     * Appelé quand un produit est supprimé
     */
    public function deleted(Product $product): void
    {
        Log::info('🗑️ Produit supprimé', [
            'product_id' => $product->id,
            'name' => $product->name
        ]);

        $this->syncFeeds();
    }

    /**
     * Déclenche la synchronisation avec les feeds Google et Facebook
     */
    private function syncFeeds(): void
    {
        try {
            // Option 1 : lancer la commande immédiatement
            Artisan::call('feed:generate-google', ['--source' => 'api']);
            Artisan::call('feed:generate-facebook', ['--source' => 'api']);

            Log::info('✅ Feeds Google & Facebook régénérés avec succès via Artisan.');

            // Option 2 (recommandée) : job asynchrone pour exécution en arrière-plan
            // RegenerateFeeds::dispatch();

        } catch (\Throwable $e) {
            Log::error('❌ Erreur lors de la régénération des feeds : ' . $e->getMessage());
        }
    }
}
