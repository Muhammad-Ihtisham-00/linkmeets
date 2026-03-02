<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessCard extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'phone',
        'website',
        'office_address',
        'facebook',
        'twitter',
        'instagram',
        'youtube',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
