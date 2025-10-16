<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleMerchantController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return new StreamedResponse(function() {
            $resp = Http::timeout(30)->get('https://hanaball.devaito.com/api/fetch-all-products');
            if (! $resp->successful()) {
                abort(502, 'API Devaito inaccessible');
            }
            $products = $resp->json()['products'] ?? [];
            $out = fopen('php://output', 'w');

            // CSV header
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
                $id    = $p['id'] ?? 'NAN';
                $title = $p['name'] ?? 'NAN';
                $link  = $p['url'] ?? 'NAN';
                $img   = $p['image'] ?? 'NAN';

                $avail = 'in stock';
                $price = isset($p['price']) 
                         ? number_format($p['price'],2).' '.$p['devise'] 
                         : 'NAN';
                $cond  = 'new';
                $brand = $p['brand_name'] ?? 'NAN';
                $gtin  = 'NAN';
                $mpn   = $p['slug'] ?? 'NAN';
                $ident = empty($p['brand_name']) ? 'FALSE' : 'TRUE';

                $sale = !empty($p['discount_amount'])
                        ? number_format($p['price'] - $p['discount_amount'],2).' '.$p['devise']
                        : 'NAN';

                if (!empty($p['categories'])) {
                    $type = $p['categories'][0]['name'];
                    $gc   = $mapCat[$type] ?? 'NAN';
                    $cats = implode(', ', array_column($p['categories'],'name'));
                } else {
                    $type = 'NAN';
                    $gc   = 'NAN';
                    $cats = '';
                }

                $desc = $p['name'] ?? 'NAN';
                if ($cats) {
                    $desc .= ' - Catégories: '.$cats;
                }
                if (!empty($p['discount_amount'])) {
                    $desc .= ' - Économisez '.$p['discount_amount'].' '.$p['devise'];
                }

                fputcsv($out, [
                    $id, $title, substr($desc,0,5000), $link, $img,
                    $avail, $price, $cond, $brand, $gtin,
                    $mpn, $ident, $sale, $type, $gc
                ]);
            }

            fclose($out);
        },200,[
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Content-Disposition'=>'inline; filename="google_merchant_feed.csv"',
            'Cache-Control'=>'public, max-age=1800',
        ]);
    }
}
