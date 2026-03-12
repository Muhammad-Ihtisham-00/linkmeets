<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostPoll extends Model
{
    protected $fillable = [
        'post_id',
        'question',
        'duration_days',
        'allow_multiple_answers',
        'expires_at'
    ];

    protected $casts = [
        'allow_multiple_answers' => 'boolean',
        'expires_at' => 'datetime'
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function options()
    {
        return $this->hasMany(PostPollOption::class, 'poll_id');
    }

    public function votes()
    {
        return $this->hasMany(PostPollVote::class, 'poll_id');
    }
}
