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
            $resp = Http::timeout(30)->get(config('app.url').'/api/fetch-all-products');
            if (! $resp->successful()) {
                abort(502, 'API source inaccessible');
            }

            $products = $resp->json()['products'] ?? [];
            $out = fopen('php://output','w');

            // En-tête CSV Google Merchant
            fputcsv($out, [
                'id','title','description','link','image_link',
                'availability','price','condition','brand','gtin',
                'mpn','identifier_exists','sale_price','product_type','google_product_category'
            ]);

            // Mapping catégories
            $mapCat = [
                'Gestion des Réseaux Sociaux'=>'Business & Industrial > Business Services',
                'Email Marketing'=>'Business & Industrial > Business Services',
                'Stratégies de Contenu'=>'Business & Industrial > Business Services',
                'Publicité en Ligne'=>'Business & Industrial > Business Services > Advertising & Marketing',
            ];

            foreach ($products as $p) {
                $id          = $p['id']            ?? 'NAN';
                $title       = $p['name']          ?? 'NAN';
                $link        = $p['url']           ?? 'NAN';
                $image       = $p['image']         ?? 'NAN';
                $availability= config('app.FEED_DEFAULT_AVAILABILITY','in stock');
                $price       = isset($p['price'])
                               ? number_format($p['price'],2).' '.$p['devise']
                               : 'NAN';
                $condition   = 'new';
                $brand       = $p['brand_name']    ?? 'NAN';
                $gtin        = $p['gtin']          ?? 'NAN';
                $mpn         = $p['slug']          ?? 'NAN';
                $ident       = (!empty($p['gtin'])||!empty($p['slug']))?'TRUE':'FALSE';
                $sale_price  = !empty($p['discount_amount'])
                               ? number_format($p['price'] - $p['discount_amount'],2).' '.$p['devise']
                               : 'NAN';

                if (!empty($p['categories'])) {
                    $type     = $p['categories'][0]['name'];
                    $googleCat= $mapCat[$type] ?? 'NAN';
                    $cats     = implode(', ', array_column($p['categories'],'name'));
                } else {
                    $type     = 'NAN';
                    $googleCat= 'NAN';
                    $cats     = '';
                }

                $desc = $p['description'] ?? $p['name'] ?? 'NAN';
                if ($cats) {
                    $desc .= ' - Catégories: '.$cats;
                }
                if (!empty($p['discount_amount'])) {
                    $desc .= ' - Économisez '.$p['discount_amount'].' '.$p['devise'];
                }

                fputcsv($out, [
                    $id,
                    substr($title,0,150),
                    substr($desc,0,5000),
                    $link,
                    $image,
                    $availability,
                    $price,
                    $condition,
                    $brand,
                    $gtin,
                    $mpn,
                    $ident,
                    $sale_price,
                    $type,
                    $googleCat,
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="google-all-products.csv"',
            'Cache-Control'       => 'public, max-age=1800',
        ]);
    }
}
