<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleMerchantController extends Controller
{
    private const SOURCE_API = 'https://hanaball.devaito.com/api/fetch-all-products';

    /**
     * GET /api/google
     * Renvoie directement le CSV pour Google Merchant Center
     */
    public function __invoke(): StreamedResponse
    {
        return new StreamedResponse(function() {
            $resp = Http::timeout(30)->get(self::SOURCE_API);
            if (! $resp->successful()) {
                abort(502, 'API source inaccessible');
            }
            $products = $resp->json()['products'] ?? [];

            $out = fopen('php://output','w');
            fputcsv($out, [
                'id','title','description','link','image_link',
                'availability','price','condition','brand','gtin',
                'mpn','identifier_exists','sale_price','product_type','google_product_category'
            ]);

            $mapCat = [
                'Gestion des Réseaux Sociaux'=>'Business & Industrial > Business Services',
                'Email Marketing'=>'Business & Industrial > Business Services',
                'Stratégies de Contenu'=>'Business & Industrial > Business Services',
                'Publicité en Ligne'=>'Business & Industrial > Business Services > Advertising & Marketing',
            ];

            foreach ($products as $p) {
                $sale = '';
                if (!empty($p['discount_amount'])) {
                    $sale = number_format($p['price'] - $p['discount_amount'],2).' '.$p['devise'];
                }
                $type = '';
                $gc   = 'Business & Industrial';
                if (!empty($p['categories'])) {
                    $type = $p['categories'][0]['name'];
                    $gc = $mapCat[$type] ?? $gc;
                }
                $desc = $p['name'];
                if (!empty($p['categories'])) {
                    $cats = implode(', ',array_column($p['categories'],'name'));
                    $desc .= ' - Catégories: '.$cats;
                }
                if (!empty($p['discount_amount'])) {
                    $desc .= ' - Économisez '.$p['discount_amount'].' '.$p['devise'];
                }

                fputcsv($out, [
                    $p['id'],
                    substr($p['name'],0,150),
                    substr($desc,0,5000),
                    $p['url'],
                    $p['image'],
                    'in stock',
                    number_format($p['price'],2).' '.$p['devise'],
                    'new',
                    $p['brand_name'] ?? 'Hanaball',
                    '',
                    $p['slug'],
                    empty($p['brand_name'])?'FALSE':'TRUE',
                    $sale,
                    $type,
                    $gc
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Content-Disposition'=>'attachment; filename="google_merchant_feed.csv"',
            'Cache-Control'=>'public, max-age=1800'
        ]);
    }
}
