<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostHiring extends Model
{
    protected $fillable = [
        'post_id',
        'job_title',
        'company',
        'location',
        'job_type',
        'experience',
        'description',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
