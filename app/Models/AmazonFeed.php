<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonFeed extends Model
{
    protected $fillable = [
        'feed_id', 'feed_type', 'status', 'request_payload', 'response', 'submitted_at', 'processed_at'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
