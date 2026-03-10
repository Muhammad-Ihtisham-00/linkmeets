<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceProduct extends Model
{
    use HasFactory;

    protected $table = 'marketplace_products'; // explicit table

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'price',
        'condition',
        'description',
        'location',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }

    public function images()
    {
        return $this->hasMany(MarketplaceProductImage::class, 'product_id');
    }
}
