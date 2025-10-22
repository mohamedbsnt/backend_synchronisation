<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;

class InstagramFeedController extends Controller
{
    public function csvFromApi(Request $request)
    {
        $apiUrl = env('FEED_SOURCE_API');
        if (empty($apiUrl)) {
            return response()->json(['error' => 'FEED_SOURCE_API not configured'], 500);
        }

        $resp = Http::timeout(20)->get($apiUrl);
        if (!$resp->successful()) {
            return response()->json(['error' => 'Source API fetch failed', 'status' => $resp->status()], 500);
        }

        $data = $resp->json();
        $products = $data['products'] ?? [];

        $headers = [
            'id','title','description','link','image_link','additional_image_link',
            'price','availability','condition','brand','gtin','mpn','product_type'
        ];

        $fh = fopen('php://memory', 'w+');
        // BOM for Excel readability
        fwrite($fh, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fh, $headers);

        foreach ($products as $p) {
            $price = (isset($p['price']) ? number_format((float)$p['price'], 2, '.', '') : '') . ' ' . ($p['devise'] ?? env('FEED_DEFAULT_CURRENCY','MAD'));
            $row = [
                $p['id'] ?? '',
                $p['name'] ?? '',
                $p['description'] ?? ($p['name'] ?? ''),
                $p['url'] ?? '',
                $p['image'] ?? '',
                '', // additional images if any (pipe-separated)
                $price,
                $p['availability'] ?? env('FEED_DEFAULT_AVAILABILITY','in stock'),
                'new',
                $p['brand_name'] ?? env('FEED_DEFAULT_BRAND',''),
                $p['gtin'] ?? '',
                $p['mpn'] ?? '',
                $p['categories'][0]['name'] ?? ''
            ];
            fputcsv($fh, $row);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="instagram-products.csv"'
        ]);
    }
}
