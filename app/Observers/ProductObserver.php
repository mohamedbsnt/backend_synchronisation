<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Lorsqu’un produit est créé
     */
    public function created(Product $product): void
    {
        $this->generateGoogleFeed('created', $product);
    }

    /**
     * Lorsqu’un produit est mis à jour
     */
    public function updated(Product $product): void
    {
        $this->generateGoogleFeed('updated', $product);
    }

    /**
     * Lorsqu’un produit est supprimé
     */
    public function deleted(Product $product): void
    {
        $this->generateGoogleFeed('deleted', $product);
    }

    /**
     * Fonction commune pour lancer la commande Artisan
     */
    protected function generateGoogleFeed(string $action, Product $product): void
    {
        try {
            Artisan::call('feed:generate-google');
            Log::info("Feed Google Merchant régénéré après {$action} du produit ID {$product->id}.");
        } catch (\Exception $e) {
            Log::error("Erreur lors de la régénération du feed Google Merchant : " . $e->getMessage());
        }
    }
}
