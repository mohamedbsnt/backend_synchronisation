<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoogleMerchantController;

Route::get('/google-all-products', GoogleMerchantController::class)
     ->name('api.google-all-products')
     ->middleware('throttle:60,1');

Route::get('/google-all-products.csv', /* alias CSV */);

