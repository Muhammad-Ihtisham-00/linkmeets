<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostEvent extends Model
{
    protected $fillable = [
        'post_id',
        'event_name',
        'event_type',
        'event_date',
        'event_time',
        'is_online',
        'location',
        'description',
        'registration_info',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    protected $casts = [
        'event_date' => 'date', // Carbon instance (date only)
        'event_time' => 'datetime:H:i', // Carbon instance with time only
        'is_online' => 'boolean', // boolean cast for checkbox
    ];
}
