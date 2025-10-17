<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return response()->json([
        'message' => '🛒 Hanaball Google Merchant Center API',
        'api_endpoint' => 'https://hanaball.devaito.com/api/google-all-products',
        'status' => 'active',
        'last_updated' => now()->format('Y-m-d H:i:s')
    ]);
});

Route::get('/api/google-all-products.csv', function () {
    $url = config('app.url') . '/api/google-all-products';
    $response = Http::timeout(30)->get($url);

    if ($response->status() !== 200) {
        abort($response->status(), 'Flux CSV inaccessible');
    }

    return response($response->body(), 200)
        ->header('Content-Type','text/csv; charset=UTF-8')
        ->header('Content-Disposition','inline; filename="google-all-products.csv"');
});
