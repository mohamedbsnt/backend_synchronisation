<?php

namespace App\Services;

use Google_Client;
use Google_Service_ShoppingContent;
use Google_Service_ShoppingContent_Product;
use Exception;
use Illuminate\Support\Facades\Log;

class GoogleMerchantService
{
    protected Google_Service_ShoppingContent $service;
    protected string $merchantId;

    public function __construct()
    {
        try {
            $client = new Google_Client();
            
            // Charge le fichier JSON de service account
            $client->setAuthConfig(storage_path('app/google-service-account.json'));
            
            // Définit le scope Content API
            $client->addScope(Google_Service_ShoppingContent::CONTENT);
            
            // Initialise le service Shopping Content
            $this->service = new Google_Service_ShoppingContent($client);
            
            // Récupère l'ID Merchant depuis .env
            $this->merchantId = env('GOOGLE_MERCHANT_ID');
            
            if (empty($this->merchantId)) {
                throw new Exception('GOOGLE_MERCHANT_ID non défini dans .env');
            }
            
        } catch (Exception $e) {
            Log::error('Erreur initialisation GoogleMerchantService: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mappe les données produit au format Google_Service_ShoppingContent_Product
     */
    protected function map(array $data): Google_Service_ShoppingContent_Product
    {
        $product = new Google_Service_ShoppingContent_Product();
        
        // Champs obligatoires
        $product->setOfferId((string)($data['id'] ?? ''));
        $product->setTitle($data['title'] ?? 'Sans titre');
        $product->setDescription($data['description'] ?? '');
        $product->setLink($data['link'] ?? '');
        $product->setImageLink($data['image_link'] ?? '');
        $product->setAvailability($data['availability'] ?? 'in stock');
        
        // Prix (obligatoire)
        $product->setPrice([
            'value'    => (string)($data['price_value'] ?? '0'),
            'currency' => $data['currency'] ?? 'MAD',
        ]);
        
        // Champs standard
        $product->setCondition($data['condition'] ?? 'new');
        $product->setBrand($data['brand'] ?? 'Hanaball');
        
        // Localisation (obligatoires)
        $product->setContentLanguage('fr');
        $product->setTargetCountry('MA');
        $product->setChannel('online');
        
        // Identifiants produit (optionnels mais recommandés)
        if (!empty($data['gtin'])) {
            $product->setGtin($data['gtin']);
        }
        
        if (!empty($data['mpn'])) {
            $product->setMpn($data['mpn']);
        }
        
        // Catégorie Google (optionnel mais important)
        if (!empty($data['google_product_category'])) {
            $product->setGoogleProductCategory($data['google_product_category']);
        }
        
        // Prix promotionnel (optionnel)
        if (!empty($data['sale_price_value'])) {
            $product->setSalePrice([
                'value'    => (string)$data['sale_price_value'],
                'currency' => $data['currency'] ?? 'MAD',
            ]);
        }

        return $product;
    }

    /**
     * Insère un nouveau produit dans Merchant Center
     */
    public function insertProduct(array $data)
    {
        try {
            $product = $this->map($data);
            $result = $this->service->products->insert($this->merchantId, $product);
            
            Log::info('Produit inséré avec succès', [
                'offer_id' => $data['id'],
                'title' => $data['title'] ?? ''
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            Log::error('Erreur insertion produit Google Merchant', [
                'offer_id' => $data['id'] ?? 'N/A',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Met à jour un produit existant dans Merchant Center
     */
    public function updateProduct(string $offerId, array $data)
    {
        try {
            $product = $this->map($data);
            $result = $this->service->products->update(
                $this->merchantId,
                'online:fr:MA:' . $offerId,  // productId complet
                $product
            );
            
            Log::info('Produit mis à jour avec succès', [
                'offer_id' => $offerId,
                'title' => $data['title'] ?? ''
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            Log::error('Erreur mise à jour produit Google Merchant', [
                'offer_id' => $offerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Supprime un produit de Merchant Center
     */
    public function deleteProduct(string $offerId)
    {
        try {
            $this->service->products->delete(
                $this->merchantId,
                'online:fr:MA:' . $offerId  // productId complet
            );
            
            Log::info('Produit supprimé avec succès', ['offer_id' => $offerId]);
            
        } catch (Exception $e) {
            Log::error('Erreur suppression produit Google Merchant', [
                'offer_id' => $offerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Récupère un produit depuis Merchant Center
     */
    public function getProduct(string $offerId)
    {
        try {
            return $this->service->products->get(
                $this->merchantId,
                'online:fr:MA:' . $offerId
            );
        } catch (Exception $e) {
            Log::error('Erreur récupération produit Google Merchant', [
                'offer_id' => $offerId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Liste tous les produits du compte Merchant
     */
    public function listProducts(int $maxResults = 250)
    {
        try {
            return $this->service->products->listProducts(
                $this->merchantId,
                ['maxResults' => $maxResults]
            );
        } catch (Exception $e) {
            Log::error('Erreur listage produits Google Merchant', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
