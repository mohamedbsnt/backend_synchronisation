<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'image', 'price', 'currency',
        'url', 'discount_type', 'discount_amount', 'brand_name', 'brand_logo',
        'stock', 'availability', 'google_product_category', 'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relations
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    // Accessors
    public function getFinalPriceAttribute()
    {
        if ($this->discount_amount > 0) {
            return max(0, $this->price - $this->discount_amount);
        }
        return $this->price;
    }

    public function getFullImageUrlAttribute()
    {
        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }
        return url($this->image);
    }

    public function getDevaitoLinkAttribute()
    {
        return $this->url ?: url('/product/' . $this->slug);
    }
}
