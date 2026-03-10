<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceCategory extends Model
{
    use HasFactory;

    protected $table = 'marketplace_categories'; // explicit table

    protected $fillable = ['name'];

    public function products()
    {
        return $this->hasMany(MarketplaceProduct::class, 'category_id');
    }
}
