<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Routes publiques utiles pour accéder aux feeds CSV (inline) pour :
| - Google (merchant feed)
| - Facebook (catalog feed)
| - Amazon (catalog / listings feed)
|
| Comportement :
| 1) Si le fichier public/feed/<filename> existe -> renvoie directement (performant).
| 2) Sinon -> proxy vers l'API interne /api/<platform>-all-products (config('app.url')).
|
| Les réponses sont renvoyées avec Content-Type text/csv; charset=UTF-8 et
| Content-Disposition inline (affichage direct dans le navigateur).
|
*/

Route::get('/', function () {
    $base = rtrim(config('app.url') ?? env('APP_URL', ''), '/');

    return response()->json([
        'message' => '🛒 Hanaball Feeds API',
        'feeds' => [
            'google_csv'   => "{$base}/api/google-all-products.csv",
            'facebook_csv' => "{$base}/api/facebook-all-products.csv",
            'amazon_csv'   => "{$base}/api/amazon-all-products.csv",
        ],
        'api_endpoints' => [
            'google_api'   => "{$base}/api/google-all-products",
            'facebook_api' => "{$base}/api/facebook-all-products",
            'amazon_api'   => "{$base}/api/amazon-all-products",
        ],
        'status' => 'active',
        'last_updated' => now()->toDateTimeString(),
    ]);
});

/**
 * Helper function to serve or proxy a feed.
 *
 * - $filePath: local public path to CSV (public/feed/<file>)
 * - $apiPath: API absolute URL to proxy to if file missing
 */
$serveCsv = function (string $filePath, string $apiPath) {
    // 1) Serve local file if exists
    if (File::exists($filePath)) {
        // read file content and return inline
        $content = File::get($filePath);
        return response($content, 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'inline; filename="'.basename($filePath).'"')
            ->header('Cache-Control', 'public, max-age=300');
    }

    // 2) Proxy to API endpoint
    try {
        $resp = Http::timeout(20)->get($apiPath);
    } catch (\Exception $e) {
        abort(502, 'Erreur lors de la récupération du flux source: ' . $e->getMessage());
    }

    if (!$resp->successful()) {
        abort($resp->status(), 'Flux source inaccessible (status: ' . $resp->status() . ')');
    }

    $body = $resp->body();

    // If the API returned JSON, try convert to CSV? — we will return raw body but set CSV content-type.
    // (Prefer: your API endpoints should already return CSV or the feed generator should write files.)
    return response($body, 200)
        ->header('Content-Type', 'text/csv; charset=UTF-8')
        ->header('Content-Disposition', 'inline; filename="'.basename($filePath).'"')
        ->header('Cache-Control', 'public, max-age=120');
};

/*
|--------------------------------------------------------------------------
| Google Merchant CSV (inline)
|--------------------------------------------------------------------------
| public path: public/feed/google-products.csv
| fallback API: {APP_URL}/api/google-all-products
*/
Route::get('/api/google-all-products.csv', function () use ($serveCsv) {
    $fileName = env('FEED_CSV_FILENAME_GOOGLE', 'google-products.csv'); // optional override
    $filePath = public_path('feed/' . $fileName);
    $apiUrl = rtrim(config('app.url') ?? env('APP_URL', ''), '/') . '/api/google-all-products';
    return $serveCsv($filePath, $apiUrl);
})->name('feeds.google.csv');

/*
|--------------------------------------------------------------------------
| Facebook Catalog CSV (inline)
|--------------------------------------------------------------------------
| public path: public/feed/facebook-products.csv
| fallback API: {APP_URL}/api/facebook-all-products
*/
Route::get('/api/facebook-all-products.csv', function () use ($serveCsv) {
    $fileName = env('FEED_CSV_FILENAME_FACEBOOK', 'facebook-products.csv');
    $filePath = public_path('feed/' . $fileName);
    $apiUrl = rtrim(config('app.url') ?? env('APP_URL', ''), '/') . '/api/facebook-all-products';
    return $serveCsv($filePath, $apiUrl);
})->name('feeds.facebook.csv');

/*
|--------------------------------------------------------------------------
| Amazon CSV (inline)
|--------------------------------------------------------------------------
| public path: public/feed/amazon-products.csv
| fallback API: {APP_URL}/api/amazon-all-products
|
| NOTE: Amazon often requires structured JSON feeds via SP-API. This route
| is useful if you prefer uploading a CSV to Amazon's Seller Central (Scheduled fetch).
*/
Route::get('/api/amazon-all-products.csv', function () use ($serveCsv) {
    $fileName = env('FEED_CSV_FILENAME_AMAZON', 'amazon-products.csv');
    $filePath = public_path('feed/' . $fileName);
    $apiUrl = rtrim(config('app.url') ?? env('APP_URL', ''), '/') . '/api/amazon-all-products';
    return $serveCsv($filePath, $apiUrl);
})->name('feeds.amazon.csv');

/*
|--------------------------------------------------------------------------
| Legacy feed route (if you used it)
|--------------------------------------------------------------------------
| /feeds/google-merchant.csv => serve file in storage or public.
*/
Route::get('/feeds/google-merchant.csv', function () {
    $path = public_path('feeds/google-merchant.csv'); // keep previous location if used
    if (!File::exists($path)) abort(404);
    return response(File::get($path), 200)
        ->header('Content-Type', 'text/csv; charset=UTF-8')
        ->header('Content-Disposition', 'inline; filename="google-merchant.csv"')
        ->header('Cache-Control', 'public, max-age=600');
});


Route::get('/api/instagram-all-products.csv', function () use ($serveCsv) {
    $fileName = env('FEED_CSV_FILENAME_INSTAGRAM', 'instagram-products.csv');
    $filePath = public_path('feed/' . $fileName);
    $apiUrl = rtrim(config('app.url') ?? env('APP_URL', ''), '/') . '/api/instagram-all-products';
    return $serveCsv($filePath, $apiUrl);
})->name('feeds.instagram.csv');
