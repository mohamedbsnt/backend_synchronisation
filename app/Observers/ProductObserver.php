<?php

namespace App\Observers;

use App\Models\Product;
use App\Jobs\SyncProductToGoogleMerchant;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Déclenché quand un produit est créé
     */
    public function created(Product $product): void
    {
        Log::info('ProductObserver: Nouveau produit créé', ['id' => $product->id]);
        
        SyncProductToGoogleMerchant::dispatch($product, 'insert');
    }

    /**
     * Déclenché quand un produit est modifié
     */
    public function updated(Product $product): void
    {
        Log::info('ProductObserver: Produit mis à jour', ['id' => $product->id]);
        
        SyncProductToGoogleMerchant::dispatch($product, 'update');
    }

    /**
     * Déclenché quand un produit est supprimé
     */
    public function deleted(Product $product): void
    {
        Log::info('ProductObserver: Produit supprimé', ['id' => $product->id]);
        
        SyncProductToGoogleMerchant::dispatch($product, 'delete');
    }
}
