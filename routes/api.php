<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AmazonFeedController;
use App\Http\Controllers\Api\GoogleMerchantController;
use App\Http\Controllers\Api\FacebookFeedController;
use App\Http\Controllers\Api\InstagramFeedController;



Route::get('/google-all-products', GoogleMerchantController::class)
     ->name('api.google-all-products')
     ->middleware('throttle:60,1');

Route::get('/google-all-products.csv', /* alias CSV */);

Route::get('/facebook-all-products', [FacebookFeedController::class, 'csvFromApi'])
    ->name('api.facebook-all-products');


Route::get('/amazon-feed/status', [AmazonFeedController::class, 'status']);
Route::post('/amazon-feed/trigger', [AmazonFeedController::class, 'trigger']);
 


Route::get('/instagram-all-products', [InstagramFeedController::class, 'csvFromApi'])
    ->name('api.instagram-all-products');

