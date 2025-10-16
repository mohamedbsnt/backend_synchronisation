<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class ProductObserver
{
    /**
     * Appelé quand un produit est créé
     */
    public function created(Product $product): void
    {
        Log::info('Nouveau produit créé', ['product_id' => $product->id, 'name' => $product->name]);
        $this->syncWithGoogleMerchant();
    }

    /**
     * Appelé quand un produit est mis à jour
     */
    public function updated(Product $product): void
    {
        Log::info('Produit mis à jour', ['product_id' => $product->id, 'name' => $product->name]);
        $this->syncWithGoogleMerchant();
    }

    /**
     * Appelé quand un produit est supprimé
     */
    public function deleted(Product $product): void
    {
        Log::info('Produit supprimé', ['product_id' => $product->id, 'name' => $product->name]);
        $this->syncWithGoogleMerchant();
    }

    /**
     * Déclenche la synchronisation avec Google Merchant
     */
    private function syncWithGoogleMerchant(): void
    {
        // Exécuter la commande en arrière-plan
        Artisan::queue('google:sync-products');
    }
}
