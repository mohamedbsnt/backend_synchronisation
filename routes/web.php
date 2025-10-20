<?php
use Illuminate\Support\Facades\Storage;
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

Route::get('/test-google-api', function() {
    $client = new Google_Client();
    $client->setAuthConfig(storage_path('app/google-service-account.json'));
    $client->addScope(Google_Service_ShoppingContent::CONTENT);

    return response()->json([
        'authenticated' => !$client->isAccessTokenExpired(),
        'credentials_loaded' => true
    ]);
});



Route::get('/feeds/google-merchant.csv', function () {
    abort_unless(Storage::exists('feeds/google-merchant.csv'), 404);
    return response(Storage::get('feeds/google-merchant.csv'), 200)
        ->header('Content-Type', 'text/csv; charset=UTF-8')
        ->header('Content-Disposition', 'inline; filename="google-merchant.csv"')
        ->header('Cache-Control', 'public, max-age=600');
});
