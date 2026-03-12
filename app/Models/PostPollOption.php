<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostPollOption extends Model
{
    protected $fillable = [
        'poll_id',
        'option_text',
        'votes_count'
    ];

    public function poll()
    {
        return $this->belongsTo(PostPoll::class, 'poll_id');
    }

    public function votes()
    {
        return $this->hasMany(PostPollVote::class, 'option_id');
    }
}
