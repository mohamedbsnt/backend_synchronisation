<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AmazonFeed;
use App\Jobs\SyncAllProductsToAmazon;

class AmazonFeedController extends Controller
{
    public function status()
    {
        $last = AmazonFeed::orderBy('submitted_at','desc')->first();
        return response()->json($last);
    }

    public function trigger(Request $r)
    {
        SyncAllProductsToAmazon::dispatch();
        return response()->json(['message' => 'Sync job dispatched']);
    }
}
