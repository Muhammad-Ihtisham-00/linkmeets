<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostFindExpert extends Model
{
    protected $fillable = [
        'post_id',
        'expertise_needed',
        'project_description',
        'key_requirements',
        'duration',
        'type',
        'budget',
        'is_urgent',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
