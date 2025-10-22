<?php

namespace App\Jobs;

use App\Services\FacebookCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductsBatchToFacebook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $products;
    public int $chunkSize;
    public $tries = 3;
    public $timeout = 600;

    public function __construct(array $products, int $chunkSize = 50)
    {
        $this->products = $products;
        $this->chunkSize = $chunkSize;
    }

    public function handle(FacebookCatalogService $fbService): void
    {
        try {
            foreach (array_chunk($this->products, $this->chunkSize) as $chunk) {
                $resp = $fbService->upsertBatch($chunk);
                Log::info('SyncProductsBatchToFacebook processed chunk', ['count' => count($chunk)]);
                sleep(1);
            }
        } catch (\Throwable $e) {
            Log::error('SyncProductsBatchToFacebook failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
