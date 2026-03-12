<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostJobSeeker extends Model
{
    protected $fillable = [
        'post_id',
        'title',
        'key_skills',
        'experience',
        'work_preference',
        'about',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
