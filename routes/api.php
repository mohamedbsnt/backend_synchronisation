<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoogleMerchantController;

Route::get('/google-merchant', GoogleMerchantCsvController::class)
     ->name('api.google-merchant')
     ->middleware('throttle:60,1');
