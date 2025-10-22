<?php

namespace App\Jobs;

use App\Services\FacebookCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToFacebook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $product;
    public $tries = 3;
    public $timeout = 120;

    public function __construct(array $product)
    {
        $this->product = $product;
    }

    public function handle(FacebookCatalogService $fbService): void
    {
        try {
            $resp = $fbService->upsertSingleProduct($this->product);
            Log::info('SyncProductToFacebook success', ['retailer_id' => $this->product['id'] ?? null, 'resp' => $resp]);
        } catch (\Throwable $e) {
            Log::error('SyncProductToFacebook error: ' . $e->getMessage(), ['product' => $this->product]);
            throw $e;
        }
    }
}
