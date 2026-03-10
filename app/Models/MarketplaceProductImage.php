<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceProductImage extends Model
{
    use HasFactory;

    protected $table = 'marketplace_product_images'; // explicit table

    protected $fillable = ['product_id', 'image_path'];

    public function product()
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id');
    }
}
