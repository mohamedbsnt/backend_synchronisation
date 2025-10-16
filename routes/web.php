<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => '🛒 Hanaball Google Merchant Center API',
        'api_endpoint' => 'https://hanaball.devaito.com/api/google-all-products',
        'status' => 'active',
        'last_updated' => now()->format('Y-m-d H:i:s')
    ]);
});

// Routes pour servir les fichiers statiques (optionnel)
Route::get('/feed/google-products.csv', function(){
    $path = public_path('feed/google-products.csv');
    if (!file_exists($path)) {
        abort(404, 'Feed not generated yet. Run: php artisan google:sync-products --save-file');
    }
    return response()->file($path, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="hanaball-google-products.csv"'
    ]);
});
