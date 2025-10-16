<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/feed/google-products.csv', function(){
    $path = public_path(env('FEED_OUTPUT_DIR','feed') . '/' . env('FEED_CSV_FILENAME','google-products.csv'));
    if (!file_exists($path)) {
        abort(404, 'Feed not generated yet.');
    }
    return response()->file($path, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'inline; filename="'.basename($path).'"'
    ]);
});

Route::get('/feed/google-products.xml', function(){
    $path = public_path(env('FEED_OUTPUT_DIR','feed') . '/' . env('FEED_XML_FILENAME','google-products.xml'));
    if (!file_exists($path)) {
        abort(404, 'Feed not generated yet.');
    }
    return response()->file($path, [
        'Content-Type' => 'application/xml; charset=UTF-8',
        'Content-Disposition' => 'inline; filename="'.basename($path).'"'
    ]);
});
