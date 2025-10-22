<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EbayToken extends Model
{
    protected $fillable = [
        'environment', 'refresh_token', 'access_token', 'access_token_expires_at', 'scope'
    ];

    protected $dates = ['access_token_expires_at'];

    public function isAccessTokenValid(): bool
    {
        return $this->access_token && $this->access_token_expires_at && $this->access_token_expires_at->isFuture();
    }

    public static function current(string $env = null): ?self
    {
        $env = $env ?: env('EBAY_ENVIRONMENT', 'sandbox');
        return static::firstWhere('environment', $env);
    }
}
