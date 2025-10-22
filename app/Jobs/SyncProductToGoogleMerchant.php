<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\GoogleMerchantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class SyncProductToGoogleMerchant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Product $product;
    protected string $action;

    /**
     * Nombre de tentatives en cas d'échec
     */
    public $tries = 3;

    /**
     * Timeout du job (secondes)
     */
    public $timeout = 120;

    public function __construct(Product $product, string $action)
    {
        $this->product = $product;
        $this->action = $action;
    }

    public function handle(GoogleMerchantService $service): void
    {
        try {
            // Prépare les données produit
            $data = [
                'id'                => $this->product->id,
                'title'             => $this->product->name,
                'description'       => $this->product->description ?? $this->product->name,
                'link'              => $this->product->url,
                'image_link'        => $this->product->image,
                'availability'      => $this->product->stock > 0 ? 'in stock' : 'out of stock',
                'price_value'       => (string)$this->product->price,
                'currency'          => $this->product->currency ?? 'MAD',
                'condition'         => 'new',
                'brand'             => $this->product->brand_name ?? 'Hanaball',
            ];

            // Ajoute le prix promotionnel si disponible
            if (!empty($this->product->discount_amount)) {
                $salePrice = $this->product->price - $this->product->discount_amount;
                $data['sale_price_value'] = (string)max(0, $salePrice);
            }

            // Exécute l'action appropriée
            match ($this->action) {
                'insert' => $service->insertProduct($data),
                'update' => $service->updateProduct((string)$data['id'], $data),
                'delete' => $service->deleteProduct((string)$data['id']),
                default  => throw new Exception("Action invalide: {$this->action}")
            };

            Log::info("Job SyncProductToGoogleMerchant réussi", [
                'action' => $this->action,
                'product_id' => $this->product->id
            ]);

        } catch (Exception $e) {
            Log::error("Job SyncProductToGoogleMerchant échoué", [
                'action' => $this->action,
                'product_id' => $this->product->id,
                'error' => $e->getMessage()
            ]);

            // Re-lance l'exception pour que le job soit marqué comme failed
            throw $e;
        }
    }

    /**
     * Gère l'échec définitif du job après toutes les tentatives
     */
    public function failed(Exception $exception): void
    {
        Log::critical("Job SyncProductToGoogleMerchant failed définitivement", [
            'action' => $this->action,
            'product_id' => $this->product->id,
            'error' => $exception->getMessage()
        ]);
    }
}
