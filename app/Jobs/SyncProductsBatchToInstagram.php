<?php

namespace App\Jobs;

use App\Services\InstagramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductsBatchToInstagram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $products;
    public int $chunkSize = 50;
    public $tries = 3;
    public $timeout = 600;

    public function __construct(array $products, int $chunkSize = 50)
    {
        $this->products = $products;
        $this->chunkSize = $chunkSize;
    }

    public function handle(InstagramService $instagram)
    {
        try {
            foreach (array_chunk($this->products, $this->chunkSize) as $chunk) {
                $instagram->upsertBatch($chunk);
                Log::info('SyncProductsBatchToInstagram chunk processed', ['count' => count($chunk)]);
                sleep(1);
            }
        } catch (\Throwable $e) {
            Log::error('SyncProductsBatchToInstagram failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
