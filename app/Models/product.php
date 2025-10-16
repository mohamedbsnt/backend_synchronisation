<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'price',
        'currency',
        'url',
        'discount_type',
        'discount_amount',
        'brand_name',
        'brand_logo',
        'stock',
        'availability',
        'google_product_category',
    ];

    // Relations
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    // Exemple de scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}
