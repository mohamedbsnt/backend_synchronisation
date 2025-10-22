<?php

namespace App\Jobs;

use App\Services\InstagramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToInstagram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $product;
    public $tries = 3;
    public $timeout = 120;

    public function __construct(array $product)
    {
        $this->product = $product;
    }

    public function handle(InstagramService $instagram)
    {
        try {
            $result = $instagram->upsertProduct($this->product);
            Log::info('SyncProductToInstagram result', ['retailer_id' => $this->product['id'] ?? null, 'result' => $result]);
        } catch (\Throwable $e) {
            Log::error('SyncProductToInstagram error: ' . $e->getMessage(), ['product' => $this->product]);
            throw $e;
        }
    }
}
