<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostCelebration extends Model
{
    protected $fillable = [
        'post_id',
        'celebration_type',
        'title',
        'description'
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
